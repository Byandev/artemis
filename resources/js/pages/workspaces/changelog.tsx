import { Head, Link } from '@inertiajs/react';
import { home } from '@/routes';

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
                items: [
                    'Initial rollout of finance features (feat/finance)',
                ],
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
                items: [
                    'Sentry integration added for error monitoring',
                ],
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
                    'Status picker, Assign to me, and Remove assignee are disabled unless the order\'s delivery date is today — backend validation mirrors the UI',
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
                items: [
                    'New CSR users now default to ACTIVE status',
                ],
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
    'v3.1.0':
        'bg-sky-500/10 text-sky-600 dark:text-sky-400 ring-sky-500/20',
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
                        <Link href={home().url} className="flex items-center gap-2.5">
                            <img src="/img/logo/artemis.png" alt="Artemis" className="h-7 w-7 object-contain" />
                            <span className="font-semibold tracking-tight text-gray-900 dark:text-white">Artemis</span>
                        </Link>
                        <span className="font-mono text-[11px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">
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
                                            <p className="mb-2.5 font-mono text-[10px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">
                                                {section.title}
                                            </p>
                                            <ul className="space-y-1.5">
                                                {section.items.map((item, i) => (
                                                    <li
                                                        key={i}
                                                        className="flex items-start gap-2.5 font-mono text-[12px] text-gray-600 dark:text-gray-400"
                                                    >
                                                        <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-gray-300 dark:bg-zinc-600" />
                                                        {item}
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>

                    <p className="mt-8 text-center font-mono text-[11px] text-gray-400 dark:text-gray-600">
                        © {new Date().getFullYear()} Artemis. All rights reserved.
                    </p>
                </main>
            </div>
        </>
    );
}
