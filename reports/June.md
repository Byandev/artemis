# Shipped — June 2026

**18 releases** shipped this month, **v3.7.0 (Jun 3) → v3.14.2 (Jun 30)**.
Source: in-app changelog (`resources/js/pages/workspaces/changelog.tsx`), cross-checked against git history.

## Releases

| Version | Date | What shipped |
|---|---|---|
| **v3.14.2** | Jun 30 | ERP transaction-history + PO syncs now run 3×/day; bigger batches to dodge rate limits; Meta Ads insights refresh every 6h |
| **v3.14.1** | Jun 29 | **Gencys Sync Health page** (per-item sync status + 24h summary); ERP stock column gated to partner workspaces; **Creatives filter panel**; guided CSV transaction import; password-change & public-page-password activity logging |
| **v3.14.0** | Jun 29 | **Shop-first model** — connect a Shop (not pages one at a time); plan limits now count shops; teams own shops; page form simplified; parcel-journey breakdown per shop |
| **v3.13.3** | Jun 28 | Pancake order-refresh maintenance command (configurable backfill window) |
| **v3.13.2** | Jun 28 | Fix: removed debug limit that only synced a handful of test orders |
| **v3.13.1** | Jun 28 | Fix: shop "Last Sync" resets on manual re-pull (no stale timestamp) |
| **v3.13.0** | Jun 28 | **Webcake orders captured** (sync per-shop, records order source); shop "Refresh orders" + "Last Sync"; dashboard **Order Source filter**; RTS "By Order Source" breakdown |
| **v3.12.0** | Jun 26 | **Gencys ERP integration** (Daily Sales Tracker + Unit Codes); **Meta Ads Report Builder**; **Activity Logs audit trail**; ERP credentials settings; **PO delivery monitoring**; inventory ERP sync; unique creative names |
| **v3.11.0** | Jun 18 | **Team-level data access** (scope records to teams via "View All Workspace Data" perm); per-team page/ad-account assignment; **"Viewing as Team" switcher** |
| **v3.10.2** | Jun 13 | Meta Ads — per-rule optimization scheduling + "Run now" |
| **v3.10.1** | Jun 11 | Meta Ads — instant backfill on connect; dedicated sync workers |
| **v3.10.0** | Jun 11 | **Meta Ads module** (OAuth, unified Ads Manager, optimization rules + approval workflow, sync health, 8 permissions); public pages can be password-protected |
| **v3.9.1** | Jun 10 | Pages **Excel export/import**; creative submission-status flags (on-time/early/late) |
| **v3.9.0** | Jun 9 | **Creative Tracker module**; **Video Editor Dashboard**; Sales & Marketing dashboard scaffold; RTS filter persistence + AI-chat permission gate; 8 bug fixes |
| **v3.8.1** | Jun 9 | Removed RTS Rate column from Parcel Journey page stats |
| **v3.8.0** | Jun 9 | Parcel-journey duplicate-notification fix; per-order customer sync; outgoing-API logging; faster re-sync window |
| **v3.7.1** | Jun 3 | Sidebar cleanup (Admin + Customer Support hidden) |
| **v3.7.0** | Jun 3 | **Team Member Schedule**; **CSR Dashboard & RMO Management**; page daily budget records; Public API v2 (call-logs, rmo-orders); archived roles |

## Major themes this month

- **Meta Ads** — brand-new module replacing the old Ads Manager: OAuth sync, unified Ads Manager, optimization rules with an approval workflow, a saved Report Builder, and scheduling (v3.10.0 → v3.14.2).
- **Gencys ERP** — new integration: Daily Sales Tracker, Unit Codes, automated n8n sync, and a Sync Health page (v3.12.0 → v3.14.2).
- **Shop-first model** — orders, plan limits, and team ownership moved from per-page to per-shop, which also unlocked Webcake order capture (v3.13.0 → v3.14.0).
- **Team-level data access** — records scopeable to teams, with a "Viewing as Team" switcher (v3.11.0).
- **Creative Tracker + Video Editor Dashboard** — full creative production pipeline and a dedicated dashboard (v3.9.0 → v3.14.1).
- **Activity Logs** — workspace + cross-workspace audit trail (v3.12.0).

## Shipped in code but NOT in the changelog

These merged in June but have no (or only a thin) changelog entry:

| Feature | Merged | Status |
|---|---|---|
| Bulk assignment for RMO orders | June | Missing |
| Extend status update to yesterday's delivered orders | Jun 11 | Missing |
| CSR Leaderboard redesign | Jun 10 | Only thinly noted in v3.10.0 ("Public Leaderboard… refreshed") |

> Note: the bulk-assign code is dated May 30 but its branch merged in June, which is why it falls between months.
