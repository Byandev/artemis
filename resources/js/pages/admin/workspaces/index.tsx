import PageHeader from '@/components/common/PageHeader';
import { MetricSettingDialog } from '@/components/metrics/metricsetting-dialog-form';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import DatePicker from '@/components/ui/date-picker';
import AdminSidebarLayout from '@/layouts/admin/admin-sidebar-layout';
import { PaginatedData, SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { format } from 'date-fns';
import { omit } from 'lodash';
import debounce from 'lodash/debounce';
import {
    ArrowUpRight,
    Boxes,
    ChartColumnBig,
    CreditCard,
    Files,
    LayoutGrid,
    LucideIcon,
    MessagesSquare,
    Search,
    Settings2,
    Smartphone,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';

interface SubscriptionPlan {
    id: number;
    code: string;
    name: string;
    price_php: string;
}

interface Subscription {
    id: number;
    subscription_plan_id: number;
    status: string;
    current_period_start: string | null;
    current_period_end: string | null;
    trial_ends_at: string | null;
    plan: SubscriptionPlan;
}

interface Workspace {
    id: number;
    name: string;
    slug: string;
    created_at: string;
    owner?: { name: string };
    pages_count: number;
    shops_count: number;
    sms_parcel_journey_pages_count: number;
    chat_parcel_journey_pages_count: number;
    max_shops: number | null;
    subscription?: Subscription | null;
    inventory_module_enabled: boolean;
    finance_module_enabled: boolean;
    products_module_enabled: boolean;
    teams_module_enabled: boolean;
    checklist_module_enabled: boolean;
    csr_module_enabled: boolean;
    rmo_module_enabled: boolean;
    leaderboard_module_enabled: boolean;
    botcake_module_enabled: boolean;
    creatives_module_enabled: boolean;
    meta_ads_module_enabled: boolean;
    gencys_module_enabled: boolean;
    is_gencys_partner: boolean;
    sales_marketing_dashboard_module_enabled: boolean;
    video_editor_dashboard_module_enabled: boolean;
    csr_dashboard_module_enabled: boolean;
    sim_gateway_module_enabled: boolean;
    ad_spend_goals_module_enabled: boolean;
    billing_module_enabled: boolean;
    courses_module_enabled: boolean;
    welle_module_enabled: boolean;
    metric_settings?: { metric_key: string }[];
}

const MODULE_FIELDS: Array<{
    key: keyof Pick<
        Workspace,
        | 'inventory_module_enabled'
        | 'finance_module_enabled'
        | 'products_module_enabled'
        | 'teams_module_enabled'
        | 'checklist_module_enabled'
        | 'csr_module_enabled'
        | 'rmo_module_enabled'
        | 'leaderboard_module_enabled'
        | 'botcake_module_enabled'
        | 'creatives_module_enabled'
        | 'meta_ads_module_enabled'
        | 'gencys_module_enabled'
        | 'is_gencys_partner'
        | 'sales_marketing_dashboard_module_enabled'
        | 'video_editor_dashboard_module_enabled'
        | 'csr_dashboard_module_enabled'
        | 'sim_gateway_module_enabled'
        | 'ad_spend_goals_module_enabled'
        | 'billing_module_enabled'
        | 'courses_module_enabled'
        | 'welle_module_enabled'
    >;
    label: string;
    description: string;
}> = [
    {
        key: 'products_module_enabled',
        label: 'Products',
        description: 'Product catalog and management',
    },
    {
        key: 'teams_module_enabled',
        label: 'Teams',
        description: 'Team grouping and assignments',
    },
    {
        key: 'checklist_module_enabled',
        label: 'Checklist',
        description: 'Per-shop and per-page checklist',
    },
    {
        key: 'csr_module_enabled',
        label: 'CSR',
        description: 'CSR management and analytics',
    },
    {
        key: 'inventory_module_enabled',
        label: 'Inventory',
        description: 'Inventory items, transactions, purchased orders',
    },
    {
        key: 'finance_module_enabled',
        label: 'Finance',
        description: 'Accounts, transactions, remittances',
    },
    {
        key: 'rmo_module_enabled',
        label: 'RMO Management',
        description: 'Public RMO management link',
    },
    {
        key: 'leaderboard_module_enabled',
        label: 'Leaderboards',
        description: 'Public leaderboards link',
    },
    {
        key: 'botcake_module_enabled',
        label: 'Botcake',
        description: 'Botcake sequences and flows',
    },
    {
        key: 'creatives_module_enabled',
        label: 'Creatives',
        description: 'Creative tracker with review and ads campaign status',
    },
    {
        key: 'meta_ads_module_enabled',
        label: 'Meta Ads',
        description: 'Ads manager, optimization rules, and approvals',
    },
    {
        key: 'gencys_module_enabled',
        label: 'Gencys ERP',
        description: 'Gencys ERP daily sales tracker',
    },
    {
        key: 'is_gencys_partner',
        label: 'Gencys Partner',
        description: 'Enable syncing inventory from Gencys',
    },
    {
        key: 'sales_marketing_dashboard_module_enabled',
        label: 'S&M Dashboard',
        description: 'Sales & Marketing dashboard',
    },
    {
        key: 'video_editor_dashboard_module_enabled',
        label: 'Video Editor Dashboard',
        description: 'Video Editor dashboard',
    },
    {
        key: 'csr_dashboard_module_enabled',
        label: 'CSR Dashboard',
        description: 'CSR personal dashboard',
    },
    {
        key: 'sim_gateway_module_enabled',
        label: 'SMS Gateway',
        description: 'Send/receive SMS through workspace SIMs',
    },
    {
        key: 'ad_spend_goals_module_enabled',
        label: 'Ad Spend Goals',
        description: 'Per-team daily ad spend goals and milestones',
    },
    {
        key: 'billing_module_enabled',
        label: 'Billing',
        description: 'Subscription, invoices, and payment records',
    },
    {
        key: 'courses_module_enabled',
        label: 'Courses',
        description: 'Training courses and learning material',
    },
    {
        key: 'welle_module_enabled',
        label: 'Welle',
        description: 'Connect a Welle account from workspace settings',
    },
];

type ModuleKey = (typeof MODULE_FIELDS)[number]['key'];

// Logical groupings for the admin "Toggle Modules" modal, so related features
// read as sections instead of one long flat list.
const MODULE_GROUPS: {
    title: string;
    description: string;
    keys: ModuleKey[];
}[] = [
    {
        title: 'Catalog & Operations',
        description: 'Products, stock, finance, and daily ops',
        keys: [
            'products_module_enabled',
            'inventory_module_enabled',
            'finance_module_enabled',
            'billing_module_enabled',
            'gencys_module_enabled',
            'is_gencys_partner',
            'checklist_module_enabled',
        ],
    },
    {
        title: 'Team & CSR',
        description: 'People, assignments, and customer service',
        keys: ['teams_module_enabled', 'csr_module_enabled'],
    },
    {
        title: 'Marketing & Ads',
        description: 'Ad management, creatives, and messaging',
        keys: [
            'meta_ads_module_enabled',
            'creatives_module_enabled',
            'ad_spend_goals_module_enabled',
            'botcake_module_enabled',
            'sim_gateway_module_enabled',
        ],
    },
    {
        title: 'Dashboards',
        description: 'Role-specific analytics dashboards',
        keys: [
            'sales_marketing_dashboard_module_enabled',
            'video_editor_dashboard_module_enabled',
            'csr_dashboard_module_enabled',
        ],
    },
    {
        title: 'Learning',
        description: 'Training material for workspace members',
        keys: ['courses_module_enabled'],
    },
    {
        title: 'Integrations',
        description: 'External accounts connected to this workspace',
        keys: ['welle_module_enabled'],
    },
    {
        title: 'Public Pages',
        description: 'Externally shareable links',
        keys: ['rmo_module_enabled', 'leaderboard_module_enabled'],
    },
];

const MODULE_BY_KEY = Object.fromEntries(
    MODULE_FIELDS.map((f) => [f.key, f]),
) as Record<ModuleKey, (typeof MODULE_FIELDS)[number]>;

interface Props {
    workspaces: PaginatedData<Workspace>;
    plans: SubscriptionPlan[];
    filters: {
        search: string;
        sort?: string;
    };
}

const statusColors: Record<string, string> = {
    active: 'bg-green-50 text-green-700 border-green-200 dark:bg-green-500/10 dark:text-green-400 dark:border-green-500/20',
    trialing:
        'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500/20',
    past_due:
        'bg-yellow-50 text-yellow-700 border-yellow-200 dark:bg-yellow-500/10 dark:text-yellow-400 dark:border-yellow-500/20',
    canceled:
        'bg-zinc-100 text-zinc-600 border-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:border-zinc-700',
    expired:
        'bg-red-50 text-red-700 border-red-200 dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/20',
};

/**
 * Shows how many of a workspace's pages are configured for a given parcel
 * journey channel, as "configured / total pages".
 */
function PagesCoverageCell({
    configured,
    total,
    icon: Icon,
    label,
}: {
    configured: number;
    total: number;
    icon: LucideIcon;
    label: string;
}) {
    const tone =
        total === 0 || configured === 0
            ? 'border-zinc-200 bg-zinc-50 text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-400'
            : configured === total
              ? 'border-green-200 bg-green-50 text-green-700 dark:border-green-500/20 dark:bg-green-500/10 dark:text-green-400'
              : 'border-yellow-200 bg-yellow-50 text-yellow-700 dark:border-yellow-500/20 dark:bg-yellow-500/10 dark:text-yellow-400';

    return (
        <div
            className="text-center"
            title={`${configured} of ${total} page(s) — ${label}`}
        >
            <div
                className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-bold ${tone}`}
            >
                <Icon className="h-3 w-3" />
                {total > 0 ? `${configured} / ${total}` : '—'}
            </div>
        </div>
    );
}

export default function Index({ workspaces, plans, filters }: Props) {
    // Only the primary admin account may jump straight into a client workspace.
    const canOpenWorkspace =
        usePage<SharedData>().props.auth.user?.email === 'admin@artemis.ph';
    const [selectedWorkspace, setSelectedWorkspace] =
        useState<Workspace | null>(null);
    const [search, setSearch] = useState(filters.search || '');
    const [editingWorkspace, setEditingWorkspace] = useState<Workspace | null>(
        null,
    );
    const [editingModules, setEditingModules] = useState<Workspace | null>(
        null,
    );
    const [editingMaxShops, setEditingMaxShops] = useState<Workspace | null>(
        null,
    );

    const initialSorting = useMemo(() => {
        const sort =
            typeof filters.sort === 'string' ? filters.sort : undefined;
        if (sort) {
            const isDesc = sort.startsWith('-');
            return [{ id: sort.replace(/^-/, ''), desc: isDesc }];
        }
        return [];
    }, [filters.sort]);

    const performQuery = useCallback(
        debounce((s: string) => {
            router.get(
                '/admin/workspaces',
                {
                    search: s || undefined,
                    page: 1,
                },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        }, 400),
        [],
    );

    useEffect(() => {
        if (search !== (filters.search || '')) {
            performQuery(search);
        }
        return () => performQuery.cancel();
    }, [search, performQuery, filters.search]);

    const columns: ColumnDef<Workspace>[] = [
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Workspace" />
            ),
            cell: ({ row }) => (
                <div className="flex items-center gap-3">
                    <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                        <LayoutGrid className="h-4 w-4" />
                    </div>
                    <div>
                        <div className="font-semibold text-zinc-900 dark:text-zinc-100">
                            {row.original.name}
                        </div>
                        <div className="font-mono text-xs tracking-tighter text-zinc-500">
                            /{row.original.slug}
                        </div>
                    </div>
                </div>
            ),
        },
        {
            id: 'owner',
            accessorFn: (row) => row.owner?.name || 'Platform Admin',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Primary Owner" />
            ),
            cell: ({ row }) => (
                <div className="flex items-center gap-2 text-zinc-600 dark:text-zinc-400">
                    <div className="h-2 w-2 rounded-full bg-brand-500" />
                    {row.original.owner?.name || 'Platform Admin'}
                </div>
            ),
        },
        {
            accessorKey: 'shops_count',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Shops"
                    className="justify-center"
                />
            ),
            cell: ({ row }) => (
                <div className="text-center">
                    <div className="inline-flex items-center gap-1.5 rounded-full border border-brand-100 bg-brand-50/50 px-3 py-1 text-xs font-bold text-brand-600 dark:border-brand-500/20 dark:bg-brand-500/10 dark:text-brand-400">
                        <Files className="h-3 w-3" />
                        {row.original.shops_count}
                        {row.original.max_shops !== null
                            ? ` / ${row.original.max_shops}`
                            : ''}{' '}
                        Shops
                    </div>
                </div>
            ),
        },
        {
            accessorKey: 'sms_parcel_journey_pages_count',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Pages w/ SMS Journey"
                    className="justify-center"
                />
            ),
            cell: ({ row }) => (
                <PagesCoverageCell
                    configured={row.original.sms_parcel_journey_pages_count}
                    total={row.original.pages_count}
                    icon={Smartphone}
                    label="InfoTxt token + user ID set"
                />
            ),
        },
        {
            accessorKey: 'chat_parcel_journey_pages_count',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Pages w/ Chat Journey"
                    className="justify-center"
                />
            ),
            cell: ({ row }) => (
                <PagesCoverageCell
                    configured={row.original.chat_parcel_journey_pages_count}
                    total={row.original.pages_count}
                    icon={MessagesSquare}
                    label="Parcel journey flow + custom field set"
                />
            ),
        },
        {
            id: 'subscription',
            accessorFn: (row) => row.subscription?.plan.name || 'No plan',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Subscription"
                    className="justify-center"
                />
            ),
            cell: ({ row }) => (
                <div className="text-center">
                    {row.original.subscription ? (
                        <div className="inline-flex flex-col items-center gap-1">
                            <span className="text-xs font-semibold text-zinc-900 dark:text-zinc-100">
                                {row.original.subscription.plan.name}
                            </span>
                            <span
                                className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-medium ${statusColors[row.original.subscription.status] || statusColors.canceled}`}
                            >
                                {row.original.subscription.status}
                            </span>
                        </div>
                    ) : (
                        <span className="text-xs text-zinc-400 italic">
                            No plan
                        </span>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'created_at',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Created"
                    className="justify-center"
                />
            ),
            cell: ({ row }) => {
                const date = new Date(row.original.created_at);
                return (
                    <div className="text-center">
                        <div className="text-xs font-medium text-zinc-900 dark:text-zinc-100">
                            {date.toLocaleDateString(undefined, {
                                year: 'numeric',
                                month: 'short',
                                day: 'numeric',
                            })}
                        </div>
                        <div className="text-[10px] text-zinc-500">
                            {date.toLocaleTimeString(undefined, {
                                hour: 'numeric',
                                minute: '2-digit',
                            })}
                        </div>
                    </div>
                );
            },
        },
        {
            id: 'actions',
            enableSorting: false,
            header: () => (
                <div className="text-right text-[11px] font-bold tracking-wider text-zinc-500 uppercase">
                    Actions
                </div>
            ),
            cell: ({ row }) => (
                <div className="flex items-center justify-end gap-1 text-right">
                    <Link
                        href={`/admin/workspaces/${row.original.slug}/report`}
                        className="rounded-md p-1.5 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                        title="View client report"
                    >
                        <ChartColumnBig className="h-4 w-4" />
                    </Link>
                    {canOpenWorkspace && (
                        <Link
                            href={`/workspaces/${row.original.slug}/dashboard`}
                            className="rounded-md p-1.5 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                            title="Open workspace dashboard"
                        >
                            <ArrowUpRight className="h-4 w-4" />
                        </Link>
                    )}
                    <button
                        onClick={() => setEditingMaxShops(row.original)}
                        className="rounded-md p-1.5 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                        title="Set max shops"
                    >
                        <Files className="h-4 w-4" />
                    </button>
                    <button
                        onClick={() => setEditingModules(row.original)}
                        className="rounded-md p-1.5 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                        title="Toggle modules"
                    >
                        <Boxes className="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        onClick={() => setSelectedWorkspace(row.original)}
                        className="rounded-md p-1.5 text-zinc-400 transition-colors hover:bg-emerald-50 hover:text-emerald-600 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-400"
                        title="Configure Metrics"
                    >
                        <Settings2 className="h-4 w-4" />
                    </button>

                    {/* Subscription Adjust Button */}
                    <button
                        onClick={() => setEditingWorkspace(row.original)}
                        className="rounded-md p-1.5 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                        title="Change subscription"
                    >
                        <CreditCard className="h-4 w-4" />
                    </button>
                </div>
            ),
        },
    ];

    return (
        <AdminSidebarLayout>
            <Head title="Admin | Workspaces" />

            <div className="p-4 md:p-6">
                <PageHeader
                    title="Workspace Management"
                    description="Platform-wide overview of all created workspaces and their owners."
                >
                    <div className="relative w-full sm:w-64">
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                        <input
                            type="text"
                            placeholder="Search workspaces..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full rounded-md border border-zinc-200 bg-white py-2 pr-4 pl-10 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-800 dark:bg-zinc-950"
                        />
                    </div>
                </PageHeader>

                <div className="mt-6 rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={workspaces.data || []}
                        enableInternalPagination={false}
                        initialSorting={initialSorting}
                        meta={{ ...omit(workspaces, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                '/admin/workspaces',
                                {
                                    search: search || undefined,
                                    sort: params?.sort || undefined,
                                    page: params?.page ?? 1,
                                    per_page: params?.per_page ?? undefined,
                                },
                                {
                                    preserveState: true,
                                    replace: true,
                                    preserveScroll: true,
                                },
                            );
                        }}
                    />
                </div>
            </div>

            {/* Metric Configuration Modal */}
            <MetricSettingDialog
                open={!!selectedWorkspace}
                onOpenChange={(open) => !open && setSelectedWorkspace(null)}
                workspace={selectedWorkspace}
            />

            {/* Subscription Modal */}
            {editingWorkspace && (
                <SubscriptionModal
                    workspace={editingWorkspace}
                    plans={plans}
                    onClose={() => setEditingWorkspace(null)}
                />
            )}

            {editingMaxShops && (
                <MaxShopsModal
                    workspace={editingMaxShops}
                    onClose={() => setEditingMaxShops(null)}
                />
            )}

            {editingModules && (
                <ModulesModal
                    workspace={editingModules}
                    onClose={() => setEditingModules(null)}
                />
            )}
        </AdminSidebarLayout>
    );
}

