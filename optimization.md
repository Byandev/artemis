# Dashboard & Analytics Optimization Plan

Backend / database-level remediation plan for slow dashboard and analytics pages on large datasets. Organized from highest leverage (ship first) to longer-term structural work.

---

## 1. Entry points audited

Slow paths found during investigation:

- `app/Http/Controllers/Workspaces/WorkspaceController.php:160` — `dashboard()`
- `app/Http/Controllers/Workspaces/WorkspaceController.php:182` — `getChartData()`
- `app/Http/Controllers/Workspaces/RTS/AnalyticController.php:23` — `index()` + `groupBy*` (10+ endpoints)
- `app/Http/Controllers/Workspaces/RTS/ForDeliveryController.php:228` — multiple `count()` calls
- `app/Http/Controllers/Workspaces/CSRController.php:42` — `dailyRecords()` (3 subquery joins)
- `app/Http/Controllers/Workspaces/Product/AnalyticsController.php:14` — `index()` + `metrics()` (4 nested subqueries)
- `Modules/Finance/Http/Controllers/DashboardController.php:15` — no caching at all

---

## 2. Root causes (summary)

1. **Non-sargable `DATE()` calls in WHERE clauses** prevent index usage on timestamps.
2. **Missing composite indexes** on hot tables (`pancake_orders`, `pancake_order_items`, `shipping_addresses`, `pancake_order_phone_number_reports`, `ad_records`).
3. **`whereHas()` chains** (3–4 relations deep) to scope records by workspace — no direct `workspace_id` column on tables like `ad_records`.
4. **Per-metric cloned queries** (5× `count()` per request in RTS, 4× subqueries per product metrics request) instead of single aggregation queries.
5. **No pre-aggregation** — dashboard recomputes every request; no rollup/summary tables except `csr_daily_records`.
6. **Cache store is `database`** (see `config/cache.php:18`) — the cache itself hits the DB.
7. **Frontend mounts fire 5–10 parallel XHRs**, each triggering one of the above queries cold.

---

## 3. Phase 1 — Quick wins (ship this week)

These are low-risk, high-impact. Mostly migrations and small controller edits.

### 3.1 Add missing indexes (migration)

Create `database/migrations/2026_04_20_000000_add_performance_indexes.php`:

| Table | New index | Reason |
|---|---|---|
| `pancake_order_items` | `(order_id)` | Currently **zero indexes**; JOINed in `RtsOrderItemQuery.php:30`. |
| `pancake_orders` | `(workspace_id, status, confirmed_at)` | Covers RTS status + date filter. |
| `pancake_orders` | `(workspace_id, ad_id)` | Covers `RtsAdQuery` grouping. |
| `pancake_orders` | `(workspace_id, confirmed_at, delivered_at, returning_at)` | Covers `getChartData()` range scan. |
| `shipping_addresses` | `(order_id, province_name, district_name)` | Covers `RtsLocationQuery`. |
| `pancake_order_phone_number_reports` | `(order_id, type)` | Covers `RtsCxQuery` join at `RtsCxQuery.php:46`. |
| `pancake_user_daily_reports` | `(workspace_id, date)` | For date-range aggregates without user grouping. |
| `csr_daily_records` | `(workspace_id, date)` | Filtering by workspace + date. |
| `ad_records` | `(ad_id, date)` (regular, in addition to the existing unique) | Range scans on date. |
| `ads` | `(page_id)` | JOINed in product metric subqueries. |

Deploy with `ALGORITHM=INPLACE, LOCK=NONE` (MySQL 8) or in a maintenance window if tables are huge. Run `ANALYZE TABLE` afterwards.

### 3.2 Replace `DATE()` with `whereBetween()`

`app/Http/Controllers/Workspaces/WorkspaceController.php:213-214`:

