# RTS Analytics Metrics

This document covers the **RTS Analytics page** (`resources/js/pages/workspaces/rts/analytics.tsx`) — a separate surface from the main dashboard. Each card slices the same RTS denominator (orders that reached a delivery outcome) by a different dimension.

**Page entry:** `app/Http/Controllers/Workspaces/RTS/AnalyticController.php`

**Source legend:**
- **Live** — queries `pancake_orders` on every request via the `RtsBaseQuery` class
- **Rollup (proposed)** — would read from a new `workspace_page_rts_daily_*` table
- **Reuse main rollup** — could read from existing `workspace_page_daily_metrics`

**Core formula (all cards):**
```
RTS rate = returned_count / total_orders × 100
        = (orders with status IN (4, 5)) / (orders with status IN (3, 4, 5)) × 100
```

Date filter on either `delivered_at` OR `returning_at`. Status 3 = delivered, 4 = returning (in transit), 5 = returned (final).

**Filters supported by every card:** `start_date`, `end_date`, `page_ids`, `shop_ids`. *(Note: this page does not currently support `user_ids` / `product_ids` / `team_ids` — different filter set than the main dashboard.)*

---

## Cards

### Low-cardinality (derived buckets)

These dimensions produce a fixed, small number of buckets per (workspace, page, date). Cheapest to roll up.

| Card | Endpoint | Query class | Dimension | Buckets | Source |
|---|---|---|---|---|---|
| **Price** | `groupByPrice` | `RtsPriceQuery` | Price band on `final_amount` | 9 fixed (`0-250` … `5000+`) | Live |
| **Order Frequency** | `groupByOrderFrequency` | `RtsOrderFrequencyQuery` | `customer_succeed_order_count + customer_returned_order_count` | 5 fixed (`1` … `5+`) | Live |
| **Delivery Attempts** | `groupByDeliveryAttempts` | `RtsDeliveryAttemptsQuery` | `delivery_attempts` | 5 fixed (`1` … `5+`) + null | Live |
| **Cx RTS** | `groupByCxRts` | `RtsCxQuery` | Customer's historical RTS rate from `pancake_order_phone_number_reports` | 11 (`no_report`, `0-10` … `91-100`) × 2 types (`latest`/`initial`) | Live |

### Medium-cardinality (entity dimensions)

Dozens to a few hundred distinct values per workspace.

| Card | Endpoint | Query class | Dimension | Source |
|---|---|---|---|---|
| **Confirmed By** | `groupByConfirmedBy` | `RtsConfirmedByQuery` | `pancake_users.name` (joined via `pancake_orders.confirmed_by`) | Live |
| **Ad** | `groupByAd` | `RtsAdQuery` | `ads.name` (joined via `pancake_orders.ad_id`) | Live |
| **Rider** | `groupByRider` | `RtsRiderQuery` | `parcel_journeys.rider_name` (subquery: latest "On Delivery" status per order) | Live |

### High-cardinality (free-text dimensions)

Thousands of distinct values possible. These are the most expensive live and least friendly to roll up.

| Card | Endpoint | Query class | Dimension | Source |
|---|---|---|---|---|
| **Product** | `groupByOrderItem` | `RtsOrderItemQuery` | `pancake_order_items.name` (joined via `order_id`) | Live |
| **Location (Province)** | `groupByProvinces` | `RtsLocationQuery::byProvince` | `shipping_addresses.province_name` | Live |
| **Location (City)** | `groupByCities` | `RtsLocationQuery::byCity` | `shipping_addresses.district_name` + province | Live |

---

## Why this is harder than the main dashboard

The main dashboard rollup keys on `(workspace, page, date)`. The RTS page slices by **9 different dimensions** at that same grain, so naive expansion = 9 sibling tables. That was v1's mistake — too many tables, hard to hold in your head.

A single fact table at `(workspace, page, date)` only stores the **overall** RTS rate per page per day. To slice by rider/item/location we'd need either:
- Separate tables per dimension (high cardinality → wide tables × many days)
- A wide table with N columns per fixed-bucket dimension
- A schemaless approach (one row per `(workspace, page, date, dimension, dimension_value)`)

---

## Rollup plan — staged

### Stage 0 (already done — reuse main rollup)

The **overall RTS rate** for the page is already computable from `workspace_page_daily_metrics` columns we built for the dashboard:

```sql
SUM(entered_returning_amount) / SUM(entered_returning_amount + delivered_amount)
```

