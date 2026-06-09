import { home } from '@/routes';
import { Head, Link } from '@inertiajs/react';

interface ChangelogEntry {
    version: string;
    date: string;
    sections: {
        title: string;
        items: string[];
    }[];
}

const changelog: ChangelogEntry[] = [
    {
        version: 'v3.8.0',
        date: '2026-06-09',
        sections: [
            {
                title: 'Parcel Journey — Duplicate Notification Fix',
                items: [
                    'Fixed a bug where multiple parcel journey updates for the same day could each trigger a notification on repeated syncs — notifications are now only sent for journeys newer than the most recently notified one, preventing redundant messages',
                ],
            },
            {
                title: 'Pancake — Customer Sync Per Order',
                items: [
                    'Customer data is now synced directly during order processing via a new SyncCustomerAction — no separate fetch job needed',
                    'Removed the FetchShopCustomers job, TriggerFetchShopCustomers command, and the customers_last_synced_at column from shops — customer records stay fresh automatically on every order sync',
                    'Removed the unique constraint on pancake_customers.customer_id to support customers appearing across multiple shops',
                    'Removed the "Refresh customers" action from the Shops menu — the "Refresh users" action remains',
                ],
            },
            {
                title: 'Outgoing API Logging',
                items: [
                    'New outgoing_api_logs table records every external API call made by the app — service, action, HTTP method, URL, request payload, response status, response body, and duration',
                    'Botcake sendFlow and updateCustomField calls are now logged when PARCEL_JOURNEY_NOTIFICATION_LOGS_ENABLED=true; skipped notifications (when the feature is disabled) are also recorded',
                    'Logging is off by default — set PARCEL_JOURNEY_NOTIFICATION_LOGS_ENABLED=true in .env to enable',
                ],
            },
            {
                title: 'Order Sync — Faster Re-Sync Window',
                items: [
                    'orders_last_synced_at is now set 1 minute behind the sync end time (down from 15 minutes), reducing the gap between syncs and ensuring recent orders are not missed on the next run',
                ],
            },
        ],
    },
    {
        version: 'v3.7.1',
        date: '2026-06-03',
        sections: [
            {
                title: 'Sidebar — Cleanup',
                items: [
                    'Removed the Admin nav group and Customer Support link from the main workspace sidebar — both sections are temporarily hidden while their respective flows are being finalised',
                ],
            },
        ],
    },
    {
        version: 'v3.7.0',
        date: '2026-06-03',
        sections: [
            {
                title: 'Teams — Member Schedule',
                items: [
                    'New Team Member Schedule page — assign and visualize weekly schedules for each team member across all teams in the workspace',
                    'Schedule entries are keyed by team member and day-of-week; bulk assignments and per-member overrides are supported from the same view',
                    'Backed by a new team_member_schedules table (TeamMemberSchedule model) and TeamScheduleController; CSR-level schedules stored separately in csr_schedules',
                    'New manage-schedule permission controls who can view and edit team schedules',
                ],
            },
            {
                title: 'CSR — Dashboard & RMO Management',
                items: [
                    'New CSR Dashboard — dedicated view for CSR agents with performance KPIs, call activity, and order status summary scoped to the logged-in CSR',
                    'New CSR RMO Management page — CSR-scoped delivery order list with assignee filters, status updates, call logging, and daily stats; backed by a new CSR sidebar layout (csr-layout.tsx + csr-sidebar.tsx)',
                    'CSRController introduced to serve CSR-specific data separate from the shared workspace analytics path',
                ],
            },
            {
                title: 'Pages — Daily Budget Records',
                items: [
                    'New Page Daily Budget Records section — log and track daily ad budget entries per page with date, amount, and notes',
                    'Backed by a new page_daily_budget_records table (PageDailyBudgetRecord model) and PageDailyBudgetRecordController; list supports date-range filtering, pagination, and sortable columns',
                    'New update-page-budget permission gates access to creating and editing budget records',
                ],
            },
            {
                title: 'Public API — V2 Endpoints',
                items: [
                    'New /api/v1/public/v2/call-logs endpoint (CallLogV2Controller) — improved call log sync with stricter upsert logic and richer response envelope',
                    'New /api/v1/public/v2/rmo-orders endpoint (RmoOrderV2Controller) — paginated RMO order listing with assignee, status, and date filters for the mobile CSR client',
                ],
            },
            {
                title: 'Roles — Archived Roles',
                items: [
                    'Roles can now be archived instead of deleted — archived roles retain their permissions and member history but are hidden from active role assignment',
                    'New Archived Roles page lists all archived roles with restore and permanent-delete actions; accessible from the Roles index via the Archive tab',
                ],
            },
        ],
    },
    {
        version: 'v3.6.15',
        date: '2026-05-12',
        sections: [
            {
                title: 'Per-Workspace Page Limit Override',
                items: [
                    "Added a workspaces.max_pages column (new migration) that overrides the subscription plan's page_limit on a per-workspace basis — when set it wins, when null the plan limit is used, when both are null pages are unlimited",
                    'Centralised the resolution on the Workspace model — new pageLimit(), pageLimitInfo(), and hasReachedPageLimit() helpers so every creation site reads the same source of truth instead of duplicating the ?? chain',
                    "PageController::store and OnboardingController::store both now throw a ValidationException with a page_limit message when the workspace is at capacity; PageController::create still redirects to index with a flash error so users can't land on the create form when full",
                    'Onboarding page (resources/js/pages/workspaces/onboarding.tsx) now receives pageLimit/pageCount/pageLimitReached and disables the "Connect & Sync Orders" button with a tooltip + small counter ("X/Y pages used"), matching the pattern already on the pages index',
                    'Admin — /admin/workspaces now exposes a max_pages field on the workspace edit form and shows current usage as "count / max_pages" in the listing; AdminWorkspaceController validates max_pages as nullable|integer|min:1',
                ],
            },
        ],
    },
    {
        version: 'v3.6.14',
        date: '2026-05-09',
        sections: [
            {
                title: 'Botcake — Page & Shop Filters on Flows / Sequences',
                items: [
                    'Added the same Filters dropdown the main dashboard uses (Page + Shop) to /workspaces/{slug}/botcake/flows and /workspaces/{slug}/botcake/sequences — narrow the table to specific Pancake pages or shops without leaving the page',
                    'Filter state is persisted in the URL as filter[page_ids] / filter[shop_ids] (comma-separated), so refreshes and shared links preserve the selection. Page resets to 1 when the filter changes',
                    'Server-side: FlowController and SequenceController now whitelist page_ids and shop_ids — page_ids does whereIn on botcake_(flows|sequences).page_id, shop_ids walks the page relation (whereHas page → whereIn shop_id)',
                ],
            },
        ],
    },
    {
        version: 'v3.6.13',
        date: '2026-05-09',
        sections: [
            {
                title: 'Frontend Formatting Sweep',
                items: [
                    'Ran Prettier across the entire frontend (resources/js + resources/css) — 239 files reformatted with no functional changes. Quote-style normalised (single quotes), long lines wrapped, multi-line shadow / arg lists aligned, and a handful of missing spaces after type-annotation colons fixed (e.g. total_rmo_call_attempts:number → : number)',
                    'Refreshed resources/views/design-guidelines.html with the expanded component reference (~3,800 added lines of HTML samples covering the current design system) so internal contributors have an up-to-date visual catalog',
                ],
            },
        ],
    },
    {
        version: 'v3.6.12',
        date: '2026-05-09',
        sections: [
            {
                title: 'Customer Support — Tickets',
                items: [
                    'Added a workspace-side Support page (resources/js/pages/workspaces/support/index.tsx) with a "Contact Support" modal so users can open a ticket directly from the app — captures subject, message, and source page',
                    'Added an admin-side Support Tickets triage page (resources/js/pages/workspaces/admin/support-tickets/index.tsx) with status filters and row-click drilldown',
                    'Backed by a new SupportTicket model + policy + StoreSupportTicketRequest + factory; routes registered in routes/workspaces.php; covered by tests/Feature/Workspaces/SupportTicketsTest.php',
                    'Sidebar / app header / sidebar header now expose the Support entry, and Inertia shares support ticket data so the indicator stays in sync',
                ],
            },
            {
                title: 'Admin — Per-Workspace Metric Whitelist',
                items: [
                    'Admins can now control which metrics each workspace can see via /admin/workspaces/{slug}/metric-settings — backed by the new workspace_metric_settings table, MetricSettingPolicy, WorkspaceMetricSetting model, and a new App\\Support\\Metrics\\MetricRegistry helper',
                    'The MetricPicker / LocationCard / CxRtsCard components honour the whitelist; metrics not enabled for a workspace are hidden in the UI rather than silently empty',
                ],
            },
            {
                title: 'Inventory — Keyword Sync & Public API',
                items: [
                    'Added trigger-fetch-inventory-keyword-records scheduled command + TriggerFetchInventoryKeywordRecord job to keep keyword-driven inventory data fresh',
                    'New /api/v1/public/inventory-items endpoint (PublicApi\\InventoryItemController) for machine-to-machine reads of inventory state',
                    'Added remaining_qty column on inventory_items (separate migration) — surfaced in the inventory items page and form dialogs so on-hand quantity is no longer derived from running totals',
                ],
            },
            {
                title: 'CSR Analytics — Search & URL-Persistent Filters',
                items: [
                    'Added a search input on the CSR analytics page that filters the per-CSR table by name; the search term, type toggle (POS/ERP), and pagination state are now persisted in the URL so refreshes and shared links keep the filter context',
                    'Fixed CSR analytics pagination drift (86d2t08ae) and a CSR management update bug',
                ],
            },
            {
                title: 'Permissions & Errors',
                items: [
                    'Added grant-user-all-view-permissions artisan command (App\\Console\\Commands\\GrantUserAllViewPermissions) to bulk-grant every view-* permission to a user in a workspace',
                    'Added a proper /errors/403 Inertia page with copy and a "Go back" action — middleware now renders this on workspace-permission denials instead of the generic Laravel exception page; covered by tests/Feature/ForbiddenAccessTest.php',
                ],
            },
            {
                title: 'Data Integrity & Misc Fixes',
                items: [
                    'Added a unique index to call_logs (new migration) to enforce one row per (workspace, user, call_date, call_time, phone_number) and prevent duplicate ingestion',
                    'RTS Analytics — table and pagination fix (86d2t0bg9); Dashboard — crowded-filters layout fix (86d2rz9jz); Finance / Remittance row-filter bugs fixed; inventory transaction form clears state when switching from edit to create',
                    'RMO Management — JSX structure cleanup (orphaned </div> removed) so the page parses again; minor layout adjustments',
                ],
            },
        ],
    },
    {
        version: 'v3.6.11',
        date: '2026-05-08',
        sections: [
            {
                title: 'CSR Daily Records — One-Pass Read Path',
                items: [
                    'GET /api/.../csr/daily-records now collapses the previous 7 correlated subqueries into 2 grouped scans (pancake_user_pos_daily_reports + pancake_user_rmo_daily_reports) joined via leftJoinSub — significantly fewer scans on workspaces with large operator lists',
                    'Both rollup subqueries are now workspace-scoped, and the outer pancake_users query is constrained to operators in the current workspace via the pancake_shop_users → shops relationship — totals can no longer leak across workspaces',
                    'Refactored to Eloquent: PancakeUserPosDailyReport::query(), PancakeUserRmoDailyReport::query(), and User::whereHas(shopUsers.shop, ...) replace the raw DB::table calls. Added shopUsers and shop relationships on the Pancake User and ShopUser models',
                ],
            },
            {
                title: 'CSR Daily Records — Sorting & RTS Rate',
                items: [
                    'Added rts_rate as a server-side computed column (returning / (returning + delivered) * 100, rounded to 2 decimals) so CSRs can be sorted by return rate without recomputing in JS',
                    'Sortable columns: csr_name, total_orders, total_sales, total_returning, total_delivered, total_called, total_call_time, total_rmo_call_attempts, rts_rate. Default sort is -total_sales',
                ],
            },
            {
                title: 'sync:csr-daily-records — Writes to POS Rollup',
                items: [
                    'App\\Jobs\\SyncCsrDailyRecord now writes into pancake_user_pos_daily_reports keyed by pancake_user_id instead of csr_daily_records keyed by users.id — the whereNotNull(pu.user_id) filter that previously excluded Pancake operators without linked system accounts is gone, so every operator with confirmed orders is now captured',
                    'rmo_called is no longer written from this job — that count now lives exclusively in the RMO rollup (pancake_user_rmo_daily_reports), avoiding the dual-source drift that v3.6.9 already aligned',
                ],
            },
            {
                title: 'CSR Analytics — UI Column Updates',
                items: [
                    'Frontend table column accessors updated to match the new API aliases (name, total_delivered, total_returning) and a new "RMO Attempts" column showing total_rmo_call_attempts per CSR',
                ],
            },
        ],
    },
    {
        version: 'v3.6.10',
        date: '2026-05-07',
        sections: [
            {
                title: 'CSR Daily Sync — 7-Day Backfill & Queued',
                items: [
                    'sync:csr-daily-records and sync:csr-rmo-daily-records are now queue-based — the per-date aggregation moved into App\\Jobs\\SyncCsrDailyRecord and App\\Jobs\\SyncCsrRmoDailyRecord, and the commands have become thin dispatchers',
                    'Both commands now backfill the last 7 days by default (one job per day) instead of just yesterday — a transient failure on any night is automatically retried on each of the next 6 nightly runs, since the same dates keep being re-aggregated. updateOrCreate keeps it idempotent',
                    'Added --days=N (override the trailing-day window) and kept --date=YYYY-MM-DD (single-day mode) for ad-hoc backfills. The 03:00 / 04:00 nightly schedule is unchanged',
                ],
            },
        ],
    },
    {
        version: 'v3.6.9',
        date: '2026-05-07',
        sections: [
            {
                title: 'Shops — Refresh Users',
                items: [
                    'Added a "Refresh users" action to the shop row menu that re-dispatches the FetchShopUsers job for that shop — newly added Pancake operator accounts now show up in the workspace without having to wait for the next nightly sync or refresh the whole shop',
                ],
            },
            {
                title: 'CSR Daily Records — Aligned & More Detail',
                items: [
                    'sync:csr-daily-records (RMO) now defines total_called the same way the POS rollup does: pancake_order_for_delivery rows where status != PENDING. Previously the RMO and POS sides counted "called" differently, which made cross-tab comparisons drift',
                    'Added a separate total_rmo_call_attempts column to pancake_user_rmo_daily_reports — counts every matching call log per delivery row instead of collapsing to 0/1, so you can now see how many call attempts a CSR actually made versus how many deliveries they reached',
                ],
            },
            {
                title: 'CSR Performance API — Faster Reads',
                items: [
                    'GET /api/.../csr/daily-records now reads from the pre-aggregated daily rollup tables (csr_daily_records, pancake_user_erp_daily_reports, pancake_user_rmo_daily_reports) instead of recomputing from raw orders / deliveries on every request — same numbers, dramatically less work per page load on workspaces with large order volumes',
                ],
            },
        ],
    },
    {
        version: 'v3.6.8',
        date: '2026-05-07',
        sections: [
            {
                title: 'CSR Analytics — Index Coverage for Live Queries',
                items: [
                    'Added composite index (workspace_id, delivery_date, assignee_id) on pancake_order_for_delivery so the RMO outer query becomes a range scan and GROUP BY assignee_id has an ordered source — avoids the temp-table sort that was kicking in on workspaces with large delivery volume',
                    'Added composite index (workspace_id, user_id, call_date, phone_number) on call_logs so the EXISTS subquery powering RMO Called resolves via a single index seek per delivery row instead of falling back to (workspace_id, user_id) plus a row-level date / phone match',
                ],
            },
        ],
    },
    {
        version: 'v3.6.7',
        date: '2026-05-07',
        sections: [
            {
                title: 'CSR Analytics — Live POS Data',
                items: [
                    'POS metrics (Total Sales, Orders, Delivered, Returning, RTS Rate, and the per-CSR table) now read directly from pancake_orders instead of the pancake_user_pos_daily_reports rollup — figures reflect live order activity within the selected range without waiting on the daily rollup job',
                    'Per-CSR aggregation groups pancake_orders by confirmed_by; total_orders / total_sales use confirmed_at, delivered uses status=3 + delivered_at, returning uses status IN (4,5) + returning_at',
                ],
            },
            {
                title: 'CSR Analytics — Live RMO Called',
                items: [
                    'RMO Called and Total Call Time now derive directly from pancake_order_for_delivery (joined to call_logs via workspace + assignee + delivery date + rider/customer phone) instead of pancake_user_rmo_daily_reports — same aggregation logic the SyncCsrRmoDailyRecords command used, just evaluated at request time',
                ],
            },
            {
                title: 'CSR Analytics — ERP Toggle Disabled',
                items: [
                    'ERP option in the POS / ERP toggle is temporarily disabled (greyed out, unclickable) while the ERP data path is being reworked; POS remains the default and only selectable mode',
                ],
            },
            {
                title: 'Scheduler — Analytics Rollup Paused',
                items: [
                    'Hourly and back-fill analytics:rollup schedules in routes/console.php are commented out now that POS / RMO analytics no longer depend on the rollup tables — frees the queue from redundant work',
                ],
            },
        ],
    },
    {
        version: 'v3.6.6',
        date: '2026-05-07',
        sections: [
            {
                title: 'Onboarding — Help Panel',
                items: [
                    'Added a "Need help getting started?" panel under the onboarding form with three quick links: a setup-tutorial video, an email shortcut to hello@artemis.ph, and a Facebook message link — each with an icon and emerald hover accent that matches the brand palette',
                ],
            },
            {
                title: 'Onboarding — Sync Complete',
                items: [
                    'After the initial sync finishes, the onboarding page now does a full reload instead of an Inertia visit — server-side props (workspace flags, sync timestamps, sidebar visibility) refresh cleanly so the dashboard renders with up-to-date state on first paint',
                ],
            },
            {
                title: 'Dashboard — Default Date Range',
                items: [
                    'Dashboard date range now defaults to "start of month → yesterday" instead of "start of month → end of month" so the chart no longer extends into future days and dilutes today\'s metrics with empty buckets',
                ],
            },
            {
                title: 'Pancake Sync — Initial Backfill Window',
                items: [
                    "First-time Pancake page connect (and the Refresh button on Pages) now backfills 1 month of orders and shop customers instead of 3 months, cutting onboarding sync time and keeping queue load proportional to a typical seller's active window",
                ],
            },
        ],
    },
    {
        version: 'v3.6.5',
        date: '2026-05-06',
        sections: [
            {
                title: 'Botcake — Module Toggle',
                items: [
                    "Added a per-workspace botcake_module_enabled flag — the Botcake nav group (Sequences + Flows) is hidden from the sidebar when the module is off, and any permission with the Botcake category is filtered out of the user's effective permission set",
                ],
            },
            {
                title: 'Botcake — Overall vs Historical Stats',
                items: [
                    'Flows and Sequences index pages now have an Overall / Historical toggle; Historical reveals a date-range picker (defaulting to the last 7 days) and re-aggregates Sent / Phone Numbers / Success Rate from the new daily delta tables',
                    'FetchFlowStatistics and FetchSequenceStatistics now compute per-day deltas against the prior cumulative snapshot (clamped at 0 to absorb counter resets) so historical sums add up to real activity within any window — the cumulative-as-of-now value is still saved on the Flow / SequenceMessage row for the Overall view',
                    'Trigger commands renamed under the botcake: namespace (botcake:trigger-fetch-flows, botcake:trigger-fetch-sequences, botcake:trigger-fetch-flow-statistics, botcake:trigger-fetch-sequence-statistics); old names kept as aliases',
                    'Stats triggers now chunk through Flows / Sequences in batches of 200 instead of fetching all at once, preventing memory spikes on workspaces with thousands of records',
                ],
            },
            {
                title: 'Botcake — Schema & Code Layout',
                items: [
                    'botcake_flows, botcake_sequences, and botcake_sequence_messages now use the Botcake-supplied id directly as the primary key — collapsing the previous (auto-increment id + flow_id / sequence_id / message_id) split into a single column. The migration drops and recreates the six related tables to apply the change',
                    'Web controllers for Flows and Sequences moved from app/Http/Controllers/Workspaces/Botcake/ into Modules/Botcake/Http/Controllers/Web/, keeping module-owned code inside the module',
                ],
            },
            {
                title: 'Telescope & Horizon — Access Control',
                items: [
                    'Both /telescope and /horizon now require is_super_admin = true in non-local environments — non-super-admin users get a 403 instead of seeing the dashboards. Local development continues to bypass the gate via the framework default',
                ],
            },
            {
                title: 'Workspace Middleware — Lookup Fallbacks',
                items: [
                    "CheckWorkspace now falls back to the route-bound {workspace} parameter and the user's session current_workspace_id when the X-Workspace-Id header is absent, so URL-scoped routes don't need clients to set the header explicitly",
                ],
            },
        ],
    },
    {
        version: 'v3.6.4',
        date: '2026-05-06',
        sections: [
            {
                title: 'Inventory — SKU Uniqueness',
                items: [
                    'Editing an inventory item now rejects a SKU that is already used by another item in the same workspace, returning a clear validation error instead of silently saving a duplicate',
                ],
            },
            {
                title: 'Call Logs API — Idempotent Sync',
                items: [
                    'Public /call-logs/sync endpoint now upserts on (workspace, user, phone number, call date, call time) so re-syncing the same logs from the mobile app no longer creates duplicate rows',
                    'Response now returns a synced count alongside total, and incoming timestamps are preserved as-sent rather than re-anchored to the app timezone',
                ],
            },
            {
                title: 'Purchased Orders — Create Feedback',
                items: [
                    'Creating a purchased order now shows a success toast on save and an error toast (with console-logged validation details) when the form fails, matching the edit-flow behaviour',
                ],
            },
        ],
    },
    {
        version: 'v3.6.3',
        date: '2026-05-05',
        sections: [
            {
                title: 'Sign-up — Terms & Privacy',
                items: [
                    'Registration form now requires checking an "I agree to the Terms & Conditions and Privacy Policy" box before submit, with links opening the legal pages in a new tab',
                    'Backend validates the acceptance flag and returns a clear error ("You must accept the Terms & Conditions to create an account.") if it is missing',
                ],
            },
            {
                title: 'Plans — New Enterprise Tier',
                items: [
                    'Added a new Enterprise plan to the pricing line-up: custom pricing, unlimited orders and pages, 24-month data retention, full analytics, Parcel Journey SMS included, and dedicated support',
                    'Re-balanced Scale to ₱14,999/mo (down from ₱19,999) to slot under the new Enterprise tier',
                ],
            },
        ],
    },
    {
        version: 'v3.6.2',
        date: '2026-05-05',
        sections: [
            {
                title: 'Plans — Pricing & Page Limits',
                items: [
                    'Re-priced and capped page counts on paid tiers: Starter ₱1,499→₱2,999 with a 5-page cap, Growth ₱3,999→₱5,999 with 25 pages, Scale ₱9,999→₱19,999 with 100 pages',
                    'Parcel Journey SMS is now bundled (free) on every paid tier — removed the per-message rates (₱0.50 / ₱0.35 / ₱0.20) and turned SMS on for Starter so every paid plan includes it',
                ],
            },
            {
                title: 'Marketing Site',
                items: [
                    'Landing page now consistently reads "30-day free trial" everywhere (hero subline, pricing card label, pricing card lede, final CTA, and FAQ) — matches the actual trial length',
                ],
            },
            {
                title: 'Navigation',
                items: [
                    'Removed the Changelog item from the public sidebar; it stays reachable directly at /changelog',
                ],
            },
        ],
    },
    {
        version: 'v3.6.1',
        date: '2026-05-05',
        sections: [
            {
                title: 'Pages — Plan Limits',
                items: [
                    'Pages list now enforces the workspace\'s plan page limit — the index shows an "X/Y pages used" indicator under the Add New Page button, the button disables once the limit is reached, and a tooltip points to upgrading the plan',
                    'Backend now blocks the Add Page flow when the limit is hit (both the create page and the store endpoint) with a clear validation message instead of letting the request through silently',
                ],
            },
            {
                title: 'Free Trial Plan',
                items: [
                    'Bumped Free Trial defaults so new workspaces get a more useful evaluation: 10,000 order cap (was unlimited), 6 months of data retention (was 1), full analytics tier (was basic), Parcel Journey SMS enabled, and priority chat support',
                ],
            },
        ],
    },
    {
        version: 'v3.6.0',
        date: '2026-05-05',
        sections: [
            {
                title: 'RTS — RMO Management',
                items: [
                    'Split the single "Only my data" toggle into two independent filters — "My Assignee Only" and "My Confirmee Only" — so reps can narrow the list to orders they confirmed separately from those assigned to them, and combine both when needed',
                    'Public endpoint and CSV export now accept a confirmee_id filter (mirrors the existing assignee_id filter), and toggle state is persisted per browser via localStorage',
                ],
            },
            {
                title: 'Subscription Gate & Syncing Modal',
                items: [
                    'Restored the subscription-expired gate on every authenticated Inertia page — workspaces with an expired, cancelled, or lapsed trial/active subscription are surfaced the upgrade modal with active non-trial plans (skipped on local environments)',
                    'Restored the "syncing data" modal that appears on first connect until at least one page finishes its initial order sync; copy softened to "Please be patient." now that the sync runs in the background',
                ],
            },
            {
                title: 'Onboarding — Initial Sync Window',
                items: [
                    'First-time Pancake page connect now backfills 3 months of orders and shop customers (was 1 month), so newly onboarded workspaces have deeper history available immediately',
                ],
            },
            {
                title: 'Mobile / Public API — Call Logs',
                items: [
                    'Synced call-log timestamps are now normalised to the workspace timezone before storage, eliminating the off-by-hours drift on the KPI screen',
                    'Total talk time KPI now only counts calls whose phone number matches a delivery on that date (customer or rider phone on pancake_order_for_delivery), giving an accurate read of talk time tied to actual delivery work',
                ],
            },
        ],
    },
    {
        version: 'v3.5.0',
        date: '2026-05-04',
        sections: [
            {
                title: 'Analytics',
                items: [
                    'Page-view analytics via PostHog — tracks Inertia route changes, identifies the signed-in user, and groups events by workspace so funnels and retention can be sliced per workspace',
                    'Toggleable per environment via the VITE_POSTHOG_DISABLED flag, so local development stays out of production analytics',
                ],
            },
            {
                title: 'Pancake — Order Sync',
                items: [
                    'Consolidated the separate shipped-orders sync into the main page-orders job — at 9 AM, 12 PM, 3 PM, 6 PM, and 9 PM the run pulls shipped orders (filter_status[]=2); other runs pull orders updated since the last sync',
                    'Removed the standalone trigger-fetch-page-shipped-orders command and FetchPageShippedOrders job — same coverage with one scheduled command instead of two',
                    'trigger-fetch-page-orders now runs hourly (was every 30 minutes), reducing duplicate fetch overhead now that shipped pulls are interleaved',
                ],
            },
        ],
    },
    {
        version: 'v3.4.1',
        date: '2026-05-01',
        sections: [
            {
                title: 'Inventory — Purchased Orders',
                items: [
                    'List now shows subtotal and summary totals for the active filtered view, so you can see overall delivery fee and total amount without exporting',
                    'Excel export includes the same summary rows at the bottom of the file',
                ],
            },
            {
                title: 'Finance — Transactions',
                items: [
                    'Index page now surfaces totals for credit (in) and debit (out) across the active filters, giving a quick read on cashflow without leaving the page',
                ],
            },
            {
                title: 'Inventory — Stock Transactions',
                items: [
                    'Date-range filter added to the stock transactions list, with the selection preserved across pagination and sort',
                ],
            },
        ],
    },
    {
        version: 'v3.4.0',
        date: '2026-05-01',
        sections: [
            {
                title: 'Pancake — Courier Shipments',
                items: [
                    'New Courier Shipments page under Pancake — import courier reports (xlsx) and reconcile them against your Pancake orders by waybill / tracking code',
                    'J&T Express xlsx importer — uploads are parsed, upserted by (workspace, courier, waybill), and automatically linked to the matching pancake_orders row',
                    'Totals strip surfaces overall and matched-only sums for total shipping cost, COD fee, COD collected, and receivable freight, so you can see exactly how much shipping fee is tied to confirmed Pancake orders',
                    'List supports search by waybill / order # / receiver / phone, matched-only or unmatched-only filters, pickup-date range, and sortable shipping-cost columns',
                    'Two new role permissions — View Courier Shipments and Import Courier Shipments — assignable from the Roles screen',
                ],
            },
        ],
    },
    {
        version: 'v3.3.2',
        date: '2026-05-01',
        sections: [
            {
                title: 'Inventory — Purchased Orders',
                items: [
                    'List now defaults to sorting by issue date (newest first) instead of created date, so the most recently issued POs surface at the top',
                ],
            },
        ],
    },
    {
        version: 'v3.3.1',
        date: '2026-05-01',
        sections: [
            {
                title: 'Inventory — Purchased Orders',
                items: [
                    'Search the list by delivery no., customer PO, or control no.',
                    'Filter the list by issue-date range, with the selection preserved across pagination and sort',
                    'Export CSV now respects the active search, date range, and sort',
                ],
            },
        ],
    },
    {
        version: 'v3.3.0',
        date: '2026-05-01',
        sections: [
            {
                title: 'Admin — Subscription Plans',
                items: [
                    'New Subscription Plans admin panel — create, edit, and manage plans with pricing, trial settings, and feature flags',
                    'Plan list with quick row actions and a shared form between create and edit screens',
                    'Subscription plan seeder updated to match the new schema, safe to re-run idempotently',
                ],
            },
            {
                title: 'Admin — Workspaces',
                items: [
                    'Workspace Management panel reworked — sidebar logo and workspace name added, plus richer per-workspace controls for plan, subscription, and trial state',
                    'Inline plan assignment and subscription edits directly from the workspaces list',
                ],
            },
            {
                title: 'Subscriptions',
                items: [
                    'Core subscription management and 30-day free trial — new workspaces start on a one-month trial that auto-expires via a scheduled command',
                    'Subscription Expired modal — gracefully blocks workspace access when an active plan lapses, with a clear path to upgrade',
                    'Syncing Data modal — friendlier first-run state while initial workspace data is being pulled in',
                    'Subscription gate is temporarily disabled in production while the billing flow is finalised — workspaces continue working as normal in the meantime',
                ],
            },
            {
                title: 'Workspaces — Onboarding',
                items: [
                    'New onboarding flow at /workspaces/onboarding — guided first-run setup that lands new workspaces in a ready-to-use state without manual configuration',
                ],
            },
            {
                title: 'Public API — Call Logs',
                items: [
                    'Two new Bearer-token endpoints under /api/v1/public for call log listing and KPI summary',
                    'CSR mobile clients can now read call log data directly without going through the workspace UI',
                ],
            },
            {
                title: 'Dashboard',
                items: [
                    'Header and dashboard filter — alignment and reset behaviour fixed; selections now persist correctly across navigation',
                    'Statistic breakdown — removed a duplicate total row that was double-counting in summary cards',
                ],
            },
            {
                title: 'RTS Analytics',
                items: [
                    'Breakdown chart x-axis labels now align cleanly with extra bottom padding, so dates no longer overlap on dense ranges',
                ],
            },
            {
                title: 'Inventory',
                items: [
                    'Inventory Items — row-level filtering fixed; filters now apply correctly on first load',
                    'Purchased Orders — total computation fix on the create screen',
                ],
            },
            {
                title: 'Checklist',
                items: [
                    'Edit Checklist — fixed a mobile-only bug that prevented edits from saving',
                    'Add Task dialog — sorting and notification handling tightened',
                ],
            },
            {
                title: 'Polish',
                items: [
                    'Tab titles normalised to "Artemis | <Page>" across both client and SSR for consistent browser tab labels everywhere',
                    'Sidebar settings entry removed in favour of inline controls already available elsewhere',
                    'Small visual cleanups across workspace switcher, app header, and sidebar',
                ],
            },
        ],
    },
    {
        version: 'v3.2.0',
        date: '2026-04-30',
        sections: [
            {
                title: 'Analytics — Live by default',
                items: [
                    'Dashboard cards and breakdowns now compute directly from pancake_orders instead of the hourly rollup table, so numbers reflect activity in near real time rather than waiting for the next rollup pass',
                    'Opt back into the rollup by passing ?source=rollup on the analytics endpoints — useful when you want a faster (but slightly stale) read or to compare values against the rollup baseline',
                    'Per-metric source toggle — each metric class can be flipped between live and rollup independently via setSource(); RtsRate, the avg-days metrics, and the count/amount metrics all support both modes',
                ],
            },
        ],
    },
    {
        version: 'v3.1.0',
        date: '2026-04-28',
        sections: [
            {
                title: 'Finance — Transactions',
                items: [
                    'Export CSV — download the current transactions view as a CSV file with date, account, description, type, transaction type, sub-category, amount, running balance, and notes',
                    'Date range filter — filter transactions by date directly from the toolbar, with the selection preserved across pagination and other filters',
                ],
            },
            {
                title: 'Finance — Remittances',
                items: [
                    'Edit remittances in place — new Edit action in the row dropdown on the remittances list and a dedicated Edit button on the remittance detail page',
                    'Date range filter on the remittances list, matching against the billing period',
                    'Linked Transaction picker now only lists remittance-type transactions and shows amounts formatted in pesos (₱) for easier scanning',
                ],
            },
            {
                title: 'Fixes',
                items: [
                    'Pages — removed a dead duplicate dispatch in the manual refresh path',
                    'SuperAdmin — fixed a casing mismatch on the workspaces index that prevented the page from resolving on case-sensitive filesystems',
                ],
            },
        ],
    },
    {
        version: 'v3.0.2',
        date: '2026-04-27',
        sections: [
            {
                title: 'Fixes',
                items: [
                    'Analytics Rollup — page daily metrics with no activity (all-zero counts and amounts) are no longer written to the rollup table, keeping the metrics dataset compact and avoiding empty rows for inactive pages',
                ],
            },
        ],
    },
    {
        version: 'v3.0.1',
        date: '2026-04-27',
        sections: [
            {
                title: 'Fixes',
                items: [
                    'Purchased Orders — fixed a missing AuthorizesRequests import that caused authorization checks to fail on the Purchased Orders controller',
                ],
            },
        ],
    },
    {
        version: 'v3.0.0',
        date: '2026-04-27',
        sections: [
            {
                title: 'Artemis — Public Launch',
                items: [
                    'Rebranded from ecomm-control-hub to Artemis — the analytics & automation platform for Philippine COD e-commerce',
                    'New public marketing site with Hunt down RTS positioning — hero, problem, features, free trial, how it works, real seller results, and FAQ sections',
                    'New /rts-calculator page — sellers can quantify their monthly RTS bleed in pesos before signing up',
                    'New about, blog, contact, privacy, terms, data-policy, and security pages',
                    'New Artemis logo, emerald brand palette, and dark/light theme toggle on all marketing pages',
                ],
            },
            {
                title: 'Subscriptions',
                items: [
                    'Subscription management UI — workspaces can now view their plan, current period, and billing status',
                    'Plan selection and upgrade flow built on top of the v2.7.1 subscriptions foundation',
                    '14-day free trial flow — new workspaces start on a trial subscription with no credit card required',
                    'Plan tier gating across feature surfaces (gracefully shown rather than hidden when out of plan)',
                ],
            },
            {
                title: 'Roles & Permissions',
                items: [
                    'Reworked permissions engine — roles now resolve through a single source of truth across workspace, module, and action layers',
                    'Per-action permission checks across Members, Roles, Orders, Products, Teams, Inventory, Reports, Shops, and API Keys',
                    'Workspace members with the manage-api-keys permission can now generate and revoke API keys without owner intervention',
                    'Bypass mode for owner-level accounts to keep workspace recovery flows working when permissions are misconfigured',
                ],
            },
            {
                title: 'Finance — Remittances',
                items: [
                    'Detailed remittance management view with per-account balance, transaction history, and date-range filters',
                    'Bulk import from Excel — paste or upload remittance entries in batches with validation and preview before commit',
                    'Inline edit and delete of individual remittance entries from the management view',
                ],
            },
            {
                title: 'Shops',
                items: [
                    'Shops management view aligned with the Pages experience — search, sort, filter, and per-shop checklist progress',
                    'Per-shop status badge and last-sync indicator',
                ],
            },
            {
                title: 'Performance & Polish',
                items: [
                    'Optimization pass on dashboard and analytics queries — faster initial loads with smaller payloads',
                    'New skeleton loading states across dashboard, RTS analytics, and inventory pages',
                    'Sidebar scrollbar fix — no longer overlaps content on narrow viewports',
                    'Metrics pipeline tightened — fewer redundant recalculations across workspace metrics',
                ],
            },
            {
                title: 'Internal',
                items: [
                    'Marketing plan, content playbook, and post calendar documents added to the repository for the launch',
                    'Project documentation refreshed to reflect the Artemis brand and RTS-first positioning',
                ],
            },
        ],
    },
    {
        version: 'v2.7.1',
        date: '2026-04-24',
        sections: [
            {
                title: 'RMO Management',
                items: [
                    'Copy rider and customer phone numbers for the first 10 pending orders directly from the RMO Management view (currently enabled on efb.on-forge.com)',
                    'Parcel Update Notification template form now validates empty text areas before saving',
                ],
            },
            {
                title: 'Sidebar',
                items: [
                    'Removed duplicate RTS entry from the workspace sidebar',
                ],
            },
            {
                title: 'Forms',
                items: [
                    'Polish pass across Inventory Items, Purchased Orders, Products, Teams, and Employees dialogs — tightened validation and layout consistency',
                ],
            },
            {
                title: 'Internal',
                items: [
                    'Subscriptions foundation — new subscription_plans catalog and workspace_subscriptions tables, plan tier constants, idempotent plan seeder, and a subscription relation on Workspace (no user-facing UI yet)',
                    'Build config fix in vite.config.ts',
                ],
            },
        ],
    },
    {
        version: 'v2.7.0',
        date: '2026-04-22',
        sections: [
            {
                title: 'Analytics Optimization',
                items: [
                    'Analytics rollup — new scheduled command precomputes order metrics so dashboards load from aggregated data instead of recalculating per request',
                    'Backfill command for populating analytics rollups across historical date ranges',
                    'Order metrics (AOV, totals, lifetime value, repeat/retention, delivery timing, RTS averages) refactored to read from rollups — significantly faster queries',
                    'Parcel journey metrics (SMS sent, tracked orders, total for delivery) now use the same optimized pipeline',
                ],
            },
            {
                title: 'Inventory Items',
                items: [
                    'Purchased Orders view per inventory item, with pagination',
                    'Fixed inventory transaction bugs affecting stock calculations',
                ],
            },
            {
                title: 'RMO Management',
                items: [
                    'Call logs are now visible directly on the RMO management dashboard',
                    'Phone numbers can be edited in place from the RMO management view',
                    'Parcel status label updated and redundant update logic removed',
                ],
            },
            {
                title: 'CSR Mobile API',
                items: [
                    'New call log synchronization and KPI endpoints for the CSR mobile client',
                    'Call logs moved to a dedicated table with separated KPI calculations for improved accuracy',
                    'Call log sync batches database updates to reduce load during large imports',
                ],
            },
            {
                title: 'SuperAdmin Panel',
                items: [
                    'SuperAdmin panel added with cross-workspace oversight views',
                    'Casing and styling adjustments for consistency with the rest of the app',
                ],
            },
            {
                title: 'Finance',
                items: ['Initial rollout of finance features (feat/finance)'],
            },
            {
                title: 'Parcel Journey',
                items: [
                    'Parcel journey template support added (feat/pj-template)',
                    'parcel_status added to OrderForDelivery for faster filtering and is now nullable',
                ],
            },
            {
                title: 'Internal',
                items: ['Sentry integration added for error monitoring'],
            },
        ],
    },
    {
        version: 'v2.6.0',
        date: '2026-04-16',
        sections: [
            {
                title: 'Workspace Checklist',
                items: [
                    'New Checklist page in the workspace sidebar — define reusable tasks that apply to Shop or Page targets',
                    'Each checklist item has a title, target type (Shop or Page), and a required flag shown as a yes/no badge',
                    'Add, edit, and delete checklist items from a shared modal with validation',
                    'Pages and Shops list rows now include a "View Checklist" action that opens a drawer showing per-target progress',
                    'Optimistic toggle flow — checking or unchecking an item updates instantly and records who completed it',
                    'Pages and Shops sort indicator now reflects how many required checklist items are still pending per target',
                ],
            },
            {
                title: 'Purchased Orders',
                items: [
                    'All columns are now sortable, with sort state persisted in the URL query string',
                    'Create form enforces a strict YYYY-MM-DD issue date and disables submit until required fields are valid',
                ],
            },
            {
                title: 'Inventory Items',
                items: [
                    'Lead Time, Unfulfilled Count, 3-Day Average, and Created Date columns are now sortable',
                ],
            },
            {
                title: 'RMO Management',
                items: [
                    'Filter state is now preserved correctly after navigation — fixed a regression where assignee/status filters would reset',
                ],
            },
            {
                title: 'Tables',
                items: [
                    'Default page size is now 10 rows across CSR Management, CSR Analytics, RTS Analytics breakdowns (Ad, Confirmed By, Product, Rider), Parcel Templates, Roles, Inventory Purchased Orders, Ads Optimization Rules, and the public API endpoints (Users, RMO, CSR Daily Records)',
                    'Row numbering now starts from the correct offset on every paginated table across the system',
                ],
            },
        ],
    },
    {
        version: 'v2.5.1',
        date: '2026-04-16',
        sections: [
            {
                title: 'CSR Analytics',
                items: [
                    'RMO Total For Delivery — new sortable column showing orders assigned to the CSR as conferrer within the selected date range',
                    'RMO Productivity — new sortable column showing RMO called as a percentage of RMO Total For Delivery',
                    'Assignee filter now counts total orders by confirmed_by, so the stat card matches the filtered CSR',
                ],
            },
            {
                title: 'RMO Management',
                items: [
                    "Status picker, Assign to me, and Remove assignee are disabled unless the order's delivery date is today — backend validation mirrors the UI",
                    'Date picker moved to the right side of the toolbar, next to the Show/Hide Statistics button',
                ],
            },
            {
                title: 'CSR ERP Sync',
                items: [
                    'Schedule replaced: backfill runs at 2/3/4/5 AM for 2–5 days ago, plus 12 PM and 3 PM runs for yesterday (previously twice daily at 10 and 22)',
                    'New --date option on trigger-fetch-csr-erp-dail-records accepts any Carbon-parseable value for ad-hoc backfills',
                    'Sync now only targets ACTIVE Pancake users',
                ],
            },
        ],
    },
    {
        version: 'v2.5.0',
        date: '2026-04-14',
        sections: [
            {
                title: 'Inventory Items',
                items: [
                    'New computed metric columns: Unfulfilled, Current Stocks, Waiting for Delivery, 3-Day Avg, Remaining After Fulfillment, Days It Can Last, PO Needed',
                    'Lead Time — new field on inventory items; used in the PO Needed calculation',
                    'Unfulfilled Count — stored column, editable from the item form; synced hourly from Pancake order quantities with status 1, 8, or 9',
                    '3-Day Average — stored column, editable from the item form; synced hourly from confirmed orders over the last 3 full days',
                    'Waiting for Delivery — now sourced only from Purchased Orders with status "Waiting For Delivery"',
                    'All metric columns are center-aligned for easier scanning',
                ],
            },
            {
                title: 'Transaction Logs',
                items: [
                    'Lost — new field on transaction logs to record lost stock quantities',
                    'Remaining Quantity — now a plain editable field (no automatic computation)',
                ],
            },
            {
                title: 'Purchased Orders',
                items: [
                    'Status field added with 8 states: For Approval, Approved, To Pay, Paid, For Purchase, Waiting For Delivery, Delivered, Cancelled',
                    'Edit page added — purchased orders can now be fully edited after creation',
                    'Issue date is now stored and displayed in YYYY-MM-DD format',
                ],
            },
            {
                title: 'Role Permissions',
                items: [
                    'New Manage Permissions page per role — checkbox grid grouped by category with select-all per group',
                    '25 default permissions across 7 categories: Members, Roles, Orders, Products, Teams, Inventory, Reports',
                    '"Manage Permissions" action added to the role dropdown on the Roles page',
                ],
            },
            {
                title: 'Workspace Settings',
                items: [
                    'show_inventory — new setting to control whether the Inventory sidebar group is displayed per workspace',
                    'inventory_sync — new setting to enable hourly syncing of 3-day average and unfulfilled count for inventory items',
                ],
            },
            {
                title: 'Members',
                items: [
                    'Unauthorized remove action now shows an in-page permission toast instead of redirecting to a 403 page',
                ],
            },
        ],
    },
    {
        version: 'v2.4.4',
        date: '2026-04-09',
        sections: [
            {
                title: 'CSR Analytics',
                items: [
                    'Delivered and Returning columns now display as Philippine Peso currency',
                    'All columns are now sortable — click any column header to sort ascending or descending',
                    'Analytics data is now server-side paginated and sorted via QueryBuilder',
                ],
            },
        ],
    },
    {
        version: 'v2.4.3',
        date: '2026-04-09',
        sections: [
            {
                title: 'Internal',
                items: [
                    'CSR daily records are now stored in a dedicated table keyed by Pancake user ID',
                    'Fixed 401 errors on internal API endpoints caused by session auth not being available on the API middleware stack',
                ],
            },
        ],
    },
    {
        version: 'v2.4.2',
        date: '2026-04-09',
        sections: [
            {
                title: 'Fixes',
                items: ['New CSR users now default to ACTIVE status'],
            },
        ],
    },
    {
        version: 'v2.4.1',
        date: '2026-04-09',
        sections: [
            {
                title: 'Fixes',
                items: [
                    'CSR Management — sorting and pagination now work correctly after the first load',
                ],
            },
        ],
    },
    {
        version: 'v2.4.0',
        date: '2026-04-09',
        sections: [
            {
                title: 'API Keys',
                items: [
                    'Generate API keys per workspace to connect external tools and platforms',
                    'Each key has a name, prefix preview, and a last-used timestamp',
                    'Reveal the full key at any time using the eye icon — no need to regenerate after a page refresh',
                    'Revoke any key instantly from the API Keys settings page',
                ],
            },
            {
                title: 'Public API',
                items: [
                    'New public API endpoints authenticated via Bearer token: health check, workspace users, and CSR daily records',
                    'Fixed CSRF token mismatch — public API routes are now stateless and no longer require a CSRF token',
                    'User list endpoint supports search, pagination, and filtering by Pancake account',
                    'CSR daily records endpoint supports upsert — safe to call multiple times for the same date',
                ],
            },
            {
                title: 'CSR Management',
                items: [
                    'New CSR Management page — view all CSRs in your workspace with their linked Pancake accounts',
                    'New CSR Analytics page — daily performance table with total orders, sales, delivered, returning, RMO called, and RTS rate',
                    'Filter CSR analytics by date range',
                ],
            },
            {
                title: 'Inventory Items',
                items: [
                    'New Inventory Items page — create, edit, and delete inventory items per workspace',
                    'Each item has a name, SKU, unit, and description',
                ],
            },
            {
                title: 'Leaderboard',
                items: [
                    'Leaderboard now includes Called Activity and Delivery Success categories',
                    'Group leaderboard by called activity or delivery performance',
                ],
            },
            {
                title: 'Auth Pages',
                items: [
                    'Login, register, and workspace setup pages have been redesigned with a cleaner, more premium look',
                ],
            },
            {
                title: 'Sidebar',
                items: [
                    'API Keys added to the workspace switcher menu for quick access',
                    'Inventory section is hidden in production — only visible in non-production environments',
                ],
            },
        ],
    },
    {
        version: 'v2.3.0',
        date: '2026-04-07',
        sections: [
            {
                title: 'AI Chat — Dashboard & RTS Analytics',
                items: [
                    'Ask the AI questions about your data directly from the dashboard or the RTS analytics page',
                    'Dashboard: analyze your sales metrics, page performance, shop performance, and team performance',
                    'RTS Analytics: ask about returns by price, product, rider, customer risk, location, or order frequency',
                    'Answers are based on the data currently on screen, not guesses',
                ],
            },
            {
                title: 'Members — Reset Password',
                items: [
                    'Admins can now generate a password reset link for any team member',
                    'Click "Copy Reset Link" from the member\'s action menu — the link is copied to your clipboard and ready to share',
                ],
            },
            {
                title: 'RTS Analytics — Performance',
                items: [
                    'Analytics page loads noticeably faster — date filters now use index-friendly queries instead of per-row calculations',
                    'New database indexes on key columns used by the RTS queries',
                ],
            },
        ],
    },
    {
        version: 'v2.2.0',
        date: '2026-04-01',
        sections: [
            {
                title: 'Parcel Journey',
                items: [
                    'New Parcel Journey section added to the RTS menu',
                    'See all your parcel notification templates in one place',
                    'Dashboard cards showing how many orders were tracked, and how many SMS and chat messages were sent',
                    'Filter the dashboard by date range — defaults to the current month',
                    'Browse and manage notification templates with search and pagination',
                ],
            },
            {
                title: 'Page Status',
                items: [
                    'Pages can now be set to Active or Inactive instead of being archived',
                    'Toggle a page on or off directly from the create or edit form',
                    'Pages list shows a clear Active or Inactive badge for each page',
                ],
            },
        ],
    },
    {
        version: 'v2.1.0',
        date: '2026-03-27',
        sections: [
            {
                title: 'Parcel Journey Improvements',
                items: [
                    'Notifications are only sent for orders that are out for delivery today — no unnecessary messages',
                    'Stops tracking a parcel once it has been registered as returned',
                    'Rider name and contact number are now saved automatically when a delivery is recorded',
                ],
            },
            {
                title: 'RTS Analytics',
                items: [
                    'New breakdown by Product — see which items have the most returns',
                    'New breakdown by Rider — see which riders have the most returns',
                    'Price, Delivery Attempts, and Customer RTS cards now default to chart view',
                ],
            },
            {
                title: 'Pages',
                items: [
                    'Pages list now shows whether a page is using the latest sync logic or an older version',
                ],
            },
            {
                title: 'RMO Management',
                items: [
                    'Order status dropdown now shows color-coded labels for each status',
                    'Orders that are out for delivery show a live pulsing indicator',
                ],
            },
        ],
    },
    {
        version: 'v2.0.1',
        date: '2026-03-26',
        sections: [
            {
                title: 'New Features',
                items: [
                    'RMO Management — view and manage return and delivery orders in one place, with search, sorting, and status updates',
                    'Role Management — create and manage user roles to control what each team member can access',
                    'Dark Mode — switch between light and dark theme from the app header',
                    'Employees — view all team members connected to your workspace in a searchable list',
                ],
            },
            {
                title: 'Improvements',
                items: [
                    'Navigation updated with links to RMO Management and Roles',
                    'Your name and initials now appear as a pill in the header instead of a notification bell',
                    'Empty tables now show a helpful message instead of blank space',
                    'Analytics load faster thanks to short-term caching',
                ],
            },
            {
                title: 'Fixes',
                items: [
                    'Fixed sidebar background appearing transparent on mobile in dark mode',
                    'Fixed sidebar border looking too bright on mobile in dark mode',
                    'Fixed an error that occurred when setting up a new workspace',
                ],
            },
        ],
    },
    {
        version: 'v1.2.0',
        date: '2025-12-03',
        sections: [
            {
                title: 'RTS Analytics',
                items: [
                    'New RTS analytics dashboard with key return-to-sender metrics',
                    'City heatmap — see which cities have the most returns at a glance',
                    'Breakdown by user — see RTS numbers per team member',
                    'Breakdown by page — see RTS numbers per Facebook page',
                    'Breakdown by city — detailed table with return counts per city',
                ],
            },
            {
                title: 'Page Management',
                items: [
                    'Search, filter, and sort your pages list',
                    'Archive pages you no longer need and restore them anytime',
                    "View order history directly from a page's detail view",
                ],
            },
            {
                title: 'Teams',
                items: [
                    'Create and manage teams within your workspace',
                    'Assign team members to specific teams',
                ],
            },
            {
                title: 'Employees',
                items: ['Employees section added to the sidebar navigation'],
            },
            {
                title: 'Improvements',
                items: [
                    'Empty tables now show a friendly message with an icon instead of blank space',
                ],
            },
        ],
    },
    {
        version: 'v1.1.0',
        date: '2025-11-28',
        sections: [
            {
                title: 'Workspaces',
                items: [
                    'Create new workspaces from within the app',
                    'Switch between workspaces without logging out',
                    'Workspace selection screen showing all your workspaces and member counts',
                    'Workspace switcher in the sidebar for quick access',
                ],
            },
            {
                title: 'Invitations',
                items: [
                    'Invite team members to your workspace by email',
                    'Invited members can accept or decline the invitation',
                ],
            },
            {
                title: 'Facebook Integration',
                items: [
                    'Connect your Facebook accounts to a workspace',
                    'View linked ad accounts and their campaigns',
                ],
            },
            {
                title: 'Fixes',
                items: [
                    'Fixed a dialog that was getting cut off on smaller screens',
                ],
            },
        ],
    },
    {
        version: 'v1.0.0',
        date: '2025-10-31',
        sections: [
            {
                title: 'Initial Release',
                items: [
                    'Set up your first workspace to get started',
                    'Connect your pages and sync orders automatically',
                    'View all orders per Facebook page',
                    'Manually refresh orders whenever you need the latest data',
                    'Send and view messages on orders',
                ],
            },
        ],
    },
];