```php
// Before (full table scan)
->selectRaw('DATE(confirmed_at) as date, SUM(total_amount) as total_sales')
->whereRaw("DATE($dateColumn) >= ? AND DATE($dateColumn) <= ?", [$startDate, $endDate])

// After (uses index on confirmed_at)
->selectRaw("DATE($dateColumn) as date, SUM(total_amount) as total_sales")
->whereBetween($dateColumn, [
    Carbon::parse($startDate)->startOfDay(),
    Carbon::parse($endDate)->endOfDay(),
])
```

Keep `DATE()` only in the `SELECT` / `GROUP BY` — never in the `WHERE`.

### 3.3 Collapse `ForDeliveryController` counts into one query

`app/Http/Controllers/Workspaces/RTS/ForDeliveryController.php:228-246` currently runs 5 cloned queries. Replace with one conditional aggregation:

```php
$stats = (clone $statsBase)
    ->leftJoin('pancake_orders', 'pancake_orders.id', '=', 'pancake_order_for_delivery.order_id')
    ->selectRaw("
        COUNT(*) AS total_for_delivery,
        SUM(CASE WHEN pancake_order_for_delivery.status <> 'PENDING' THEN 1 ELSE 0 END) AS total_called,
        SUM(CASE WHEN pancake_orders.parcel_status = 'delivered' THEN 1 ELSE 0 END) AS total_delivered,
        SUM(CASE WHEN pancake_orders.parcel_status = 'returning' THEN 1 ELSE 0 END) AS total_returning,
        SUM(CASE WHEN pancake_orders.parcel_status = 'undeliverable' THEN 1 ELSE 0 END) AS total_problematic
    ")
    ->first();
```

5 round-trips → 1. Also removes the `whereHas('order', ...)` that re-joins `pancake_orders` three times.

### 3.4 Switch cache store to Redis

`config/cache.php:18` currently defaults to `database`. Set `CACHE_STORE=redis` (Redis is already in the stack via Horizon). `Cache::remember()` calls in `RTS/AnalyticController.php:36` immediately get faster.

Also normalize cache keys: hash the filter payload (`md5(json_encode($filters))`) instead of building keys from raw query strings — reduces fragmentation and cache-miss rate.

---

## 4. Phase 2 — Structural (next sprint)

### 4.1 Pre-aggregated rollup tables

The biggest win. Stop recomputing on every request.

Create two summary tables + nightly/half-hourly jobs:

**`order_daily_summaries`**
```
workspace_id, page_id, shop_id, date,
orders_count, confirmed_count, delivered_count, returning_count,
undeliverable_count, total_sales, total_cod, rts_rate
PRIMARY KEY (workspace_id, page_id, shop_id, date)
INDEX (workspace_id, date)
```

**`ad_spend_daily_summaries`**
```
workspace_id, ad_account_id, campaign_id, date,
spend, impressions, clicks, sales, purchases
PRIMARY KEY (workspace_id, ad_account_id, campaign_id, date)
INDEX (workspace_id, date)
```

Jobs to add under `app/Jobs/Summaries/`:
- `SummarizeOrdersDaily` — run every 30 min in `routes/console.php` (alongside `trigger-fetch-page-orders`), upserts yesterday + today.
- `SummarizeAdSpendDaily` — run hourly.
- Backfill command: `php artisan summaries:backfill --from=... --to=...`.

Dashboard (`WorkspaceController::getChartData()`) then reads from summary tables — a 1–5ms query regardless of dataset size.

This also retrofits the already-migrated-but-unused `city_order_summaries` / `province_order_summaries` tables.

### 4.2 Add `workspace_id` to `ad_records`

Currently every scope reaches `ad_records → ads → pages → workspaces` via `whereHas` (`AdRecord::ofWorkspace` at `app/Models/AdRecord.php:31`).

Denormalize: add `workspace_id` column + index, backfill once, maintain on write. Removes a 3-level `whereHas` from every analytics request.

### 4.3 Rewrite product metrics with a single CTE

