# Analytics Rollup Plan (7f3a9c)

Pre-aggregate dashboard + RTS analytics into rollup tables so reads become `SELECT SUM(...) FROM small_table` instead of scanning `pancake_orders`.

## Goal

Turn dashboard + RTS analytics cold-load from multi-second aggregations over `pancake_orders` (millions of rows) into sub-100ms lookups against rollup tables (thousands to low-millions of rows).

## Non-goals

- Replace live RMO pages like `ForDeliveryController::public()` — those need row-level data.
- Track sub-daily granularity. Daily is enough for dashboards; today's data stays live or uses a separate hourly table in a later phase.
- Replace the existing cache layer (`AnalyticsController` / `AnalyticController`). The rollup sits *behind* it. Cache still helps for repeat requests within the TTL.

---

## Architecture

Six new tables plus new commands:

1. `workspace_daily_metrics` — main fact table at `(workspace_id, date, page_id)` grain. Covers the bulk of dashboard metrics and all "totals / by page / by shop / by user".
2. `workspace_daily_metrics_by_rider` — sibling table for `RtsRiderQuery`.
3. `workspace_daily_metrics_by_item` — sibling for `RtsOrderItemQuery`.
4. `workspace_daily_metrics_by_location` — sibling for `RtsLocationQuery`.
5. `workspace_daily_metrics_by_confirmed_by` — sibling for `RtsConfirmedByQuery`.
6. `workspace_customer_facts` — customer-level fact for distinct-count + cohort + lifetime-value metrics.

All six are rebuilt by one scheduled command (`analytics:rollup`). Default: rebuild the trailing 14 days every run + a nightly full-today refresh. Backfill is a separate command.

Read path: metric classes gain a `rollup/` variant that the existing `compute/breakdown/perPage/perShop/perUser` methods delegate to when the date range is fully in the rolled-up window. Recent-day cutoff (today) either UNIONs live data or falls back to the live query for that single day.

---

## Design principles

1. **No averages, no ratios, no derived columns stored.** Only additive primitives: counts and sums. Everything else (`rts_rate`, `aov`, `avg_days_*`, `avg_delivery_attempts`, `delivered_avg_customer_rts`, etc.) is computed in SQL at read time from those primitives. Rationale: averages-of-averages is wrong, ratios-of-ratios is wrong, and any formula change would otherwise require a full backfill.
2. **Every AVG decomposes into a `sum_* / count_*` pair.** The count denominator is either a dedicated `count_*` column or an existing stored count (e.g., `delivered_count` is the denominator for `delivered_avg_delivery_attempts`). See the "Derived at read time" list for each metric's formula.
3. **Event-date binding is explicit.** Each column is tied to exactly one event date (confirmed_at / shipped_at / delivered_at / returning_at / first_delivery_attempt / parcel_journeys.created_at). Never put a metric on a row whose date doesn't correspond to the event that produced it.
4. **Rebuild, don't increment.** Status transitions mean incremental deltas drift. Trailing-14-day full rebuilds are the source of truth.

## Schema

### 1. `workspace_daily_metrics` (main)

Grain: `(workspace_id, date, page_id)`. One row per page per day. `shop_id` and `owner_id` are **not** denormalized — `perShop` / `perUser` reads do a small JOIN to `pages` at query time. Trades a trivial read-side JOIN for zero denormalization drift when a page is reassigned to a different shop or owner.

