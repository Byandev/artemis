# Meta Ads Entity Monitor — build context & status

Resume doc for the creative-testing monitor. Status: **v1 implemented & verified, not committed.**

- Original approved plan: `~/.claude/plans/glowing-twirling-gadget.md`
- Source spreadsheet that defined the requirements: `~/Downloads/META DIGITRADING - CREATIVE TESTING TRACKER 2026.xlsx - Mark.csv`

## Goal

Replace the manual "CREATIVE TESTING TRACKER" Google Sheet. Answer one question per entity: **is this campaign / ad set / ad winning?** Auto-fill the Day 1–7 spend + ROAS from synced Meta data, suggest a status from configurable rules, record it daily with the reason, and let a person override.

## Locked decisions

- **Unit:** any entity — campaign / ad set / ad — watched over its **first 7 days of spend** (Day 1 = first spend day).
- **ROAS source:** Meta `purchase_value ÷ spend` from `meta_ads_insights`.
- **Three fixed statuses:** Scaling / Maintain / Killed (+ derived `too_early` < 3 days of spend, `unmatched` if no rule passes).
- **Dynamic rules:** each status = configurable multi-condition rule (any metric, AND/OR, window = first_3_days / first_7_days / lifetime). Seeded to the sheet's bands (Scaling ROAS≥6, Maintain ≥3.5, Killed <3.5 over first_3_days).
- **Auto-suggest, human confirms:** per-entity override (+ note).
- **Daily monitoring + "why":** scheduled job records each in-test entity's status + reason snapshot per day.
- **v1 = monitoring only.** No billing/charging split, no Item/Phase columns, no Meta writes (no auto-pause).

## Evaluation logic

Priority Scaling → Maintain → Killed; first rule whose conditions pass wins. `< 3` days of spend → `too_early`. Reason = per-condition `{metric, window, operator, threshold, actual_value, passed}`.

## File map

**Migrations** (`Modules/MetaAds/database/migrations/2026_06_25_*`):
- `..._create_meta_ads_monitor_status_rules_table` — workspace_id, status, condition_operator, is_active; unique(workspace_id,status).
- `..._create_meta_ads_monitor_conditions_table` — rule_id (FK `maomc_rule_id_foreign`), metric, operator, value, window.
- `..._create_meta_ads_monitor_evaluations_table` — daily history: workspace_id, level, entity_id, date, metrics(json), suggested_status, reason(json), evaluated_at; unique per entity/day.
- `..._create_meta_ads_entity_status_overrides_table` — workspace_id, level, entity_id, final_status (nullable), notes, decided_by/at; unique per entity.

**Models** (`Modules/MetaAds/app/Models/`): `MonitorStatusRule` (has `STATUSES`, `ensureDefaultsFor()`, hasMany conditions), `MonitorCondition`, `MonitorEvaluation`, `EntityStatusOverride`.

**Services** (`Modules/MetaAds/app/Services/`):
- `MonitorStatusEvaluator` — pure: `evaluate($windowSums, $rules, $daysWithSpend)` → `{status, reason}`; `metricValue()` (roas/cpa/ctr/cpm/… + raw cols).
- `EntityMonitorService` — `buildRows($workspace, $level, $accountIds, $from, $to, $rules)`. Resolves first-spend-day, **sums insights to entity+date grain** (insights are per-ad!), builds Day 1–7 series + window sums, calls the evaluator. `TEST_DAYS = 7`.

**Controller** `Modules/MetaAds/app/Http/Controllers/EntityMonitorController.php`: `index` (shell + accounts + rules + options), `data` (JSON rows + overrides + trail), `setStatus` (upsert/clear override), `updateRules` (save the 3 rules + conditions). Team-scoped via `AdAccount::forWorkspace()->visibleTo()`.

**Command** `Modules/MetaAds/app/Console/Commands/EvaluateEntityMonitorCommand.php` (`meta-ads:evaluate-entity-monitor`) — daily upsert of `meta_ads_monitor_evaluations` for entities started in the last 7 days. Registered in `MetaAdsServiceProvider::$commands`; scheduled in `routes/console.php` `dailyAt('01:00')`.

**Routes** `routes/workspaces.php` (Meta section): `entity-monitor` index / `/data` / `/status` / `/rules`, names `workspaces.metaads.entity-monitor.*`, gated `can:View Meta Ads,workspace`.

**Frontend** `resources/js/pages/workspaces/integrations/meta-ads/entity-monitor.tsx` — level switch, started-date presets (7/30/90d), status filter + search, summary counts, Day 1–7 grid, status badge, expandable why + daily trail, override popover, and the Status-rules config dialog. Nav item added in `resources/js/components/app-sidebar.tsx` (Trophy icon).

## How to use / verify

- Page: `/workspaces/{slug}/integrations/meta/entity-monitor` (sidebar → Meta Ads → Entity Monitor).
- Default rules are seeded lazily on first visit (`MonitorStatusRule::ensureDefaultsFor`).
- Daily job: `php artisan meta-ads:evaluate-entity-monitor` (only writes for entities within their first 7 days).
- Smoke-tested against Workspace 1 (1,417 insights): 46 campaigns / 83 ad sets / 280 ads; day windows capped at 7; e.g. `SPIR | 040126R04` → Killed (ROAS 1.12 < 3.5 over first 3 days).
- Checks pass: `php artisan migrate` (up/down clean), Pint, and the page's eslint/tsc.

## Notes / caveats

- **Daily trail starts empty** for existing campaigns (already past their 7-day window); it accrues for new tests going forward. The board itself shows everything live.
- **Fixed during build:** insights are stored per-ad, so campaign/ad-set rows double-counted days until summed to entity+date grain in `EntityMonitorService`.
- `npm run lint` reports two `app-sidebar.tsx` unused-var errors — **pre-existing** on the committed version, not from this work.
- Write endpoints (status/rules) are gated under `can:View Meta Ads` for v1 — tighten to a dedicated permission later if needed.

## Possible next steps (v2)

- Billing/charging layer (Company % / Intern % / Charge To / Finance) from the sheet.
- Extend monitoring past day 7 for "scaled" entities.
- Show the recorded per-day status inside the Day 1–7 grid cells.
- Dedicated `Manage Entity Monitor` permission + role assignment.
- Pest tests for `MonitorStatusEvaluator` (pure) and the `data`/`setStatus`/`updateRules` endpoints.
- Optional: pause "killed" entities on Meta (would move beyond monitoring-only).
