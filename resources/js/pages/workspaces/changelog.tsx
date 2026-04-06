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
        version: 'v2.2.0',
        date: '2026-04-01',
        sections: [
            {
                title: 'Parcel Journey — New Page & Analytics',
                items: [
                    'Added Parcel Journey entry under the RTS sidebar',
                    'New page at /rts/parcel-journeys displaying parcel journey notification templates',
                    'Analytics cards at the top: Tracked Orders, SMS Sent, Chat Sent, Total Sent',
                    'Analytics are computed by combining parcel_journey_notification_logs (batch) + parcel_journey_notifications (real-time)',
                    'Date range filter on the analytics section; defaults to current month',
                    'Template list uses paginated DataTable with the standard premium table design',
                    'Template form redesigned — premium dialog matching the teams form style; variable chips styled as code tokens with violet accent',
                ],
            },
            {
                title: 'Pages — Status Management',
                items: [
                    'Replaced Archive/Restore with Active / Inactive status',
                    'Added status column (enum: active, inactive, default: active) to pages table',
                    'Status toggle added to both Create and Edit page forms',
                    'Status badge on the pages list shows Active (emerald) / Inactive (red)',
                    'PageController::archive and restore now set status instead of soft-deleting',
                ],
            },
        ],
    },
    {
        version: 'v2.1.0',
        date: '2026-03-27',
        sections: [
            {
                title: 'SyncOrder — Full Refactor (SOLID)',
                items: [
                    'Broke SyncOrder job into 5 focused action classes: UpsertOrderAction, SyncOrderItemsAction, SyncShippingAddressAction, SyncParcelTrackingAction, SyncPhoneNumberReportsAction',
                    'SyncOrder is now a thin orchestrator using Laravel method injection',
                    'Added JourneyUpdateNormalizer — fixes update_at typo, resolves canonical status names, extracts rider name/mobile from bracket notation',
                    'Added OrderTimestampResolver and MessageRenderer support classes',
                    'Parcel journey notification logic extracted into ParcelJourneyNotifier + Strategy pattern handlers',
                ],
            },
            {
                title: 'Parcel Journey — Logic Improvements',
                items: [
                    'Save only journeys where status is On Delivery or created_at is today',
                    'Stop saving journey entries that succeed a Return Register status',
                    'isNotifiable only triggers if created_at is today',
                    'Rider name and mobile are now parsed by the normalizer and saved directly on the parcel_journeys record',
                    'OnDeliveryHandler reads rider_name/rider_mobile from the saved journey instead of re-parsing the note',
                ],
            },
            {
                title: 'RTS Analytics — New Cards & Decoupled Architecture',
                items: [
                    'Each analytics card is now a self-contained component with its own fetch/state',
                    'New: By Product — RTS breakdown by product/item name with sortable DataTable',
                    'New: By Rider — RTS breakdown by rider name, sourced from the latest On Delivery parcel journey per order',
                    'Price, Delivery Attempts, and Cx RTS cards default to chart view',
                    'Product and Rider breakdowns use DataTable',
                ],
            },
            {
                title: 'Pages List',
                items: [
                    'is_sync_logic_updated flag now shown inline in the Last Sync column as an Updated / Legacy badge',
                ],
            },
            {
                title: 'RMO Management — Refactor & Design',
                items: [
                    'ForDeliveryController refactored: query building extracted to ForDeliveryQuery, stats to ForDeliveryStatsService',
                    'Parcel status badge shows an animated pulsing dot when status is out_for_delivery',
                    'Order status picker dropdown now shows color-coded pill badges for each option',
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
                    'RMO Management Module — tabular view of return and delivery orders, status management via dropdown, search, pagination, and sortable columns',
                    'Role Management Module — dedicated module for managing user roles with search, create, modify, and archive support',
                    'Theme Toggle — Dark mode / Light mode support',
                    'Employees page — New page listing Pancake users as Employees with search, sort, and pagination',
                    'Employees sidebar link — "Employees" added to the main workspace navigation',
                ],
            },
            {
                title: 'Improvements',
                items: [
                    'Sidebar Navigation — Added RMO Management and Roles module links',
                    'App header — Removed notification bell; updated authenticated user display to a premium pill button showing initials and name',
                    'Data table empty state — Replaced plain "No results." text with a centered icon box, heading, and hint text',
                    'Analytics caching — All AnalyticsController methods now cache results for 5 minutes with workspace-scoped cache keys',
                ],
            },
            {
                title: 'Fixes',
                items: [
                    'Sidebar mobile dark mode — Fixed almost-transparent sidebar background on mobile dark mode',
                    'Sidebar mobile border — Fixed overly bright border on the mobile sidebar sheet in dark mode',
                    'Workspace setup route — Fixed MethodNotAllowedHttpException by correcting route method from PUT to POST',
                ],
            },
            {
                title: 'Database',
                items: [
                    'pancake_customers — Removed unique index on fb_id column',
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
                    'RTS analytics dashboard with key metrics',
                    'City heatmap — visual breakdown of RTS orders by city',
                    'User analytics — RTS breakdown by assigned user',
                    'Page breakdown — RTS breakdown per Facebook page',
                    'City breakdown — tabular city-level RTS stats with pagination',
                ],
            },
            {
                title: 'Page Management',
                items: [
                    'Pages list with search, filter, and sort support',
                    'Archive and restore pages',
                    'Page detail view with order history',
                ],
            },
            {
                title: 'Teams',
                items: [
                    'Teams table, model, and controller',
                    'Assign workspace members to teams',
                ],
            },
            {
                title: 'Employees',
                items: [
                    'Employees navigation added to sidebar',
                    'Workspace user seeder for development',
                ],
            },
            {
                title: 'UI Improvements',
                items: [
                    'Data table empty state with icon, heading, and hint text',
                    'Heatmap height adjustments for better readability',
                ],
            },
        ],
    },
    {
        version: 'v1.1.0',
        date: '2025-11-28',
        sections: [
            {
                title: 'Workspace Management',
                items: [
                    'Workspace creation flow',
                    'Workspace context — scopes all data to the active workspace',
                    'Workspace switching — switch between workspaces without logging out',
                    'Workspace selection page listing all workspaces with member counts',
                    'Workspace switcher dropdown in the sidebar',
                    '"All workspaces" option for cross-workspace views',
                    'Back button on workspace selection page',
                ],
            },
            {
                title: 'Invitation Flow',
                items: [
                    'Invite members to a workspace via email',
                    'Accept/decline invitation flow',
                ],
            },
            {
                title: 'Facebook Integration',
                items: [
                    'Connect Facebook accounts to a workspace',
                    'Fetch and display Facebook ad accounts',
                    'Fetch campaigns linked to ad accounts',
                ],
            },
            {
                title: 'Fixes',
                items: [
                    'Fixed dialog overflow on smaller screens',
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
                    'Workspace setup — onboarding flow to create the first workspace',
                    'Order sync — pull and sync orders from connected pages',
                    'Page orders — view orders per Facebook page',
                    'Trigger manual fetch for page orders',
                    'Allow refreshing page orders on demand',
                    'Message support on orders',
                ],
            },
        ],
    },
];

const versionColors: Record<string, string> = {
    'v2.2.0': 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 ring-emerald-500/20',
    'v2.1.0': 'bg-blue-500/10 text-blue-600 dark:text-blue-400 ring-blue-500/20',
    'v2.0.1': 'bg-violet-500/10 text-violet-600 dark:text-violet-400 ring-violet-500/20',
    'v1.2.0': 'bg-amber-500/10 text-amber-600 dark:text-amber-400 ring-amber-500/20',
    'v1.1.0': 'bg-orange-500/10 text-orange-600 dark:text-orange-400 ring-orange-500/20',
    'v1.0.0': 'bg-rose-500/10 text-rose-600 dark:text-rose-400 ring-rose-500/20',
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