```sql
CREATE TABLE workspace_daily_metrics (
    workspace_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    page_id BIGINT UNSIGNED NOT NULL,

    -- order counts, each bound to the relevant event date
    confirmed_count INT UNSIGNED NOT NULL DEFAULT 0,         -- confirmed_at = date
    shipped_count INT UNSIGNED NOT NULL DEFAULT 0,           -- shipped_at = date
    first_delivery_attempt_count INT UNSIGNED NOT NULL DEFAULT 0, -- first_delivery_attempt = date
    delivered_count INT UNSIGNED NOT NULL DEFAULT 0,         -- status=3, delivered_at = date
    returning_in_transit_count INT UNSIGNED NOT NULL DEFAULT 0, -- status=4, returning_at = date
    returned_final_count INT UNSIGNED NOT NULL DEFAULT 0,    -- status=5, returning_at = date
    for_delivery_count INT UNSIGNED NOT NULL DEFAULT 0,      -- from parcel_journeys: status='On Delivery'

    -- revenue sums (denominated by same event as the matching count)
    total_sales DECIMAL(18,2) NOT NULL DEFAULT 0,            -- SUM(final_amount) on confirmed_at
    delivered_amount DECIMAL(18,2) NOT NULL DEFAULT 0,       -- SUM(final_amount) on delivered_at, status=3
    returning_amount DECIMAL(18,2) NOT NULL DEFAULT 0,       -- SUM(final_amount) on returning_at, status=4
    returned_amount DECIMAL(18,2) NOT NULL DEFAULT 0,        -- SUM(final_amount) on returning_at, status=5

    -- delivery attempt totals (divide by count at read time for avg)
    sum_delivery_attempts_delivered INT UNSIGNED NOT NULL DEFAULT 0,  -- SUM(delivery_attempts) on delivered_at, status=3
    sum_delivery_attempts_returned INT UNSIGNED NOT NULL DEFAULT 0,   -- SUM(delivery_attempts) on returning_at, status in (4,5)

    -- customer RTS rate (sum/count pairs; reconstruct AVG at read time)
    sum_customer_rts_rate_delivered DECIMAL(18,4) NOT NULL DEFAULT 0,
    count_customer_rts_rate_delivered INT UNSIGNED NOT NULL DEFAULT 0,
    sum_customer_rts_rate_returned DECIMAL(18,4) NOT NULL DEFAULT 0,
    count_customer_rts_rate_returned INT UNSIGNED NOT NULL DEFAULT 0,

    -- sum+count pairs for every Average*Days metric, stored on the end-event's date
    sum_days_confirmed_to_shipped INT UNSIGNED NOT NULL DEFAULT 0,
    count_confirmed_to_shipped INT UNSIGNED NOT NULL DEFAULT 0,
    sum_days_confirmed_to_first_attempt INT UNSIGNED NOT NULL DEFAULT 0,
    count_confirmed_to_first_attempt INT UNSIGNED NOT NULL DEFAULT 0,
    sum_days_confirmed_to_delivered INT UNSIGNED NOT NULL DEFAULT 0,
    count_confirmed_to_delivered INT UNSIGNED NOT NULL DEFAULT 0,
    sum_days_shipped_to_first_attempt INT UNSIGNED NOT NULL DEFAULT 0,
    count_shipped_to_first_attempt INT UNSIGNED NOT NULL DEFAULT 0,
    sum_days_shipped_to_delivered INT UNSIGNED NOT NULL DEFAULT 0,
    count_shipped_to_delivered INT UNSIGNED NOT NULL DEFAULT 0,
    sum_days_returning_to_returned INT UNSIGNED NOT NULL DEFAULT 0,
    count_returning_to_returned INT UNSIGNED NOT NULL DEFAULT 0,

    -- parcel journey counts (from parcel_journeys / parcel_journey_notifications)
    tracked_orders_count INT UNSIGNED NOT NULL DEFAULT 0,
    sms_sent_count INT UNSIGNED NOT NULL DEFAULT 0,
    chat_sent_count INT UNSIGNED NOT NULL DEFAULT 0,

    updated_at TIMESTAMP NOT NULL,

    PRIMARY KEY (workspace_id, date, page_id),
    KEY idx_workspace_date (workspace_id, date)
);
```

**Derived at read time (do not store):**
- `aov = total_sales / confirmed_count`
- `rts_rate = (returning_in_transit_count + returned_final_count) / (delivered_count + returning_in_transit_count + returned_final_count) * 100`
- `delivered_avg_delivery_attempts = sum_delivery_attempts_delivered / delivered_count`
- `returned_avg_delivery_attempts = sum_delivery_attempts_returned / (returning_in_transit_count + returned_final_count)`
- All `Average*Days` metrics: `sum_days_* / count_*`
- `delivered_avg_customer_rts = sum_customer_rts_rate_delivered / count_customer_rts_rate_delivered`

### 2. `workspace_daily_metrics_by_rider`

Grain: `(workspace_id, date, rider_name)`. Built from `parcel_journeys` joined to `pancake_orders`.