The per-page summary header on the RTS page can read from there for free.

### Stage 1 — fold low-cardinality dimensions into the existing rollup

Add bucket columns directly to `workspace_page_daily_metrics` (same row, no new tables):

| Dimension | Columns to add | Cost |
|---|---|---|
| Price | 9 × 3 = 27 columns: `price_0_250_total`, `price_0_250_returned`, `price_0_250_delivered`, … | Wide, but flat |
| Order Frequency | 5 × 3 = 15 columns: `freq_1_total`, `freq_1_returned`, … | Wide |
| Delivery Attempts | 6 × 3 = 18 columns (incl. null bucket) | Wide |
| Cx RTS | 11 × 3 × 2 (latest/initial) = 66 columns | Very wide |

Cx RTS is borderline — 66 columns is a lot. Could store as a separate sibling table to keep the main one clean.

**Alternative: schemaless bucket table** — `workspace_page_rts_buckets_daily(workspace_id, page_id, date, dimension, bucket, total, delivered, returned)`. ~50 rows per (workspace, page, date) covering all buckets across all 4 dimensions. Avoids the wide-column problem.

### Stage 2 — sibling tables for medium-cardinality dimensions

One table per entity dimension, keyed on `(workspace_id, page_id, date, entity_id)`:

| Dimension | Table | Estimated rows/day |
|---|---|---|
| Confirmed By | `workspace_page_rts_by_user_daily` | 5–50 per page |
| Ad | `workspace_page_rts_by_ad_daily` | 1–50 per page |
| Rider | `workspace_page_rts_by_rider_daily` | 5–100 per page |

Each table has the standard `(total_orders, delivered_count, returned_count)` triplet. Read = SUM grouped by entity_id, joined to entity table for name.

For Rider specifically: the live query does a subquery to find the rider from the latest "On Delivery" parcel_journey entry — the builder needs to replicate that.

### Stage 3 — high-cardinality dimensions

Two sub-options:

**Option A (rollup):** Build sibling tables `workspace_page_rts_by_item_daily` and `workspace_page_rts_by_location_daily`. For a workspace with thousands of distinct items/locations, this means thousands of rows per (page, day) — but rollup queries still run on small slices of those rows.

**Option B (live + index):** Skip the rollup, ensure indexes on `pancake_order_items(order_id, name)` and `shipping_addresses(order_id, province_name, district_name)`. Live queries scan the date-filtered orders + join. Pagination caps the rendered slice.

**Recommendation:** start with Option B. If the page is still slow after Stages 0–2, move to Option A.

### Stage 4 — fix the cache TTL

`AnalyticController::ttl()` is currently hardcoded to `return 1;` (1 second). The original logic that's commented out — 24h for fully-historical ranges, 5min for ranges touching today — should be restored. **Free perf win, no rollup work needed.**

---

## Suggested execution order

1. **Stage 4** (cache TTL) — 1 line change, immediate impact. Do first.
2. **Stage 1** as schemaless bucket table — covers 4 of 9 cards with a single new table + builder. Low risk.
3. **Profile remaining 5 cards** (rider, ad, confirmed_by, item, location) under realistic data volume. Prioritize by actual slowness.
4. **Stage 2** sibling tables for whatever's slowest — likely Rider and Item.
5. **Stage 3** only if Stage 2 doesn't cover it.

---

## Index audit (do regardless)

Before rolling anything up, verify these indexes exist (`add_rts_analytics_performance_indexes` migration should cover most):

- `pancake_orders(workspace_id, status, delivered_at)`
- `pancake_orders(workspace_id, status, returning_at)`
- `pancake_orders(workspace_id, page_id)` — ✅ exists
- `pancake_orders(workspace_id, shop_id)` — ✅ exists
- `pancake_order_items(order_id, name)`
- `shipping_addresses(order_id)` — for the LEFT JOIN
- `parcel_journeys(order_id, status, id)` — for the rider subquery's `MAX(id)` lookup

If any are missing, the live queries pay a heavy cost regardless of whether we roll up.

---

## Quick-reference difficulty

- **Free win** — Restore the cache TTL (Stage 4)
- **Easy** — Schemaless bucket table for the 4 low-cardinality cards (Stage 1)
- **Medium** — 3 sibling tables for medium-cardinality entity dimensions (Stage 2)
- **Hard** — Sibling tables for Item and Location (Stage 3) — only if needed
- **Complication** — Rider rollup needs to replicate the "latest On Delivery parcel_journey per order" subquery in the builder