const versionColors: Record<string, string> = {
    'v3.3.0':
        'bg-yellow-500/10 text-yellow-600 dark:text-yellow-400 ring-yellow-500/20',
    'v3.1.0': 'bg-sky-500/10 text-sky-600 dark:text-sky-400 ring-sky-500/20',
    'v3.0.2':
        'bg-teal-500/10 text-teal-600 dark:text-teal-400 ring-teal-500/20',
    'v3.0.1':
        'bg-green-500/10 text-green-600 dark:text-green-400 ring-green-500/20',
    'v3.0.0':
        'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 ring-emerald-500/20',
    'v2.7.1':
        'bg-lime-500/10 text-lime-600 dark:text-lime-400 ring-lime-500/20',
    'v2.7.0':
        'bg-purple-500/10 text-purple-600 dark:text-purple-400 ring-purple-500/20',
    'v2.6.0':
        'bg-pink-500/10 text-pink-600 dark:text-pink-400 ring-pink-500/20',
    'v2.5.1':
        'bg-fuchsia-500/10 text-fuchsia-600 dark:text-fuchsia-400 ring-fuchsia-500/20',
    'v2.5.0':
        'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 ring-indigo-500/20',
    'v2.4.4':
        'bg-slate-500/10 text-slate-600 dark:text-slate-400 ring-slate-500/20',
    'v2.4.3':
        'bg-slate-500/10 text-slate-600 dark:text-slate-400 ring-slate-500/20',
    'v2.4.2':
        'bg-slate-500/10 text-slate-600 dark:text-slate-400 ring-slate-500/20',
    'v2.4.1':
        'bg-slate-500/10 text-slate-600 dark:text-slate-400 ring-slate-500/20',
    'v2.4.0':
        'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 ring-cyan-500/20',
    'v2.3.0':
        'bg-teal-500/10 text-teal-600 dark:text-teal-400 ring-teal-500/20',
    'v2.2.0':
        'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 ring-emerald-500/20',
    'v2.1.0':
        'bg-blue-500/10 text-blue-600 dark:text-blue-400 ring-blue-500/20',
    'v2.0.1':
        'bg-violet-500/10 text-violet-600 dark:text-violet-400 ring-violet-500/20',
    'v1.2.0':
        'bg-amber-500/10 text-amber-600 dark:text-amber-400 ring-amber-500/20',
    'v1.1.0':
        'bg-orange-500/10 text-orange-600 dark:text-orange-400 ring-orange-500/20',
    'v1.0.0':
        'bg-rose-500/10 text-rose-600 dark:text-rose-400 ring-rose-500/20',
};

