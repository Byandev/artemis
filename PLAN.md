# Artemis — Product Plan

> Positioning: The analytics platform for Filipino Facebook sellers.
> Core principle: Every number shown must translate to pesos. Data → Money → Action.

---

## Priority Order

| # | Feature | Impact | Effort | Status |
|---|---------|--------|--------|--------|
| 1 | Peso Loss Dashboard | Very High | Low | [ ] |
| 2 | Weekly Business Digest | Very High | Medium | [ ] |
| 3 | RFM Customer Segmentation | High | Medium | [ ] |
| 4 | COD Risk Scoring (solo) | Very High | Medium | [ ] |
| 5 | Product Intelligence | High | Low | [ ] |
| 6 | Courier + City Intelligence | High | Medium | [ ] |
| 7 | Business Health Score | Medium | Medium | [ ] |
| 8 | Shared COD Blacklist (network) | Very High | High | [ ] |

---

## 1. Peso Loss Dashboard

**Goal:** The first thing a seller sees when they log in is how much money they are losing — in pesos, not percentages.

**Why:** Sellers think in pesos. A percentage is ignorable. A peso amount is not.

### Cards to show
- **Lost to Returns** — total peso value of RTS orders this month
- **At-Risk Revenue** — combined order value of customers who haven't reordered in 45+ days
- **Recovery Opportunity** — projected savings if RTS drops by 10% (based on current average order value)
- **Repeat Revenue** — total revenue from repeat customers vs. one-time buyers

### Implementation
- [ ] Add `peso_value` to order sync — store confirmed order amount on `pancake_orders`
- [ ] `PesoLossService` — computes RTS loss, at-risk revenue, and recovery opportunity per workspace and date range
- [ ] New analytics cards on the main dashboard using the existing card component pattern
- [ ] Peso formatting helper (₱ symbol, comma-separated, no decimals for large amounts)
- [ ] Default to current month; support date range filter

### Display rules
- Show month-over-month change (up/down arrow + % delta)
- Red for losses, green for gains
- Tooltip explaining how each number is calculated in plain language

---

## 2. Weekly Business Digest

**Goal:** Deliver a plain-language summary of the seller's week automatically — so they get value without logging in.

**Why:** Non-technical sellers don't open dashboards daily. Bring the insight to them.

### Digest content
- Total orders and total revenue for the week
- Pesos lost to returns
- Number of at-risk customers (and their combined historical value)
- Best performing city
- Worst performing rider (highest RTS)
- One recommended action (the single most impactful thing to do this week)

### Channels
- Email (primary)
- Viber message via Infotxt (for workspaces with Infotxt configured) — sent to workspace owner

### Implementation
- [ ] `WeeklyDigestJob` — scheduled every Monday 8:00 AM PH time
- [ ] `DigestDataBuilder` — aggregates the past 7 days of data per workspace
- [ ] `DigestRecommendationEngine` — picks the single most impactful action based on that week's numbers (e.g., if RTS spiked, recommend reviewing top RTS city; if retention dropped, recommend messaging at-risk customers)
- [ ] Email template — clean, readable on mobile, no charts (just numbers and one sentence each)
- [ ] Viber template — shorter version, 5 bullet points max
- [ ] Workspace setting to enable/disable digest and choose channel
- [ ] Preview digest button in settings so owner can see what it looks like before enabling

---

## 3. RFM Customer Segmentation

**Goal:** Automatically group customers into segments based on buying behavior and tell the seller exactly who to pay attention to.

**Why:** Sellers don't know who their best customers are or who is about to leave. RFM makes this visible without any manual work.

### Segments
| Segment | Definition | Action |
|---------|-----------|--------|
| Champions | Ordered in last 30 days, 3+ orders, high spend | Reward, upsell |
| Loyal | Regular buyers, not recent | Re-engage soon |
| At Risk | Were frequent, gone quiet 45+ days | Message now |
| One-Time Buyers | Ordered once, never returned | Win-back campaign |
| Lost | No order in 90+ days | Low priority |

### RFM Scoring
- **Recency** — days since last order
- **Frequency** — total number of orders
- **Monetary** — total lifetime spend in pesos

### Implementation
- [ ] `CustomerSegmentService` — computes RFM score per customer per workspace, runs nightly
- [ ] Store segment label on a `customer_segments` table (workspace_id, customer_id, segment, rfm_score, computed_at)
- [ ] Segments page under Analytics — table showing segment, customer count, and total value per segment
- [ ] Drill into each segment to see individual customers (name, phone, last order date, total spend)
- [ ] Export button — downloads customer name + phone as CSV for Pancake broadcast or manual outreach
- [ ] Show segment badge on individual customer profile pages

---

## 4. COD Risk Scoring (Solo Mode)

**Goal:** Before an order ships, flag it as low, medium, or high risk based on signals from that workspace's own order history.

**Why:** Fake orders and ghost COD buyers are the #1 operational pain for PH sellers. Catching one bad order saves the cost of a full round-trip shipment.

### Risk signals (solo — workspace data only)
- Customer has a previous undelivered/returned order in this workspace
- Customer phone number linked to 2+ RTS orders
- City has RTS rate above 40% in the past 30 days
- First-time customer + COD amount above ₱2,000 + far province
- Order placed between 12am–5am (statistically higher fake order rate)

### Risk levels
- 🟢 **Low** — no flags
- 🟡 **Medium** — 1–2 flags, review before shipping
- 🔴 **High** — 3+ flags, consider requiring downpayment or cancelling

