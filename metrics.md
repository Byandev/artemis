# Metrics

All metrics dispatched from `WorkspaceMetrics` (`app/Support/WorkspaceMetrics.php`). Each metric class implements `compute()`, `breakdown()`, `perPage()`, `perShop()`, `perUser()`.

**Source legend:**
- **Rollup** — reads from `workspace_page_daily_metrics` (pre-aggregated daily, populated by `analytics:rollup` queue job)
- **Live** — queries `pancake_orders` (or related tables) on every request

All metrics support filters: `page_ids`, `shop_ids`, `user_ids` (→ `pages.owner_id`), `product_ids` (→ `pages.product_id`), `team_ids` (→ `team_user.team_id` → `pages.owner_id`).

---

## Revenue & Volume

| Key | Description | Formula | Source | Path to rollup |
|---|---|---|---|---|
| `totalSales` | Total revenue from confirmed orders in the period | `SUM(confirmed_amount)` | **Rollup** | — |
| `totalOrders` | Number of orders confirmed in the period | `SUM(confirmed_count)` | **Rollup** | — |
| `aov` | Average order value | `SUM(confirmed_amount) / SUM(confirmed_count)` | **Rollup** | — |
| `uniqueCustomerCount` | Distinct customers who ordered in the period | `COUNT(DISTINCT pancake_orders.customer_id)` where `confirmed_at` in range | Live | **Hard.** DISTINCT counts aren't additive across days. Options: (a) HyperLogLog sketch column (approximate), (b) per-customer fact table joined at read time (exact, separate grain). |

## Delivery Outcomes

| Key | Description | Formula | Source | Path to rollup |
|---|---|---|---|---|
| `deliveredAmount` | Sales of orders delivered in the period | `SUM(delivered_amount)` | **Rollup** | — |
| `returningAmount` | Sales of orders currently in transit back (snapshot) | `SUM(final_amount)` where `returning_at` in range AND `returned_at IS NULL` *as-of-now* | Live | **Semantic shift required.** Snapshots don't aggregate (would double-count). Options: (a) store end-of-day snapshot, read latest date in range only — start date becomes decorative. (b) Replace metric with event-based "Returning Started" using existing `entered_returning_amount` column (different meaning). |
| `returnedAmount` | Sales of orders fully returned in the period | `SUM(returned_amount)` | **Rollup** | — |
| `rtsRate` | Return-to-sender rate (decimal 0–1) | `SUM(entered_returning_amount) / SUM(entered_returning_amount + delivered_amount)` | **Rollup** | — |
| `totalForDeliveryCount` | Parcel journeys "On Delivery" in the period | `COUNT(*) FROM parcel_journeys WHERE status='On Delivery'` | Live | **Easy but different grain.** Add `for_delivery_count` column populated from `parcel_journeys` (parcel-journey events, not orders). Worth doing if it's slow. |

## Fulfillment Lead Time

All averages of `TIMESTAMPDIFF(DAY, …)` for orders that completed the indicated transition in the period.

| Key | Description | Formula | Source | Path to rollup |
|---|---|---|---|---|
| `averageDaysFromConfirmedToShipped` | Avg days confirmed → shipped | `AVG(DATEDIFF(shipped_at, confirmed_at))` over orders shipped in range | Live | **Easy.** Add `sum_days_confirmed_to_shipped` + `count_confirmed_to_shipped` columns. Read = SUM/SUM weighted avg. |
| `averageDaysFromConfirmedToFirstAttempt` | Avg days confirmed → first delivery attempt | `AVG(DATEDIFF(first_delivery_attempt, confirmed_at))` | Live | **Easy.** Add `sum_days_confirmed_to_first_attempt` + `count_confirmed_to_first_attempt` columns. |
| `averageDaysFromConfirmedToDelivered` | Avg days confirmed → delivered | `AVG(DATEDIFF(delivered_at, confirmed_at))` over orders delivered in range | Live | **Easy.** Add `sum_days_confirmed_to_delivered` + `count_confirmed_to_delivered` columns. |
| `averageDaysFromShippedToFirstAttempt` | Avg days shipped → first delivery attempt | `AVG(DATEDIFF(first_delivery_attempt, shipped_at))` | Live | **Easy.** Add `sum_days_shipped_to_first_attempt` + `count_shipped_to_first_attempt` columns. |
| `averageDaysFromShippedToDelivered` | Avg days shipped → delivered | `AVG(DATEDIFF(delivered_at, shipped_at))` over orders delivered in range | Live | **Easy.** Add `sum_days_shipped_to_delivered` + `count_shipped_to_delivered` columns. |
| `averageDaysFromReturningToReturned` | Avg days returning → returned | `AVG(DATEDIFF(returned_at, returning_at))` over orders returned in range | Live | **Easy.** Add `sum_days_returning_to_returned` + `count_returning_to_returned` columns. |

## Delivery Quality Signals