`app/Models/Product.php:55-114` exposes 4 `selectSub()` scopes; `AnalyticsController::metrics()` composes all 4 → 4 correlated subqueries per row.

Rewrite as a single query:

```sql
WITH ad_agg AS (
  SELECT p.product_id, SUM(ar.sales) AS ad_sales, SUM(ar.spend) AS ad_spend
  FROM ad_records ar
  JOIN ads a ON a.id = ar.ad_id
  JOIN pages p ON p.id = a.page_id
  WHERE ar.workspace_id = ? AND ar.date BETWEEN ? AND ?
  GROUP BY p.product_id
),
order_agg AS (
  SELECT p.product_id, SUM(o.total_amount) AS order_sales, COUNT(*) AS order_count
  FROM pancake_orders o
  JOIN pages p ON p.id = o.page_id
  WHERE o.workspace_id = ? AND o.confirmed_at BETWEEN ? AND ?
  GROUP BY p.product_id
)
SELECT pr.*, ad_agg.*, order_agg.*
FROM products pr
LEFT JOIN ad_agg ON ad_agg.product_id = pr.id
LEFT JOIN order_agg ON order_agg.product_id = pr.id
WHERE pr.workspace_id = ?;
```

### 4.4 Add a `RtsBaseQuery` cache layer

Move `Cache::remember()` out of controllers and into `RtsBaseQuery::cached()` so every RTS query inherits caching with a consistent key (workspace + filter hash + query class). Add cache invalidation hooks in the order-sync jobs.

---

## 5. Phase 3 — Longer term

- **Read replica** for analytics — route `RtsBaseQuery` and summary reads to a MySQL replica; writes stay on primary.
- **Materialized views** (or scheduled refresh tables) for the heaviest RTS cross-product groupings.
- **Column store for ad_records history** — once > ~10M rows, consider ClickHouse/DuckDB for ad analytics specifically. Keep MySQL for transactional order data.
- **Frontend request consolidation** — the dashboard currently fires 5–10 parallel XHRs on mount (`StatisticBreakdown`, `PageBreakdown`, `ShopBreakdown`, `UserBreakdown`, `AskData`). Bundle into one `/dashboard/bootstrap` endpoint that hits the summary tables once and returns the full payload. Inertia can also push this server-side on initial render.
- **HTTP-level cache headers** on `groupBy*` endpoints with `ETag` so the browser skips re-fetching unchanged group-bys.

---

## 6. Measurement

Before each phase, capture baselines so the work is defensible:

- Enable `DB::enableQueryLog()` (or Telescope — already in stack) on a representative workspace, load dashboard + RTS analytics, capture: total queries, slowest query, total DB time.
- Track `EXPLAIN` output for the top 5 slowest queries before/after each index migration; verify `rows` estimate drops and `key` is populated.
- Add a lightweight timing middleware on `/workspaces/*/dashboard` and `/workspaces/*/rts/analytics/*` that logs p50/p95 to Telescope tags.

Target after Phase 1: dashboard p95 under 800ms on the largest workspace. After Phase 2: under 250ms.

---

## 7. Execution checklist

- [ ] Phase 1.1 — index migration (≈1 day, zero code change beyond migration)
- [ ] Phase 1.2 — `DATE()` → `whereBetween()` in `WorkspaceController::getChartData()`
- [ ] Phase 1.3 — consolidate `ForDeliveryController` counts
- [ ] Phase 1.4 — `CACHE_STORE=redis`, normalize RTS cache keys
- [ ] Phase 2.1 — summary tables + jobs + backfill command
- [ ] Phase 2.2 — denormalize `workspace_id` onto `ad_records`
- [ ] Phase 2.3 — rewrite product metrics as single CTE query
- [ ] Phase 2.4 — `RtsBaseQuery::cached()` helper + invalidation
- [ ] Phase 3 — read replica, frontend bootstrap endpoint, ETags