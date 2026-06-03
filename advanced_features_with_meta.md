# Advanced Meta Ads Features — Build Plan

A sequenced feature roadmap for integrating Meta ads analysis and automation into an existing DTC e-commerce operations platform.

## Context

This roadmap is designed for a platform that already has:
- Pages, Shops, Products, RTS, RMO modules
- CSR, Riders, Teams, Roles
- Finance (cashflow, remittances, transactions)
- Inventory (items, transactions, courier shipments)
- Ads Manager module (Facebook integration, campaigns, ad sets, ads, optimization rules)
- AI "Ask Data" foundation via OpenAI

The unique edge: ownership of order, delivery, RTS, COGS, and CSR data — none of which global ads tools (Superads, Triple Whale, Motion) can access.

---

## Data Source Strategy

| Use case | Data source |
|---|---|
| Dashboards, reports, drilldowns | **Sync API** |
| Joining ads with orders/RTS/finance | **Sync API** (must be in your DB) |
| Rule evaluation (read) | **Sync API** |
| Rule execution (write actions) | **Direct API** |
| Comments moderation, real-time fetches | **Direct API** |
| Chat questions, ad-hoc queries | **MCP** (with Sync API as context) |
| AI suggestions, narratives, explanations | **MCP/AI** |
| Vision/AI analysis on creatives | **MCP/AI** |
| ML predictions | Your DB + ML model |

**Rule of thumb:** MCP for thinking. API for doing. Sync for joining.

---

## Build Sequence

### Phase 0 — Foundation

| # | Feature | Description | Data Source | Priority |
|---|---|---|---|---|
| 1 | Complete Ads Data Sync | Finish syncing campaigns, ad sets, ads, daily insights, creatives, and budget snapshots into your DB | Sync API | Critical |
| 2 | Daily Budget Snapshot Job | Capture ad set budgets daily so you have history Meta doesn't keep | Sync API | Critical |
| 3 | Sync Health Monitoring | Dashboard showing sync status, last successful pull, error logs per ad account | Sync API | Critical |
| 4 | Ad-to-Order Mapping | Link Meta ad/campaign data to your orders table via UTM, ref codes, or post-purchase attribution | Sync API + your orders | Critical |
| 5 | Ad Creator Attribution | Map Facebook user IDs (ad `created_by`) to your platform members so each ad is attributed to a specific advertiser | Sync API + your members | Critical |
| 6 | Daily Ad Creation Monitor | Track if each advertiser created ads today (Mon–Sat, excluding Sunday). Flags missed days per advertiser. | Sync API + your members + scheduled job | Critical |
| 7 | Missed Deliverable Alerts | Notify owner/manager when an advertiser hasn't shipped expected ads by daily cutoff (skips Sundays) | Sync API + scheduled job + notification system | High |

### Phase 1 — Core Reporting

| # | Feature | Description | Data Source | Priority |
|---|---|---|---|---|
| 8 | Daily Performance Summary | One-screen overview of today's spend, conversions, ROAS across accounts | Sync API | High |
| 9 | Campaign/Ad Set/Ad Drilldown | Hierarchical navigation from campaigns down to individual ads | Sync API | High |
| 10 | Date Range Comparisons | Compare any two periods side-by-side | Sync API | High |
| 11 | Daily Budget Tracker | All ad sets with budgets, spend, and pacing in one view | Sync API | High |
| 12 | Budget Pacing Alerts | "Will hit budget by 2pm" or "underspending" notifications | Sync API + scheduled job | High |
| 13 | Export to CSV/PDF | Download any view for offline analysis | Sync API | Medium |

### Phase 2 — Automation & Rules

| # | Feature | Description | Data Source | Priority |
|---|---|---|---|---|
| 14 | Dynamic Rule Builder | Block-based editor for conditions, actions, constraints (using Meta-native metrics: spend, ROAS, CTR, CPA, frequency) | Sync API (read) + Direct API (write) | Critical |
| 15 | Rule Templates | Pre-built starter rules (creative fatigue, scale winners, pause underperformers) | Sync API + Direct API | High |
| 16 | Dry-Run Simulation | Test what a rule would do without activating | Sync API | High |
| 17 | Rule Audit Log | Every execution logged with before/after state | Direct API + your DB | Critical |
| 18 | Schedule-Based Actions | Pause overnight, scale on paydays, etc. | Sync API + Direct API | Medium |
| 19 | Bid Adjustment Rules | Auto-adjust bids based on performance | Direct API | Medium |
| 20 | Rule Conflict Detection | Flag rules that contradict each other | Sync API | Medium |
| 21 | Rule Rollback | Undo last action within a time window | Direct API | Medium |
| 22 | Budget Reallocation Suggestions | "Move ₱X from Ad Set A to B" | MCP/AI + Sync API | Medium |