```sql
CREATE TABLE workspace_daily_metrics_by_rider (
    workspace_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    rider_name VARCHAR(255) NOT NULL,
    delivered_count INT UNSIGNED NOT NULL DEFAULT 0,
    returning_in_transit_count INT UNSIGNED NOT NULL DEFAULT 0,
    returned_final_count INT UNSIGNED NOT NULL DEFAULT 0,
    total_orders_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL,
    PRIMARY KEY (workspace_id, date, rider_name),
    KEY idx_workspace_rider (workspace_id, rider_name)
);
```

Rider is attributed from the latest `parcel_journeys` row per order (same logic as current `RtsRiderQuery` latest-rider subquery). Attribution day = `parcel_journeys.created_at` of that row.

### 3. `workspace_daily_metrics_by_item`

Grain: `(workspace_id, date, product_id, item_name)`. Expands every order into its items.

```sql
CREATE TABLE workspace_daily_metrics_by_item (
    workspace_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    product_id BIGINT UNSIGNED NULL,
    item_name VARCHAR(255) NOT NULL,
    delivered_count INT UNSIGNED NOT NULL DEFAULT 0,
    returning_in_transit_count INT UNSIGNED NOT NULL DEFAULT 0,
    returned_final_count INT UNSIGNED NOT NULL DEFAULT 0,
    total_orders_count INT UNSIGNED NOT NULL DEFAULT 0,
    total_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    total_revenue DECIMAL(18,2) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL,
    PRIMARY KEY (workspace_id, date, product_id, item_name),
    KEY idx_workspace_product (workspace_id, product_id),
    KEY idx_workspace_item_name (workspace_id, item_name)
);
```

If `pancake_order_items.product_id` doesn't exist, drop it and key on `(workspace_id, date, item_name)` only.

### 4. `workspace_daily_metrics_by_location`

Grain: `(workspace_id, date, province_name, district_name)`.

```sql
CREATE TABLE workspace_daily_metrics_by_location (
    workspace_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    province_name VARCHAR(255) NOT NULL,
    district_name VARCHAR(255) NULL,
    delivered_count INT UNSIGNED NOT NULL DEFAULT 0,
    returning_in_transit_count INT UNSIGNED NOT NULL DEFAULT 0,
    returned_final_count INT UNSIGNED NOT NULL DEFAULT 0,
    total_orders_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL,
    PRIMARY KEY (workspace_id, date, province_name, district_name),
    KEY idx_workspace_province (workspace_id, province_name),
    FULLTEXT KEY ft_location (province_name, district_name)
);
```

Fulltext index fixes the `LIKE "%term%"` problem in `RtsLocationQuery::search`.

### 5. `pancake_user_pos_daily_reports` (extend existing)

**This table already exists** (migration `2026_04_21_000001_create_csr_split_daily_report_tables.php`). Same grain we need: `(workspace_id, pancake_user_id, date)`. Already read by `app/Http/Controllers/Workspaces/CSRController.php` and `app/Http/Controllers/API/Workspace/CSRController.php`. Already populated by `app/Console/Commands/SyncCsrDailyRecords.php`.

Reuse it instead of creating a parallel `workspace_daily_metrics_by_confirmed_by`. But it needs three fixes to align with the design principles:

**Problems with the current schema / sync:**
- `rts_rate` stored as a column — violates "no ratios stored" principle; wrong under aggregation across dates or users.
- Columns `delivered` and `returning` are **amounts** (DECIMAL), not counts. API `CSRController.php:20` already references `returning_count` as a sortable field but no such column exists.
- No status 4 vs 5 split — can't distinguish "in transit back" from "fully returned".
- `SyncCsrDailyRecords` only rebuilds one day (`--date=...`) — no drift protection for late status transitions.
- `SyncCsrDailyRecords` doesn't filter by `workspace_id` before `GROUP BY` — full cross-tenant scan on every run.

**Additive migration** (new columns, nothing dropped):

```sql
ALTER TABLE pancake_user_pos_daily_reports
    ADD COLUMN confirmed_count INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN delivered_count INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN returning_in_transit_count INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN returned_final_count INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN delivered_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    ADD COLUMN returning_in_transit_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    ADD COLUMN returned_final_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    ADD COLUMN sum_delivery_attempts_delivered INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN sum_delivery_attempts_returned INT UNSIGNED NOT NULL DEFAULT 0;
```

**Legacy columns kept for compat** (`total_orders`, `total_sales`, `delivered`, `returning`). These are additive (counts/amounts), sum correctly across aggregation, and are read by existing CSR UI. The rewritten sync keeps populating them from the new columns.

