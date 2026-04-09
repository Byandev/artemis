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