> **Note:** RTS-tied and true-profit-tied rules are deferred to Phase 3, after delivered ROAS and RTS metrics become available. Phase 2 rules use Meta-native metrics only.

### Phase 3 — True Profit & RTS Edge

| # | Feature | Description | Data Source | Priority |
|---|---|---|---|---|
| 23 | True Profit Per Ad | Spend vs delivered revenue minus COGS, actual peso profit | Sync API + orders/finance | Critical |
| 24 | Delivered ROAS | ROAS based on delivered orders, not Meta-reported | Sync API + RTS data | Critical |
| 25 | RTS Rate Per Ad | Return-to-sender percentage for each ad's orders | Sync API + RTS data | Critical |
| 26 | RTS Cost Tracker | Total peso cost of RTS losses per ad/campaign | Sync API + finance | High |
| 27 | True CAC Per Delivered Order | CAC based on actual fulfilled orders | Sync API | High |
| 28 | Margin-Aware ROAS | ROAS weighted by product margin | Sync API + finance | High |
| 29 | Break-Even ROAS Calculator | Per-product break-even threshold | Sync API | Medium |
| 30 | Profit Per Product Per Ad | Which ads drive profit for which products | Sync API | High |
| 31 | RTS-Tied Auto-Pause | Pause ads when RTS rate spikes or profit goes negative (extends Phase 2 rule builder) | Sync API + Direct API | High |

### Phase 4 — Intelligence & Insights

| # | Feature | Description | Data Source | Priority |
|---|---|---|---|---|
| 32 | AI Chat (Ask Anything) | Natural language queries on ads + commerce data | MCP + Sync API | Critical |
| 33 | Auto-Generated Daily Insights | AI-written summary of what changed and why | MCP/AI + Sync API | High |
| 34 | Manual Creative Tagging | Users tag ads (UGC, demo, sale, etc.) for grouping | Your DB | High |
| 35 | Performance by Creative Tag | ROI breakdown by hook type, format, angle | Sync API | High |
| 36 | Creative Fatigue Detection | Alert when CTR drops or frequency too high | Sync API + scheduled job | High |
| 37 | Creative Library | Visual gallery of all ads with thumbnails and metrics | Sync API | High |
| 38 | AI Auto-Tagging | Vision model auto-assigns tags to creatives | MCP/AI + your DB | Medium |
| 39 | Side-by-Side Creative Compare | Pick 2–4 ads, compare metrics in one view | Sync API | Medium |
| 40 | AI Rule Suggestions | "I notice X pattern — want a rule for that?" | MCP/AI + Sync API | Medium |
| 41 | Spend Anomaly Alerts | "You spent 3× normal yesterday" | Sync API + scheduled job | High |
| 42 | Top Performer Celebration | "Your best ad just hit X delivered orders!" | Sync API | Low |
| 43 | Advertiser Performance Dashboard | Per-advertiser view: ads created, delivered ROAS of their ads, RTS rate of their ads | Sync API + your DB | High |
| 44 | Advertiser Leaderboard | Rank advertisers by output and ad performance | Sync API + your DB | Medium |

### Phase 5 — Advanced & Differentiation