**`rts_rate` column is NOT kept.** It's a ratio — stored ratios can't be correctly aggregated, which is the whole reason we're doing this work. Drop it entirely, never write to it again, readers compute at query time:

```sql
ALTER TABLE pancake_user_pos_daily_reports DROP COLUMN rts_rate;
```

Ordered sequence (must be done in this order):
1. Update all readers that reference the stored `rts_rate` column to compute it at query time from the count columns (SUM of numerator / SUM of denominator * 100).
   - API `CSRController.php:101` — already does this, no change needed.
   - `Workspaces/CSRController.php:109, 119` — `AllowedSort::field('rts_rate')` and the allowed filter reference the stored column. Replace with `AllowedSort::custom('rts_rate', ...)` backed by a computed expression `SUM(returning_in_transit_count + returned_final_count) * 100.0 / NULLIF(SUM(delivered_count + returning_in_transit_count + returned_final_count), 0)`.
   - `PancakeUserPosDailyReport` model — remove `'rts_rate'` from `$fillable` and `$casts`.
2. Rewrite `analytics:rollup` / `SyncCsrDailyRecords` to stop writing `rts_rate`.
3. Deploy steps 1 + 2.
4. Migration to drop the column.

Step 4 ships in the same Phase 4 PR as steps 1-3 but runs the schema change AFTER the code deploy (standard zero-downtime migration ordering). If using a deployment model where schema and code go together, run the migration; stale app processes that still read the column will error briefly — acceptable for a short window.

**Rewrite `SyncCsrDailyRecords` → merge into `analytics:rollup`:**
- Rebuild trailing 14 days (not just one).
- Chunk by workspace (`->chunkById` over `Workspace::query()`); apply `where('po.workspace_id', $id)` inside each iteration.
- Populate new count columns + split status 4 / 5.
- Populate legacy columns from the new ones: `total_orders = confirmed_count`, `total_sales` = sum of confirmed-day `final_amount`, `delivered = delivered_amount`, `returning = returning_in_transit_amount + returned_final_amount`.
- Do NOT populate `rts_rate` — column no longer exists after step 4.

**Sibling tables `pancake_user_erp_daily_reports` / `pancake_user_rmo_daily_reports`** from the same migration are **out of scope** for this plan — they're fed by different sources (ERP sync, RMO call tracking) and serve different dashboards. Leave them alone.

### 6. `workspace_customer_facts`

Customer-level facts for distinct-count + cohort + LTV metrics. Rebuilt in full daily (or incrementally after backfill stabilizes).

```sql
CREATE TABLE workspace_customer_facts (
    workspace_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,

    first_confirmed_at DATETIME NULL,
    last_confirmed_at DATETIME NULL,
    first_confirmed_page_id BIGINT UNSIGNED NULL,

    total_confirmed_orders INT UNSIGNED NOT NULL DEFAULT 0,
    total_delivered_orders INT UNSIGNED NOT NULL DEFAULT 0,
    total_confirmed_spend DECIMAL(18,2) NOT NULL DEFAULT 0,
    total_delivered_spend DECIMAL(18,2) NOT NULL DEFAULT 0,

    customer_created_at DATETIME NULL,  -- denormalized from pancake_customers

    updated_at TIMESTAMP NOT NULL,

    PRIMARY KEY (workspace_id, customer_id),
    KEY idx_workspace_first_confirmed (workspace_id, first_confirmed_at),
    KEY idx_workspace_last_confirmed (workspace_id, last_confirmed_at)
);
```

Enables:
- `uniqueCustomerCount` → `COUNT(*) WHERE first_confirmed_at BETWEEN ...` (new customers in range) or `COUNT(DISTINCT customer_id) WHERE last_confirmed_at BETWEEN ...` (active customers).
- `repeatCustomerRatio` / `repeatOrderRatio` / `repeatCustomerOrderCount` — derived from `total_confirmed_orders > 1`.
- `avgLifetimeValue` → `AVG(total_confirmed_spend)` for customers active in range.
- `timeToFirstOrder` → `AVG(TIMESTAMPDIFF(HOUR, customer_created_at, first_confirmed_at))`.
- `retentionXdRateCohort` — joinable self-table on `first_confirmed_at` bucket vs later activity.

---

## Write path