### Implementation
- [ ] `CodRiskScorer` — computes risk score and flags per order on sync
- [ ] Store `risk_score` and `risk_flags` (JSON) on `pancake_orders`
- [ ] Risk badge visible on the orders list and order detail page
- [ ] Filter orders by risk level
- [ ] Risk breakdown tooltip — shows which signals triggered the score
- [ ] Workspace-level setting to configure thresholds (e.g., adjust the COD amount cutoff)

---

## 5. Product Intelligence

**Goal:** For every product, show three numbers: RTS rate, repeat purchase rate, and revenue per customer. Surface the best and worst performers automatically.

**Why:** Sellers with many SKUs have no idea which products are actually profitable. This tells them in seconds.

### Metrics per product
- **RTS Rate** — % of orders containing this product that were returned
- **Repeat Rate** — % of customers who bought this product and ordered again
- **Revenue per Customer** — average total spend of customers who bought this product
- **Total Revenue** — raw revenue from this product in the selected period

### Views
- Product table — sortable by any metric
- Hero Products — high repeat rate + low RTS (highlight these)
- Problem Products — high RTS + low repeat (flag these)

### Implementation
- [ ] `ProductIntelligenceQuery` — joins `pancake_orders`, `pancake_order_items`, and `pancake_customers` to compute metrics per product per workspace
- [ ] New page under Analytics: Products
- [ ] Color-coded RTS rate column (green < 15%, yellow 15–30%, red > 30%)
- [ ] Hero and Problem product callout cards at the top of the page
- [ ] Date range filter

---

## 6. Courier + City Intelligence

**Goal:** Show which couriers and cities have the best and worst delivery performance so sellers can make logistics decisions based on data.

**Why:** Sellers argue about couriers based on feelings. Give them the numbers.

### Courier breakdown
- Delivery rate per courier
- Average delivery attempts per courier
- RTS rate per courier
- Side-by-side comparison if multiple couriers are used

### City breakdown (enhanced)
- Delivery rate per city
- RTS rate per city
- Average COD amount per city
- Flag cities where RTS > 40% (recommend downpayment or cash terms)

### Implementation
- [ ] Extract courier name from parcel tracking data on order sync — store on `pancake_orders`
- [ ] `CourierIntelligenceQuery` — aggregates delivery outcomes grouped by courier
- [ ] `CityIntelligenceQuery` — already partially exists via RTS city breakdown; extend with delivery rate and COD average
- [ ] Courier comparison table on the RTS Analytics page
- [ ] City risk flag — badge on city rows where RTS exceeds threshold
- [ ] Recommendation callout: "Consider requiring downpayment for orders to [city]"

---

## 7. Business Health Score

**Goal:** A single number (0–100) that tells a seller how healthy their business is, broken into four components.

**Why:** One number is memorable and actionable. Sellers can track it week over week and understand what's moving it.

### Score components (25 points each)
- **Delivery Rate** — % of orders successfully delivered
- **Retention Rate** — 30-day retention cohort rate
- **Repeat Purchase Rate** — % of customers with 2+ orders
- **Revenue Trend** — week-over-week or month-over-month revenue direction

### Display
- Large score at the top of the dashboard
- Four sub-scores with labels and brief plain-language explanations
- Week-over-week change (up/down)
- "What's dragging your score" — surfaces the lowest component with one suggested action

### Implementation
- [ ] `HealthScoreService` — computes component scores and overall score per workspace
- [ ] Score stored and historized on `workspace_health_scores` table (workspace_id, score, components JSON, computed_at)
- [ ] Health score widget on the main dashboard
- [ ] Score history chart — last 12 weeks
- [ ] Score breakdown modal with plain-language explanation per component

---

## 8. Shared COD Blacklist (Network Mode)

**Goal:** A crowd-sourced database of known bad COD buyers, built from RTS and failed delivery data across all Artemis workspaces.

**Why:** A buyer who fakes orders with one seller likely does it with others. Shared data creates a network effect no single seller can replicate alone.

### How it works
- When an order is marked as RTS or failed delivery, the customer's phone number is flagged
- If the same phone number appears in 2+ workspaces as a failed delivery, it enters the shared blacklist
- All workspaces are checked against this blacklist on order sync
- COD risk score is elevated automatically for blacklisted numbers

### Privacy and trust
- No personal names or addresses shared — phone number hash only
- Workspace cannot see which other workspace flagged a number
- Seller can dispute a flag if they believe it is incorrect
- Numbers are automatically removed from the blacklist if they have 3+ successful deliveries after being flagged

### Implementation
- [ ] `GlobalCodBlacklist` table — phone_hash, flag_count, workspace_count, last_flagged_at, status
- [ ] Blacklist contribution job — runs after order sync, hashes and submits flagged numbers
- [ ] Blacklist check integrated into `CodRiskScorer` as an additional high-weight signal
- [ ] Admin panel to review and manage disputed entries
- [ ] Workspace setting to opt in/out of contributing to the shared blacklist
- [ ] Counter on the dashboard: "Protected by X flagged numbers across the Artemis network"

---

## Pricing Model

| Tier | Price | Includes |
|------|-------|---------|
| Basic | ₱799/mo | Core analytics, RTS breakdown, retention, product intelligence |
| Growth | ₱1,499/mo | Basic + RFM segmentation, peso loss dashboard, weekly digest, city/courier intelligence |
| Pro | ₱2,499/mo | Growth + COD risk scoring, business health score, shared blacklist network |

Annual plans at 2 months free.

---

## North Star Metric

**Weekly Active Workspaces** — sellers who log in or receive and open the digest at least once per week.

A seller who sees their peso loss number weekly is a seller who renews.
