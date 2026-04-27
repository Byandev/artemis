# Metrics

All metrics dispatched from `WorkspaceMetrics` (`app/Support/WorkspaceMetrics.php`). Each metric class implements `compute()`, `breakdown()`, `perPage()`, `perShop()`, `perUser()`.

**Source legend:**
- **Rollup** — reads from `workspace_page_daily_metrics` (pre-aggregated daily, populated by `analytics:rollup` queue job)
- **Live** — queries `pancake_orders` (or related tables) on every request

All metrics support filters: `page_ids`, `shop_ids`, `user_ids` (→ `pages.owner_id`), `product_ids` (→ `pages.product_id`), `team_ids` (→ `team_user.team_id` → `pages.owner_id`).

**Status snapshot:** 15 of 28 metrics are now on the rollup (all 4 Revenue & Volume except `uniqueCustomerCount`, 4 of 5 Delivery Outcomes, all 6 Fulfillment Lead Time, 2 of 4 Delivery Quality Signals).

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
| `returningAmount` | Sales of orders that entered the returning state in the period (display name: "Entered Returning Amount") | `SUM(entered_returning_amount)` | **Rollup** | — |
| `returnedAmount` | Sales of orders fully returned in the period | `SUM(returned_amount)` | **Rollup** | — |
| `rtsRate` | Return-to-sender rate (decimal 0–1) | `SUM(entered_returning_amount) / SUM(entered_returning_amount + delivered_amount)` | **Rollup** | — |
| `totalForDeliveryCount` | Distinct orders whose "out for delivery" window overlaps the period. Window = `[first_delivery_attempt, COALESCE(delivered_at, returning_at, NOW())]` | `COUNT(*) FROM pancake_orders WHERE first_delivery_attempt <= range_end AND COALESCE(delivered_at, returning_at, NOW()) >= range_start` | Live | **Hard.** The "for-delivery" window spans multiple days and the open end (`NOW()`) shifts daily. A daily rollup column would either miss in-flight orders or double-count across days. Best path: keep live + index `(workspace_id, first_delivery_attempt, delivered_at, returning_at)`. |
| `totalForDeliveryAmount` | Total value of orders whose "out for delivery" window overlaps the period (same window as `totalForDeliveryCount`) | `SUM(final_amount) FROM pancake_orders WHERE first_delivery_attempt <= range_end AND COALESCE(delivered_at, returning_at, NOW()) >= range_start` | Live | **Hard — same as `totalForDeliveryCount`.** |

## Fulfillment Lead Time

All weighted averages computed as `SUM(sum_days_X_to_Y) / SUM(count_X_to_Y)` from the rollup. Columns populated by the builder using `TIMESTAMPDIFF(DAY, …)` for orders where the later timestamp falls on the rollup date and the earlier timestamp is non-null.

| Key | Description | Formula | Source | Path to rollup |
|---|---|---|---|---|
| `averageDaysFromConfirmedToShipped` | Avg days confirmed → shipped | `SUM(sum_days_confirmed_to_shipped) / SUM(count_confirmed_to_shipped)` | **Rollup** | — |
| `averageDaysFromConfirmedToFirstAttempt` | Avg days confirmed → first delivery attempt | `SUM(sum_days_confirmed_to_first_attempt) / SUM(count_confirmed_to_first_attempt)` | **Rollup** | — |
| `averageDaysFromConfirmedToDelivered` | Avg days confirmed → delivered | `SUM(sum_days_confirmed_to_delivered) / SUM(count_confirmed_to_delivered)` | **Rollup** | — |
| `averageDaysFromShippedToFirstAttempt` | Avg days shipped → first delivery attempt | `SUM(sum_days_shipped_to_first_attempt) / SUM(count_shipped_to_first_attempt)` | **Rollup** | — |
| `averageDaysFromShippedToDelivered` | Avg days shipped → delivered | `SUM(sum_days_shipped_to_delivered) / SUM(count_shipped_to_delivered)` | **Rollup** | — |
| `averageDaysFromReturningToReturned` | Avg days returning → returned | `SUM(sum_days_returning_to_returned) / SUM(count_returning_to_returned)` | **Rollup** | — |

## Delivery Quality Signals