### Scheduled command: `php artisan analytics:rollup`

Defaults: rebuild trailing **14 days** for all tables. Run hourly for "today" + nightly at 01:00 for yesterday + trailing.

Options:
- `--date=YYYY-MM-DD` — rebuild just one day.
- `--from=YYYY-MM-DD --to=YYYY-MM-DD` — rebuild a range (used by backfill).
- `--workspace=ID` — rebuild one workspace (useful for debugging).
- `--tables=main,rider,item,...` — rebuild a subset.

Algorithm per (workspace, date):

1. Start a DB transaction (or per-table; transaction granularity is a tradeoff — see Risks).
2. `DELETE FROM workspace_daily_metrics WHERE workspace_id = ? AND date = ?` — wipe the day.
3. Run the aggregate query against `pancake_orders` (etc.) filtered to that workspace and date, with CASE WHEN branches for each column.
4. `INSERT INTO workspace_daily_metrics (...) SELECT ...`.
5. Commit.

Each table gets its own `INSERT INTO ... SELECT` built from the source query. Example main-table aggregate:

```sql
INSERT INTO workspace_daily_metrics (
    workspace_id, date, page_id, shop_id, owner_id,
    confirmed_count, total_sales,
    delivered_count, delivered_amount, sum_delivery_attempts_delivered,
    returning_in_transit_count, returning_amount,
    returned_final_count, returned_amount, sum_delivery_attempts_returned,
    sum_days_confirmed_to_delivered, count_confirmed_to_delivered,
    -- ... etc ...
    updated_at
)
SELECT
    po.workspace_id,
    DATE(po.confirmed_at) AS date,
    po.page_id,
    p.shop_id,
    p.owner_id,
    SUM(CASE WHEN DATE(po.confirmed_at) = ? THEN 1 ELSE 0 END) AS confirmed_count,
    SUM(CASE WHEN DATE(po.confirmed_at) = ? THEN po.final_amount ELSE 0 END) AS total_sales,
    -- ... etc ...
    NOW()
FROM pancake_orders po
JOIN pages p ON p.id = po.page_id
WHERE po.workspace_id = ?
  AND (
    DATE(po.confirmed_at) = ?
    OR DATE(po.shipped_at) = ?
    OR DATE(po.delivered_at) = ?
    OR DATE(po.returning_at) = ?
    OR DATE(po.first_delivery_attempt) = ?
  )
GROUP BY po.workspace_id, DATE(po.confirmed_at), po.page_id, p.shop_id, p.owner_id;
```

**In practice, splitting this across several smaller aggregates per event-date column is easier to debug and easier for the planner.** Use one INSERT per event dimension, then fold via `ON DUPLICATE KEY UPDATE`.

Date binding rule (must match the live query):
| Column | Event date |
|--------|-----------|
| `confirmed_count`, `total_sales` | `confirmed_at` |
| `shipped_count` | `shipped_at` |
| `first_delivery_attempt_count` | `first_delivery_attempt` |
| `delivered_count`, `delivered_amount`, `sum_delivery_attempts_delivered`, `sum_days_*_to_delivered`, `count_*_to_delivered` | `delivered_at` (status=3) |
| `returning_in_transit_count`, `returning_amount` | `returning_at` (status=4) |
| `returned_final_count`, `returned_amount`, `sum_days_returning_to_returned`, `count_returning_to_returned` | `returning_at` (status=5) |
| `sum_delivery_attempts_returned` | `returning_at` (status in 4,5) |
| `for_delivery_count` | `parcel_journeys.created_at` |
| `tracked_orders_count` | `parcel_journeys.created_at` (first journey row per order) |
| `sms_sent_count`, `chat_sent_count` | `parcel_journey_notifications.created_at` |

### Why rebuild trailing 14 days

Orders transition 3 → 4 → 5 over days. If an order was delivered Jan 1 and returned Jan 10, Jan 1's `delivered_count` must decrement and Jan 10's `returning_in_transit_count` must increment. Incremental deltas are fragile; full-day rebuilds over the trailing window are bulletproof. 14 days covers typical delivery+return cycles with safety margin.

### Scheduling (`routes/console.php`)

```php
Schedule::command('analytics:rollup --date=today')->hourly();
Schedule::command('analytics:rollup --from=yesterday --to=yesterday')->dailyAt('01:00');
Schedule::command('analytics:rollup --from=now-14d --to=yesterday')->dailyAt('02:00');
```