| # | Feature | Description | Data Source | Priority |
|---|---|---|---|---|
| 45 | RTS by Geography Heatmap | Province/city RTS rates joined with ads | Sync API + RMO | High |
| 46 | Bad Buyer Ad Detection | Flag ads attracting high-RTS audiences | Sync API | High |
| 47 | RTS Rate by Creative Type | Which creatives attract higher-quality buyers | Sync API | High |
| 48 | Customer LTV by Acquisition Ad | Which ads bring high-LTV customers | Sync API + orders | High |
| 49 | Repeat Purchase Rate by Ad | Which ads bring repeat customers | Sync API + orders | High |
| 50 | CSR Confirmation Rate by Ad | Which ads generate orders CSRs handle best | Sync API + CSR module | Medium |
| 51 | Audience Performance Ranking | Best-performing audiences by delivered profit | Sync API | Medium |
| 52 | Lookalike Performance Compare | Compare 1%, 2%, 3% lookalikes | Sync API | Medium |
| 53 | Ad-to-Product Mapping | Which products each ad sells | Sync API + orders | Medium |
| 54 | Cross-Sell Discovery | Ad drives sales of other products too | Sync API + orders | Medium |
| 55 | Creative Brief Generator | Auto-generate briefs from winning patterns | MCP/AI + Sync API | Medium |
| 56 | AI Ad Copy Variations | Generate copy from your winners | MCP/AI | Medium |
| 57 | Hook Suggestions | Ideas based on top performers | MCP/AI + Sync API | Medium |
| 58 | Spend Forecasting | Project end-of-month spend | Sync API | Medium |
| 59 | Revenue Forecasting | Project revenue trajectory | Sync API + orders | Medium |
| 60 | Inventory Demand Forecast | Predict stock from ad pipeline | Sync API + inventory | Medium |
| 61 | Seasonality Analysis | When does your account typically peak | Sync API | Low |
| 62 | Predictive Performance Scoring | Score new ads before launch | Sync API + ML | Low |
| 63 | RTS Prediction Score | Predict RTS rate before launching ad | Sync API + ML | Low |
| 64 | Anomaly Detection (ML) | Auto-flag unusual patterns | Sync API + ML | Low |
| 65 | Competitor Ad Tracking | Follow brands, see active ads | Direct API (Ad Library) | Low |
| 66 | White-Labeled Reports | Branded PDFs for agency users | Sync API | Low |
| 67 | Multi-Account Rollup | Master view across multiple brands | Sync API | Low |
| 68 | Messenger Bot | Queries and alerts via Facebook Messenger | MCP/AI + your DB | Low |
| 69 | Ad Comment Moderation | Auto-hide negative comments, route to CSR | Direct API + CSR module | Medium |

---

## Why This Sequence

**Phase 0 — Foundation (must finish first)**
Without complete sync and ad-to-order mapping, nothing else works. This is plumbing that's invisible to users but unblocks everything.

**Phase 1 — Core Reporting**
Get users into the product with table-stakes views. Daily budget tracker is here because it's already done manually — a known win.

**Phase 2 — Automation & Rules**
Ship the rule builder early using Meta-native metrics. Users get immediate value from automation while you build the deeper data layer. Rules can be extended with RTS/profit conditions in Phase 3.

**Phase 3 — True Profit & RTS Edge**
This is where the platform becomes differentiated. Once automation is live, layer in the unique commerce-joined metrics that no global tool can match. Existing rules get more powerful conditions (RTS-tied auto-pause, profit-tied scaling).

**Phase 4 — Intelligence & Insights**
AI chat, creative tagging, fatigue detection — adds depth once the foundation is solid.

**Phase 5 — Advanced & Differentiation**
Long-tail features that compound the moat: ML scoring, geographic insights, forecasting, agency features.

---

## Top 7 Highest-Impact Features

If only 7 features ship, these are the ones:

1. True Profit Per Ad (#23)
2. RTS Rate Per Ad (#25)
3. Daily Budget Tracker (#11)
4. Dynamic Rule Builder (#14), extended later with RTS conditions (#31)
5. AI Chat — Ask Anything (#32)
6. Daily Performance Summary (#8)
7. Daily Ad Creation Monitor (#6) — your unique advertiser deliverable feature

---

## Critical Reminders

- Don't move to Phase 1 until Phase 0 is rock solid. Half-synced data poisons every feature on top of it.
- Within each phase, ship **Critical** first, then **High**, then **Medium/Low**.
- The single most important user-facing feature is **#23 True Profit Per Ad**. If only one differentiating feature ships, ship that.
- Keep rules deterministic. AI suggests; deterministic logic executes.
- Always include dry-run, audit logs, and safety constraints on automation features.