export default function Changelog() {
    return (
        <>
            <Head title="Changelog — Artemis" />
            <div className="min-h-screen bg-gray-50 dark:bg-zinc-950">
                {/* Header */}
                <header className="border-b border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <div className="mx-auto flex max-w-3xl items-center justify-between px-6 py-4">
                        <Link
                            href={home().url}
                            className="flex items-center gap-2.5"
                        >
                            <img
                                src="/img/logo/artemis.png"
                                alt="Artemis"
                                className="h-7 w-7 object-contain"
                            />
                            <span className="font-semibold tracking-tight text-gray-900 dark:text-white">
                                Artemis
                            </span>
                        </Link>
                        <span className="font-mono text-[11px] font-semibold tracking-widest text-gray-400 uppercase dark:text-gray-500">
                            Changelog
                        </span>
                    </div>
                </header>

                {/* Content */}
                <main className="mx-auto max-w-3xl px-6 py-10">
                    <div className="mb-8">
                        <h1 className="text-[28px] font-bold tracking-tight text-gray-900 dark:text-white">
                            What's new
                        </h1>
                        <p className="mt-1 font-mono text-[13px] text-gray-400 dark:text-gray-500">
                            Latest updates and improvements to Artemis.
                        </p>
                    </div>

                    <div className="space-y-6">
                        {changelog.map((entry) => (
                            <div
                                key={entry.version}
                                className="rounded-2xl border border-black/6 bg-white p-6 dark:border-white/6 dark:bg-zinc-900"
                            >
                                {/* Version header */}
                                <div className="mb-5 flex items-center gap-3">
                                    <span
                                        className={`inline-flex items-center rounded-full px-2.5 py-0.5 font-mono text-[11px] font-semibold ring-1 ring-inset ${versionColors[entry.version] ?? 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-400 dark:ring-white/10'}`}
                                    >
                                        {entry.version}
                                    </span>
                                    <span className="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                        {entry.date}
                                    </span>
                                </div>

                                {/* Sections */}
                                <div className="space-y-5">
                                    {entry.sections.map((section) => (
                                        <div key={section.title}>
                                            <p className="mb-2.5 font-mono text-[10px] font-semibold tracking-widest text-gray-400 uppercase dark:text-gray-500">
                                                {section.title}
                                            </p>
                                            <ul className="space-y-1.5">
                                                {section.items.map(
                                                    (item, i) => (
                                                        <li
                                                            key={i}
                                                            className="flex items-start gap-2.5 font-mono text-[12px] text-gray-600 dark:text-gray-400"
                                                        >
                                                            <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-gray-300 dark:bg-zinc-600" />
                                                            {item}
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>

                    <p className="mt-8 text-center font-mono text-[11px] text-gray-400 dark:text-gray-600">
                        © {new Date().getFullYear()} Artemis. All rights
                        reserved.
                    </p>
                </main>
            </div>
        </>
    );
}