| Key | Description | Formula | Source | Path to rollup |
|---|---|---|---|---|
| `deliveredAvgCustomerRts` | Avg historical RTS rate of customers whose orders were delivered in the period | `AVG(customer_rts_rate)` from `pancake_order_phone_number_reports` joined to delivered orders | Live | **Medium.** Add `sum_customer_rts_delivered` + `count_customer_rts_delivered`. Builder needs a `pancake_order_phone_number_reports` join. |
| `returnedAvgCustomerRts` | Avg historical RTS rate of customers whose orders were returned | Same join, for returned orders | Live | **Medium.** Add `sum_customer_rts_returned` + `count_customer_rts_returned` (same join). |
| `deliveredAvgDeliveryAttempts` | Avg `delivery_attempts` for delivered orders | `SUM(sum_delivery_attempts_delivered) / SUM(count_delivery_attempts_delivered)` | **Rollup** | — |
| `returnedAvgDeliveryAttempts` | Avg `delivery_attempts` for orders that entered returning | `SUM(sum_delivery_attempts_returned) / SUM(count_delivery_attempts_returned)` | **Rollup** | — |

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
| `trackedOrdersCount` | Unique orders with sent parcel notifications in period | `SUM(tracked_orders)` from monthly log + count of new sent notifications in range | Live (hybrid) | **Easy but different table + DISTINCT.** Already half-rolled-up via `parcel_journey_notification_log` (monthly grain, per page). Add daily `tracked_orders_count` column to the rollup, populate from `parcel_journey_notifications`. Caveat: the metric is DISTINCT on order_id — sums across days overcount any order with notifications spanning multiple days. |
| `smsSentCount` | SMS sent via parcel notifications in period | `SUM(sms_sent)` from monthly log + count of new sent SMS in range | Live (hybrid) | **Easy but different table.** Add daily `sms_sent_count` column. Pure event count — additive, no DISTINCT issue. |

---

## Rollup grain & rebuild

Rollup table: `workspace_page_daily_metrics` keyed on `(workspace_id, page_id, date)`. Columns:

- **Event count/amount** — `confirmed_count/amount`, `shipped_count/amount`, `delivered_count/amount`, `entered_returning_count/amount`, `returned_count/amount`
- **Latency sum/count** — `sum_days_confirmed_to_shipped` + `count_confirmed_to_shipped` (and 5 more pairs for the other latency transitions)
- **Delivery attempts sum/count** — `sum_delivery_attempts_delivered` + `count_delivery_attempts_delivered`, same for `_returned`

### Command

```bash
analytics:rollup [--date=Y-m-d]                          # single date (defaults to yesterday)
analytics:rollup --from=Y-m-d --to=Y-m-d                 # date range, inclusive
analytics:rollup --workspace=N                           # filter to one workspace
analytics:rollup --workspace=N --page=M                  # filter to one page
```

Each `(workspace, page, date)` tuple dispatches a `RebuildPageDailyMetricsJob` onto the dedicated **`analytics`** Horizon queue (max 3 concurrent workers, configured in `config/horizon.php`).

### Schedules

- `analytics:rollup --date=today` — hourly (keeps today fresh within the hour)
- `analytics:rollup --date=yesterday` — daily at 01:00 (final pass for yesterday after late updates)

### Backfill

```bash
php artisan analytics:rollup --from=2026-01-01 --to=2026-04-25
```

## Rollup expansion difficulty — quick reference

- **Done** — 15 of 28 metrics. Includes 4 Revenue & Volume, 4 Delivery Outcomes, all 6 Fulfillment Lead Time, 2 Delivery Quality Signals.
- **Easy but different table** — `smsSentCount` and `trackedOrdersCount` (parcel_journey_notifications). Builder needs separate queries against those tables. `trackedOrdersCount` also has a DISTINCT-on-order_id wrinkle that makes cross-day sums overcount.
- **Window-overlap (hard for rollup)** — `totalForDeliveryCount`. Each order is "for delivery" for a multi-day window; rollup naturally aggregates daily events, not multi-day intervals. Keep live, add index.
- **Medium (extra joins)** — 2 customer-RTS metrics (`pancake_order_phone_number_reports` join), `timeToFirstOrder` (`pancake_customers.created_at` join).
- **Hard (need separate `workspace_customer_facts` grain)** — `avgLifetimeValue`, all 3 repeat-* metrics, `uniqueCustomerCount` (DISTINCT problem).
- **Skip rollup, use cache** — 3 retention cohort metrics (window is fixed, cache for 1h).