---

## Read path

### Metric class refactor pattern

Each metric class keeps its existing `compute / breakdown / perPage / perShop / perUser` API. Internally, route to a rollup reader when the date range is fully in the rolled-up window, else fall back to the live query (`compute` as it exists today).

Example — `TotalSales::compute`:

```php
public function compute(int $workspaceId, array $date_range, array $filter): float
{
    if ($this->canUseRollup($date_range)) {
        return $this->computeFromRollup($workspaceId, $date_range, $filter);
    }
    return $this->computeLive($workspaceId, $date_range, $filter);
}

private function computeFromRollup(int $workspaceId, array $date_range, array $filter): float
{
    return (float) DB::table('workspace_daily_metrics')
        ->where('workspace_id', $workspaceId)
        ->whereBetween('date', [$date_range['start_date'], $date_range['end_date']])
        ->when(! empty($filter['page_ids']), fn ($q) => $q->whereIn('page_id', $this->parseIds($filter['page_ids'])))
        ->when(! empty($filter['shop_ids']), fn ($q) => $q->whereIn('shop_id', $this->parseIds($filter['shop_ids'])))
        ->sum('total_sales');
}
```

Example — `RtsRate::compute`:

```php
private function computeFromRollup(int $workspaceId, array $date_range, array $filter): float
{
    $row = DB::table('workspace_daily_metrics')
        ->where('workspace_id', $workspaceId)
        ->whereBetween('date', [$date_range['start_date'], $date_range['end_date']])
        ->when(...)
        ->selectRaw('
            SUM(returning_in_transit_count + returned_final_count) as returned,
            SUM(delivered_count + returning_in_transit_count + returned_final_count) as total
        ')
        ->first();

    if (! $row || (int) $row->total === 0) return 0;
    return round(((float) $row->returned * 100.0) / (float) $row->total, 2);
}
```

### Today handling

Rollup covers up to yesterday fully; today is rebuilt hourly but may lag. Two options:
- **Simple (recommended first):** if `end_date >= today`, recompute just today's slice live and add to the rollup sum. Wrap in: `rollup[start..yesterday] + live[today]`.
- **Strict rollup-only:** force hourly rollup to complete before read. Simpler query, but today's numbers are up to 60 min stale.

### RTS queries refactor

Each `Rts*Query` gets an alternate reader that hits the sibling table:

- `RtsRiderQuery` → read from `workspace_daily_metrics_by_rider`.
- `RtsOrderItemQuery` → read from `workspace_daily_metrics_by_item`.
- `RtsLocationQuery` → read from `workspace_daily_metrics_by_location`. Search switches to `MATCH(province_name, district_name) AGAINST(? IN BOOLEAN MODE)`.
- `RtsConfirmedByQuery` → read from `workspace_daily_metrics_by_confirmed_by`.

`RtsBaseQuery`'s OR-date-filter problem disappears because the rollup tables have a single `date` column, not `delivered_at OR returning_at`.

`RtsCxQuery` stays on the live path for now (bucket depends on per-order phone-report rate; pre-aggregation is a later phase — see "Deferred work").

### Distinct-count metrics refactor

Every metric currently using `COUNT(DISTINCT customer_id)` or cohort joins swaps to `workspace_customer_facts`:

- `UniqueCustomerCount::compute` → `COUNT(*) FROM workspace_customer_facts WHERE workspace_id = ? AND last_confirmed_at BETWEEN ?` (active customers) or filter on `first_confirmed_at` for new-customer counts.
- `RepeatCustomerRatio` → ratio of rows with `total_confirmed_orders > 1`.
- `AverageLifetimeValue` → `AVG(total_confirmed_spend)`.
- `TimeToFirstOrder` → `AVG(TIMESTAMPDIFF(HOUR, customer_created_at, first_confirmed_at))`.
- `RetentionXdRateCohort` — self-join on cohort day vs activity day.

---

## Migrations

5 new tables + 2 migrations against the existing `pancake_user_pos_daily_reports` (add count columns, then drop `rts_rate`). Place in `database/migrations/`:

