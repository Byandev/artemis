# Per-User & Per-Product Income Statement — Plan

_Last updated: 2026-08-07_

## Context

Artemis runs a monthly **Income Statement (IS)** for gencys-partner workspaces
(`Modules/Finance`). It has three levels, and data flows **top → user → product**
(the top level is the source of truth; the others are drill-downs that explain it):

1. **Top-level (workspace) statement** — `IncomeStatementController` +
   `IncomeStatement`. Built from all delivered gencys orders (revenue) + all
   finance transactions (cost of sales / OPEX, classified by each transaction
   type's `income_statement_section`). This is what gets **saved**.
2. **Per-user breakdown** — `UserIncomeStatementService` + `UserIncomeStatementController`.
   Revenue attributed to interns via `intern_brands_name` → intern → user;
   transactions split by charge-to. Snapshotted per user.
3. **Per-product breakdown** — `userProductRows()` inside a user's statement.
   Orders resolved to products via unit codes; computed live.

**Purpose of the breakdowns:** (1) compute each intern's **commission/payout**,
and (2) judge **product/intern profitability**. Payouts come first, so the
numbers must be exact.

### Already built (recent work)
- Transaction types carry a **nature** (debit/credit) — drives a transaction's
  in/out direction (the manual field was removed).
- Transaction types carry an **income-statement section**: `cost_of_sales`,
  `opex`, or `null` = excluded.
- Removed the old auto-COGS from the per-user P&L; COGS now arrives as a
  transaction.
- **Ad Spent** folded into the per-product breakdown (from ad-spend transaction
  product-shares, apportioned by the intern's charge-to fraction).
- **Advisory (30%)** and **Net Profit** shown per product.
- Per-user top summary: **Gross Profit** and **Advisory Share** rows hidden
  (advisory is shown per product instead); slim summary retained.
- Regenerate now always re-includes cost-of-sales lines.

## Decisions (locked)

| Topic | Decision |
| --- | --- |
| Goal | Intern commission/payout **and** product/intern profitability. Exact numbers required. |
| COGS source | A **"Cost of Goods" finance transaction** tagged to a product (product-shares), bought in **bulk per product**. |
| COGS split | Split among interns who sold the product by their **number of delivered orders** for that product. |
| COGS scope | Show COGS in **both** the per-product table **and** the intern's top-summary Cost of Sales. Attribute it **only** via the order-count split (not charge-to) to avoid double-counting. |
| Commission base | % of a product's **net profit** (after COGS, shipping, COD, VAT, ad spend, and 30% advisory), **positive products only**. |
| Commission rate | **Per intern + product**, managed on a **separate Commission Rates page** (pick one intern at a time). Display-only (does not change net profit). |
| Per-user layout | Keep the **slim top summary** (Delivered → Cost of Sales → OPEX → Net) above the product table. |
| Payout report | **Not now.** |

---

## Phase 1 — COGS bulk-split (next to build)

**Goal:** COGS is sourced from "Cost of Goods" transactions and split by order
count, replacing the per-order `total_cog` in the product breakdown, and shown
in the intern's top-summary Cost of Sales.

Steps:
1. Add a helper `productCogsByProduct()` (mirrors the ad-spend helper) that sums
   each product's "Cost of Goods" transaction shares for the month.
2. Split per intern in `userProductRows()`:
   `product COGS ÷ total delivered orders for the product × this intern's orders`.
   Replace the `total_cog`-based COGS.
3. Add the intern's total COGS (sum of their per-product shares) as a
   **Cost of Sales line** in the per-user top summary (`buildUserStatement`).
4. **Exclude** "Cost of Goods" transaction types from the charge-to transaction
   buckets (`userTransactionBuckets`) so COGS is not double-counted.
5. Verify on real data: per-intern COGS sums to the workspace COGS (minus
   unattributable), and the top summary ties out.

**Identification:** transaction type name matching `%cost of goods%`
(won't catch "Delivery Fee of COGS").

**Reconciliation:** sum of interns' COGS = workspace total COGS **only when**
every COGS product has matching delivered orders. A product with COGS but no
resolving orders lands in the **Discrepancy** column — the signal to fix data.

---

## Phase 2 — Commission (deferred)

**Goal:** compute each intern's commission per product and show it on their
breakdown.

- Commission = `rate × product net profit` when net > 0, else 0. Display-only.
- Rate stored **per (workspace, user, product)** — table `finance_commission_rates`
  (already created), model `CommissionRate` (already created). Backend
  computation already wired into `userProductRows()` (`commission_rate`,
  `commission`), currently dormant.
- **Commission Rates page:** pick one intern, list their products, set a % each.
- Show a **Commission** row (and the intern's total) on the product table.

_Backend scaffolding exists but is inert until the rates page + display are
built._

---

## Later / not scheduled
- Payout report.

## Open items / risks
- Confirm the exact "Cost of Goods" type name(s) per workspace for matching.
- Products with COGS/ad-spend but no delivered orders → Discrepancy column;
  decide if a warning threshold is wanted.
- Per-product advisory (30% of each positive product) can differ from the
  user-level advisory (30% of total gross); documented as by-design divergence.