| Key | Description | Formula | Source | Path to rollup |
|---|---|---|---|---|
| `deliveredAvgCustomerRts` | Avg historical RTS rate of customers whose orders were delivered in the period | `AVG(customer_rts_rate)` from `pancake_order_phone_number_reports` joined to delivered orders | Live | **Medium.** Add `sum_customer_rts_delivered` + `count_customer_rts_delivered`. Builder needs a `pancake_order_phone_number_reports` join. |
| `returnedAvgCustomerRts` | Avg historical RTS rate of customers whose orders were returned | Same join, for returned orders | Live | **Medium.** Add `sum_customer_rts_returned` + `count_customer_rts_returned` (same join). |
| `deliveredAvgDeliveryAttempts` | Avg `delivery_attempts` for delivered orders | `AVG(pancake_orders.delivery_attempts)` where status=3 in range | Live | **Easy.** Add `sum_delivery_attempts_delivered` + reuse `delivered_count`. |
| `returnedAvgDeliveryAttempts` | Avg `delivery_attempts` for returned orders | `AVG(pancake_orders.delivery_attempts)` where status IN (4,5) in range | Live | **Easy.** Add `sum_delivery_attempts_returned` + reuse `returned_count`. |

## Customer Quality & Retention

All cohort/repeat metrics require full customer history — fundamentally not rollup-friendly at the workspace+page+date grain.

| Key | Description | Formula | Source | Path to rollup |
|---|---|---|---|---|
| `repeatOrderRatio` | Share of orders in period placed by repeat customers (2+ lifetime orders) | `COUNT(orders by repeat customers) / COUNT(all orders)` in range | Live | **Hard — different grain.** Needs a `workspace_customer_facts` table (per workspace+customer: total_orders). Then count daily orders against that fact. v1 had this table — was removed. |
| `repeatCustomerRatio` | Share of unique customers in period who are repeat (2+ lifetime orders) | `COUNT(DISTINCT repeat customers) / COUNT(DISTINCT all customers)` in range | Live | **Hard.** Same `workspace_customer_facts` requirement + DISTINCT count problem (HLL or fact-table read). |
| `repeatCustomerOrderCount` | Count of customers in period who have placed 2+ lifetime orders | `COUNT(DISTINCT customers WHERE lifetime order count >= 2)` | Live | **Hard.** Same `workspace_customer_facts` requirement + DISTINCT count. |
| `timeToFirstOrder` | Avg hours from customer signup to first confirmed order | `AVG(TIMESTAMPDIFF(HOUR, pancake_customers.created_at, MIN(confirmed_at)))` | Live | **Medium.** Per-customer fact (`first_confirmed_at`) + cached `customer_created_at` join. Could store `sum_hours_to_first_order` + `count_first_orders` per page+date keyed on first-order date. |
| `avgLifetimeValue` | Avg total spend per customer (lifetime, as of end date) | `SUM(final_amount) / COUNT(DISTINCT customer_id)` over all confirmed orders up to end date | Live | **Hard.** Lifetime cumulative; depends on full history up to end date. Either (a) maintain `workspace_customer_facts.total_confirmed_spend` per-customer, then aggregate at read; (b) store running totals per workspace+date (no per-page slice). |
| `retention30dRateCohort` | Of customers who first ordered 30–60 days ago, % that ordered again within 30 days | `retained / cohort_size` for rolling 30d cohort | Live | **Hard but cacheable.** Window is fixed (today−60 to today−30), independent of dashboard date filter. Just cache the result per workspace+filter for 1 hour — much simpler than rollup. |
| `retention60dRateCohort` | Same, 60-day cohort and window | `retained / cohort_size` for rolling 60d cohort | Live | **Same — cache instead.** |
| `retention90dRateCohort` | Same, 90-day cohort and window | `retained / cohort_size` for rolling 90d cohort | Live | **Same — cache instead.** |

## Parcel Notifications

| Key | Description | Formula | Source | Path to rollup |
|---|---|---|---|---|
| `trackedOrdersCount` | Unique orders with sent parcel notifications in period | `SUM(tracked_orders)` from monthly log + count of new sent notifications in range | Live (hybrid) | **Easy.** Already half-rolled-up via the monthly log. Add daily `tracked_orders_count` to the rollup table; populate from `parcel_journey_notifications`. |
| `smsSentCount` | SMS sent via parcel notifications in period | `SUM(sms_sent)` from monthly log + count of new sent SMS in range | Live (hybrid) | **Easy.** Same pattern: add daily `sms_sent_count` column. |

---

## Rollup grain & rebuild

Rollup table: `workspace_page_daily_metrics` keyed on `(workspace_id, page_id, date)`. Columns: `confirmed_count/amount`, `shipped_count/amount`, `delivered_count/amount`, `entered_returning_count/amount`, `returned_count/amount`.

Rebuild via `analytics:rollup [--date=Y-m-d]` (dispatches one queue job per `(workspace, page, date)`). Schedules: hourly for today, daily 01:00 for yesterday.

## Rollup expansion difficulty — quick reference

- **Easy (one builder query, additive columns)** — all 6 latency metrics, 2 delivery-attempts metrics, 2 parcel notification metrics, `totalForDeliveryCount` (different grain).
- **Medium (extra joins or different grain)** — 2 customer-RTS metrics, `timeToFirstOrder`.
- **Hard (need separate `workspace_customer_facts` grain)** — `avgLifetimeValue`, all 3 repeat-* metrics, `uniqueCustomerCount` (DISTINCT problem).
- **Skip rollup, use cache** — 3 retention cohort metrics (window is fixed, cache for 1h).
- **Semantic shift required** — `returningAmount` (snapshot vs event).