```
2026_04_22_120000_create_workspace_daily_metrics_table.php
2026_04_22_120001_create_workspace_daily_metrics_by_rider_table.php
2026_04_22_120002_create_workspace_daily_metrics_by_item_table.php
2026_04_22_120003_create_workspace_daily_metrics_by_location_table.php
2026_04_22_120004_add_count_columns_to_pancake_user_pos_daily_reports_table.php   ← ALTER (additive)
2026_04_22_120005_create_workspace_customer_facts_table.php
2026_04_22_120006_drop_rts_rate_from_pancake_user_pos_daily_reports_table.php    ← DROP COLUMN (ships only after all readers stop using the stored column)
```

Keep fulltext index creation on `by_location` in a follow-up migration if MySQL version / engine needs special handling.

---

## Backfill

Separate command: `php artisan analytics:rollup:backfill`.

Args:
- `--from=YYYY-MM-DD` (required) — earliest date with data (for Pancake, check `MIN(pancake_orders.confirmed_at)`).
- `--to=YYYY-MM-DD` (default: yesterday).
- `--workspace=ID` (default: all).
- `--chunk-days=7` (default: 7, process a week at a time per workspace).

Strategy:
1. Iterate workspaces in ASC ID order.
2. For each workspace, iterate date chunks backward from `--to` to `--from` in `--chunk-days` windows.
3. For each chunk, call the same rollup logic the scheduled command uses.
4. Log progress; checkpoint the last completed `(workspace_id, date)` in a small `analytics_backfill_progress` table so the command is resumable.

Expected runtime: single-digit hours for the main table on a 10M-row `pancake_orders`. Customer facts and location/item/rider tables may need a full table scan — expect similar runtime. Run off-hours. Consider a `--dry-run` flag for the first pass.

---

## Phased rollout

Do **not** ship this all at once. Six phases over ~2-3 weeks:

### Phase 1 — Foundation (1-2 days)
- Migration for `workspace_daily_metrics` only.
- `analytics:rollup` command that builds the **main** table only, with columns for: `confirmed_count`, `total_sales`, `delivered_count`, `delivered_amount`, `returning_in_transit_count`, `returned_final_count`, `returning_amount`, `returned_amount`.
- Backfill command (main table only).
- Schedule.
- **No reads yet.** Let it run for a day, compare rollup sums vs live sums per workspace, sanity-check.

### Phase 2 — Simple reads (1 day)
Refactor the lowest-risk metric classes to read from the rollup:
- `TotalSales`
- `TotalOrders`
- `DeliveredAmount`
- `ReturnedAmount`
- `ReturningAmount`
- `TotalForDeliveryCount` (once `for_delivery_count` is populated)

Keep the live fallback behind a feature flag or a `USE_METRICS_ROLLUP` env var. Gate the switch per-metric.

### Phase 3 — Derived metrics + RtsRate (1 day)
- `RtsRate` — the big win; this is the metric most sensitive to slow live aggregation.
- `Aov`
- `DeliveredAvgDeliveryAttempts`, `ReturnedAvgDeliveryAttempts` (requires `sum_delivery_attempts_*` columns — add to rollup + backfill first).
- All `Average*Days` metrics (requires sum+count pairs — add to rollup + backfill first).

### Phase 4 — RTS sibling tables + CSR fixup (2-3 days)
- Migrations for `by_rider`, `by_item`, `by_location`.
- **ALTER** `pancake_user_pos_daily_reports` (additive): add count columns + status 4/5 split + delivery attempts sums.
- Extend `analytics:rollup` and `analytics:rollup:backfill` to populate all four.
- Fold `SyncCsrDailyRecords` logic into `analytics:rollup` (workspace-scoped, trailing-14-days rebuild). Old command becomes a thin wrapper that calls the new one, then deprecated.
- Refactor `RtsRiderQuery`, `RtsOrderItemQuery`, `RtsLocationQuery`, `RtsConfirmedByQuery`.
- Refactor `Workspaces/CSRController` + API `CSRController` + `PancakeUserPosDailyReport` model to:
  - Read new count columns.
  - Compute `rts_rate` at query time from the count columns (not from a stored column).
  - Replace `AllowedSort::field('rts_rate')` with `AllowedSort::custom('rts_rate', ...)` backed by the computed SQL.
- Location fulltext search.
- **After code deploy lands:** ship the DROP COLUMN migration for `rts_rate` (migration `120006`).

