# Artemis

> **Hunt down RTS. Protect your margin.**
> The 1st analytics & automation platform for Philippine COD e-commerce.

Artemis is a purpose-built platform for Filipino Cash-on-Delivery (COD) sellers running on **Pancake POS**. It targets one number above all others: **RTS (Return-to-Sender) rate** — the silent margin killer bleeding 20–40% off most PH COD businesses every month.

Connect a Pancake page, and within minutes you see your real RTS rate, your monthly bleed, and the exact pages, shops, and cities where the losses come from. Turn on Parcel Journey notifications and watch the number drop.

---

## The Problem Artemis Solves

For Filipino COD sellers, **every failed parcel is pure loss** — shipping paid twice, packaging wasted, inventory tied up, no sale to show for it.

| Metric | Reality |
|---|---|
| **Typical RTS rate** | 20–40% across most PH COD sellers. *Marami hindi alam ang totoong number nila kasi walang proper tool.* |
| **Cost per failed parcel** | ₱150–₱250 in unrealized profit (both-way shipping, packaging, handling, inventory time) |
| **Monthly bleed · mid seller** | ₱50k+ — that's 1,000 orders × 30% RTS × ₱175. Half a year of rent, gone every month. |

**Artemis exists to move that number.**

---

## Value Proposition (from the hero)

- **Avg RTS rate before Artemis:** 30%
- **Typical reduction within 90 days:** ~10% (relative)
- **Avg profit recovered / year for a mid seller:** ₱120k+
- **Onboarding to first insight:** 2 minutes

No credit card. Connect 1 Pancake page. 14-day free trial.

---

## Core Features (as marketed on the welcome page)

### 1. Sales Analytics
Track revenue, orders, AOV, and repeat purchase rate across all your pages and shops — in one unified view.

### 2. Delivery Analytics
Monitor delivery outcomes, attempt counts, and customer RTS scores in real time. **Spot risky buyers before you ship.**

### 3. RTS Analytics *(the flagship)*
Deep-dive into return-to-sender rates by page, shop, user, and city heatmap. Find the exact source of your losses — not just "RTS is high," but *which page*, *which product*, *which barangay*, *which rider*.

### 4. Parcel Journey
Per-order timeline from confirmation to final delivery. Every status, every rider, every customer notification logged. SMS + chatbot notifications keep customers informed so they're home when the parcel arrives.

### 5. Operations Insights
Fulfillment lead times — confirmed to shipped, shipped to delivered, and every bottleneck in between.

### 6. Role-based Access
Multi-workspace support with team permissions and granular access control. Your CS team sees only what they need.

---

## How It Works

| Step | Action | What happens |
|---|---|---|
| **01 · SIGN UP** | Create your workspace | Business name + a few basics. No credit card, no lengthy forms. |
| **02 · CONNECT** | Connect Pancake | Link your Pancake POS page — we pull orders, customers, and delivery data automatically. |
| **03 · SEE** | Your first insight | Within minutes: real RTS rate, lost profit, highest-risk zones. |
| **04 · ACT** | Move the number | Turn on notifications, follow recommendations, watch RTS drop month over month. |

---

## Real Results (from actual Pancake sellers using Artemis)

> Brand names anonymized. Data sourced from live Pancake POS pages, tracked across months — not cherry-picked.

| Brand | Product | Parcel volume | Before | After | Change | Period |
|---|---|---|---|---|---|---|
| Health & Wellness Brand | Herbal spray | 10M+ | 17.27% | 14.35% | **−17%** | 5 months |
| Care Brand | Inhaler | 9M+ | 32.95% | 17.21% | **−48%** | 3 months |
| Herbal Brand | Capsule | 4.5M+ | 14.12% | 9.72% | **−31%** | 8 months |
| Pain Relief Brand | Topical cream | 8.4M+ | 18.29% | 12.74% | **−30%** | 8 months |

The common pattern: enabling **Parcel Journey** (SMS + chat notifications) is the single biggest lever.

---

## The Free Trial Offer

**₱0 · 14 days · No credit card**

- Connect 1 Pancake page
- 1 month of order & delivery data
- Parcel Journey tracking via chat
- Chat support

Setup takes under 2 minutes.

---

## Positioning & Audience

- **Geography:** Philippines (COD is the dominant payment model)
- **Channel:** Pancake POS sellers (the platform Artemis syncs from)
- **Persona:** COD e-commerce operators who know their RTS is hurting them but can't see the real number
- **Language:** Taglish-comfortable — the marketing copy mixes English with Filipino idioms ("Marami hindi alam ang totoong number nila")
- **Compliance:** Data Privacy Act (RA 10173) compliant, encrypted at rest, no data sharing

---

## Brand

- **Name:** Artemis
- **Logo:** `/img/logo/artemis.png`
- **Palette:** `brand-500` is a green (`rgba(16,211,161)` / teal-emerald). The hero uses a radial green glow on dark background, mono/uppercase small caps for taglines ("NOW HUNTING · BUILT FOR PH COD SELLERS").
- **Voice:** Direct, numeric, hunter-themed ("Hunt down RTS," "Now hunting"). Not corporate — built for operators who want the number to move.
- **Primary CTA:** "Get started — it's free" / "Start free"
- **Secondary CTA:** "Calculate your RTS bleed" → `/rts-calculator`

---

## Marketing-Facing Routes

| Path | Purpose |
|---|---|
| `/` | Welcome / landing page (`resources/js/pages/welcome.tsx`) |
| `/rts-calculator` | Interactive RTS bleed calculator — a hero lead magnet |
| `/about` · `/blog` · `/contact` | Company pages |
| `/privacy` · `/terms` · `/data-policy` · `/security` | Legal & compliance |
| `/register` · `/login` | Account entry |

The landing page is structured: **Hero → Problem → Features → Free Trial → How It Works → Real Results → FAQ → Final CTA → Footer**.

---

## FAQ Highlights (from the welcome page)

- **How does Artemis get my data?** — Direct Pancake POS integration. No manual uploads.
- **How much does RTS drop?** — ~10% relative reduction in 90 days on average; varies by product, audience, courier.
- **What if I don't know my profit margin?** — Onboarding auto-computes from sales data, or uses industry averages by product category.
- **Do I need anything besides Pancake?** — No. Pancake is all we need for COD sellers.
- **Is customer data safe?** — DPA (RA 10173) compliant, encrypted at rest, never shared.
- **What's in the free trial?** — 14 days, 1 Pancake page, 1 month of data, Parcel Journey tracking, chat support. No credit card.

---

## Under the Hood (context, not marketing)

The platform is a Laravel 12 + Inertia.js 2 + React 19 multi-tenant app. Everything is scoped to a **Workspace**; Pancake integration pulls orders, shops, customers, and daily reports; heavy RTS aggregations live in `app/Queries/Rts*Query.php` under `app/Http/Controllers/Workspaces/RTS/`. Parcel Journey notifications are template-driven (`ParcelJourneyNotificationTemplate`) and fire via queued jobs.

Beyond RTS, the codebase also hosts Ads Manager (Facebook), Botcake (chatbot flows), Inventory, Finance, and CSR performance modules — but **RTS is the anchor** the marketing is built on.