/** ISO datetime from the API → the `YYYY-MM-DD` an <input type="date"> wants. */
function toDateInput(value: string | null | undefined): string {
    return value ? value.slice(0, 10) : '';
}

function SubscriptionModal({
    workspace,
    plans,
    onClose,
}: {
    workspace: Workspace;
    plans: SubscriptionPlan[];
    onClose: () => void;
}) {
    const { data, setData, put, processing, errors } = useForm({
        subscription_plan_id:
            workspace.subscription?.subscription_plan_id?.toString() ||
            (plans[0]?.id?.toString() ?? ''),
        status: workspace.subscription?.status || 'active',
        current_period_start: toDateInput(
            workspace.subscription?.current_period_start,
        ),
        current_period_end: toDateInput(
            workspace.subscription?.current_period_end,
        ),
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/admin/workspaces/${workspace.slug}/subscription`, {
            onSuccess: () => onClose(),
        });
    }

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            onClick={onClose}
        >
            <div
                className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-zinc-900"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="mb-5 flex items-center justify-between">
                    <div>
                        <h3 className="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                            Adjust Subscription
                        </h3>
                        <p className="text-sm text-zinc-500">
                            {workspace.name}
                        </p>
                    </div>
                    <button
                        onClick={onClose}
                        className="rounded-md p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            Plan
                        </label>
                        <select
                            value={data.subscription_plan_id}
                            onChange={(e) => {
                                const selectedPlan = plans.find(
                                    (p) => p.id.toString() === e.target.value,
                                );
                                setData((prev) => ({
                                    ...prev,
                                    subscription_plan_id: e.target.value,
                                    status:
                                        selectedPlan?.code === 'free_trial'
                                            ? 'trialing'
                                            : 'active',
                                }));
                            }}
                            className="w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-700 dark:bg-zinc-800"
                        >
                            {plans.map((plan) => (
                                <option key={plan.id} value={plan.id}>
                                    {plan.name} — ₱
                                    {parseFloat(
                                        plan.price_php,
                                    ).toLocaleString()}
                                    /mo
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            Status
                        </label>
                        <select
                            value={data.status}
                            onChange={(e) => setData('status', e.target.value)}
                            className="w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-700 dark:bg-zinc-800"
                        >
                            <option value="active">Active</option>
                            <option value="trialing">Trialing</option>
                            <option value="past_due">Past Due</option>
                            <option value="canceled">Canceled</option>
                            <option value="expired">Expired</option>
                        </select>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                Period start
                            </label>
                            <DatePicker
                                id={`subscription-${workspace.id}-period-start`}
                                mode="single"
                                fullWidth
                                placeholder="Auto"
                                defaultDate={
                                    data.current_period_start || undefined
                                }
                                onChange={(dates) =>
                                    setData(
                                        'current_period_start',
                                        dates.length
                                            ? format(dates[0], 'yyyy-MM-dd')
                                            : '',
                                    )
                                }
                            />
                            {errors.current_period_start && (
                                <p className="mt-1 text-xs text-red-600">
                                    {errors.current_period_start}
                                </p>
                            )}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                Period end
                            </label>
                            <DatePicker
                                id={`subscription-${workspace.id}-period-end`}
                                mode="single"
                                fullWidth
                                placeholder="Auto"
                                defaultDate={
                                    data.current_period_end || undefined
                                }
                                // Can't end before it starts — the same rule the
                                // backend enforces with after_or_equal.
                                minDate={data.current_period_start || undefined}
                                onChange={(dates) =>
                                    setData(
                                        'current_period_end',
                                        dates.length
                                            ? format(dates[0], 'yyyy-MM-dd')
                                            : '',
                                    )
                                }
                            />
                            {errors.current_period_end && (
                                <p className="mt-1 text-xs text-red-600">
                                    {errors.current_period_end}
                                </p>
                            )}
                        </div>
                        <p className="col-span-2 text-xs text-zinc-500">
                            Leave blank to auto-calculate —{' '}
                            {data.status === 'trialing'
                                ? "the plan's trial length"
                                : 'one month'}{' '}
                            from the start date. On a trial, the end date also
                            sets when the trial lapses.
                        </p>
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-md border border-zinc-200 px-4 py-2 text-sm font-medium text-zinc-600 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-700 disabled:opacity-50"
                        >
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function MaxShopsModal({
    workspace,
    onClose,
}: {
    workspace: Workspace;
    onClose: () => void;
}) {
    const { data, setData, put, processing } = useForm({
        max_shops: workspace.max_shops?.toString() ?? '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/admin/workspaces/${workspace.slug}/max-shops`, {
            onSuccess: () => onClose(),
            preserveScroll: true,
        });
    }

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            onClick={onClose}
        >
            <div
                className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-zinc-900"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="mb-5 flex items-center justify-between">
                    <div>
                        <h3 className="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                            Max Shops Limit
                        </h3>
                        <p className="text-sm text-zinc-500">
                            {workspace.name}
                        </p>
                    </div>
                    <button
                        onClick={onClose}
                        className="rounded-md p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            Maximum Shops
                        </label>
                        <input
                            type="number"
                            min="1"
                            placeholder="No limit"
                            value={data.max_shops}
                            onChange={(e) =>
                                setData('max_shops', e.target.value)
                            }
                            className="w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-700 dark:bg-zinc-800"
                        />
                        <p className="mt-1 text-xs text-zinc-500">
                            Leave empty to use the subscription plan limit
                            instead. Currently using {workspace.shops_count}{' '}
                            shop(s).
                        </p>
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-md border border-zinc-200 px-4 py-2 text-sm font-medium text-zinc-600 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-700 disabled:opacity-50"
                        >
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function ModulesModal({
    workspace,
    onClose,
}: {
    workspace: Workspace;
    onClose: () => void;
}) {
    const { data, setData, put, processing } = useForm({
        inventory_module_enabled: workspace.inventory_module_enabled,
        finance_module_enabled: workspace.finance_module_enabled,
        products_module_enabled: workspace.products_module_enabled,
        teams_module_enabled: workspace.teams_module_enabled,
        checklist_module_enabled: workspace.checklist_module_enabled,
        csr_module_enabled: workspace.csr_module_enabled,
        rmo_module_enabled: workspace.rmo_module_enabled,
        leaderboard_module_enabled: workspace.leaderboard_module_enabled,
        botcake_module_enabled: workspace.botcake_module_enabled,
        creatives_module_enabled: workspace.creatives_module_enabled,
        meta_ads_module_enabled: workspace.meta_ads_module_enabled,
        gencys_module_enabled: workspace.gencys_module_enabled,
        is_gencys_partner: workspace.is_gencys_partner,
        sales_marketing_dashboard_module_enabled:
            workspace.sales_marketing_dashboard_module_enabled,
        video_editor_dashboard_module_enabled:
            workspace.video_editor_dashboard_module_enabled,
        csr_dashboard_module_enabled: workspace.csr_dashboard_module_enabled,
        sim_gateway_module_enabled: workspace.sim_gateway_module_enabled,
        ad_spend_goals_module_enabled: workspace.ad_spend_goals_module_enabled,
        billing_module_enabled: workspace.billing_module_enabled,
        courses_module_enabled: workspace.courses_module_enabled,
        welle_module_enabled: workspace.welle_module_enabled,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/admin/workspaces/${workspace.slug}/modules`, {
            onSuccess: () => onClose(),
            preserveScroll: true,
        });
    }

    const enabledTotal = MODULE_FIELDS.filter((f) => data[f.key]).length;

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            onClick={onClose}
        >
            <div
                className="flex max-h-[88vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-zinc-900"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="flex items-center justify-between border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
                    <div className="flex items-center gap-3">
                        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                            <Boxes className="h-4 w-4" />
                        </div>
                        <div>
                            <h3 className="text-base font-semibold text-zinc-900 dark:text-zinc-100">
                                Toggle Modules
                            </h3>
                            <p className="text-xs text-zinc-500">
                                {workspace.name} · {enabledTotal} of{' '}
                                {MODULE_FIELDS.length} enabled
                            </p>
                        </div>
                    </div>
                    <button
                        onClick={onClose}
                        className="rounded-md p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <form
                    onSubmit={handleSubmit}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    {/* Scrollable grouped body */}
                    <div className="min-h-0 flex-1 space-y-6 overflow-y-auto px-6 py-5">
                        {MODULE_GROUPS.map((group) => {
                            const groupEnabled = group.keys.filter(
                                (k) => data[k],
                            ).length;
                            return (
                                <section key={group.title}>
                                    <div className="mb-2.5 flex items-end justify-between">
                                        <div>
                                            <h4 className="text-[11px] font-semibold tracking-wider text-zinc-500 uppercase dark:text-zinc-400">
                                                {group.title}
                                            </h4>
                                            <p className="text-[11px] text-zinc-400 dark:text-zinc-500">
                                                {group.description}
                                            </p>
                                        </div>
                                        <span className="font-mono text-[10px] text-zinc-400 dark:text-zinc-500">
                                            {groupEnabled}/{group.keys.length}
                                        </span>
                                    </div>
                                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        {group.keys.map((key) => {
                                            const field = MODULE_BY_KEY[key];
                                            return (
                                                <label
                                                    key={key}
                                                    className={`flex cursor-pointer items-center justify-between gap-3 rounded-lg px-3 py-2.5 transition-colors ${
                                                        data[key]
                                                            ? 'bg-brand-50/50 dark:bg-brand-500/5'
                                                            : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50'
                                                    }`}
                                                >
                                                    <div className="min-w-0 flex-1">
                                                        <div className="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                            {field.label}
                                                        </div>
                                                        <div className="text-xs text-zinc-500 dark:text-zinc-400">
                                                            {field.description}
                                                        </div>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        role="switch"
                                                        aria-checked={data[key]}
                                                        onClick={() =>
                                                            setData(
                                                                key,
                                                                !data[key],
                                                            )
                                                        }
                                                        className={`relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors ${
                                                            data[key]
                                                                ? 'bg-brand-600'
                                                                : 'bg-zinc-200 dark:bg-zinc-700'
                                                        }`}
                                                    >
                                                        <span
                                                            className={`inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform ${
                                                                data[key]
                                                                    ? 'translate-x-5'
                                                                    : 'translate-x-1'
                                                            }`}
                                                        />
                                                    </button>
                                                </label>
                                            );
                                        })}
                                    </div>
                                </section>
                            );
                        })}
                    </div>

                    {/* Sticky footer */}
                    <div className="flex justify-end gap-2 border-t border-zinc-200 px-6 py-4 dark:border-zinc-800">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-md border border-zinc-200 px-4 py-2 text-sm font-medium text-zinc-600 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-700 disabled:opacity-50"
                        >
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