### Phase 5 — Customer facts (2 days)
- Migration + rollup logic for `workspace_customer_facts`.
- Refactor `UniqueCustomerCount`, `RepeatCustomerRatio`, `RepeatOrderRatio`, `RepeatCustomerOrderCount`, `AverageLifetimeValue`, `TimeToFirstOrder`.
- Retention cohorts (`Retention30d/60d/90dRateCohort`) are the most complex — last.

### Phase 6 — Cleanup (0.5 day)
- Remove feature flag once stable.
- Remove live-query fallback code paths where they're no longer needed.
- Drop unused indexes on `pancake_orders` that the rollup has made redundant (careful — some are still used by RMO / live paths).

---

## Validation

Parity tests are non-negotiable. For each phase:

1. **Per-workspace daily parity check** — a dev artisan command that, for a given workspace and date range, computes the metric via rollup and via live and diffs them. Target: identical to 2 decimal places (allow rounding tolerance for floats).
2. **Dashboard shadow mode** — run both paths in production for 1-2 days, log results to a `metrics_shadow_log` table, alert if delta > 0.5%.
3. **Spot check** — eyeball 3 busy workspaces on RTS analytics before/after, screenshots in PR.

---

## Risks & mitigations

| Risk | Mitigation |
|------|------------|
| Rollup drift when order statuses change beyond 14-day window | Monthly full rebuild command (`analytics:rollup:backfill --from=now-60d --to=now-15d`); alert if reconciliation gap > threshold |
| Schema change on `pancake_orders` (new status enum value) | Code review checklist for anything touching status transitions; rollup command fails loudly on unknown status |
| Backfill wedges production DB | Run off-hours; chunk by `--chunk-days=1` if needed; monitor replication lag |
| Query planner picks wrong index on new tables | `ANALYZE TABLE` after backfill; add hints only if required |
| `customer_facts` rebuild too slow | Start with rebuild-trailing-N-days window same as main table; only do full rebuilds weekly |
| Feature flag left on too long creates two code paths to maintain | Set a hard deletion date in the PR description and track with a calendar reminder |

---

## Deferred work (not in this plan)

- **Sub-daily grain** (hourly rollup) for today's data — add an `workspace_hourly_metrics` later if product needs it.
- **RTS CX bucket pre-aggregation** — requires denormalizing the bucket onto `pancake_orders` itself, separate project.
- **HyperLogLog for distinct counts** — only if `workspace_customer_facts` proves too slow at scale.
- **Read replica routing** — separate initiative; orthogonal to rollup.
- **Dashboard per-metric caching in `WorkspaceMetrics::extract`** — still worth doing as a quick win before this rollup lands (cheap, ~1 hr); rollup makes it less critical but not obsolete.

---

## Files that will change

New:
- `database/migrations/2026_04_22_12000*_create_workspace_daily_metrics*.php` (4 new tables) + `*_add_count_columns_to_pancake_user_pos_daily_reports_table.php` (ALTER) + `*_create_workspace_customer_facts_table.php`
- `app/Console/Commands/RollupAnalytics.php`
- `app/Console/Commands/RollupAnalyticsBackfill.php`
- `app/Models/WorkspaceDailyMetric.php` (+ siblings for rider/item/location/customer_facts)
- `app/Support/AnalyticsRollup/*.php` — per-table builder classes to keep the command thin

Modified:
- All 27 metric classes in `app/Metrics/Orders/` and `app/Metrics/ParcelJourney/` — gain rollup reader paths.
- All 10 `app/Queries/Rts*Query.php` — gain rollup reader paths.
- `app/Console/Commands/SyncCsrDailyRecords.php` — becomes a thin wrapper over the new rollup command (deprecated, scheduled to be removed after migration stabilizes).
- `app/Http/Controllers/Workspaces/CSRController.php` + `app/Http/Controllers/API/Workspace/CSRController.php` — read new count columns, drop dependency on stored `rts_rate`.
- `routes/console.php` — new schedules, remove old `sync:csr-daily-records` schedule.
- Possibly `app/Support/WorkspaceMetrics.php` if we add per-metric caching at the same time.

Unchanged (explicitly out of scope):
- `pancake_user_erp_daily_reports` and `pancake_user_rmo_daily_reports` tables — different source systems.
- `ForDeliveryController::public()` and other row-level live reads.
- `AnalyticsController` / `AnalyticController` cache layer.
- Frontend — reads the same JSON shape.
