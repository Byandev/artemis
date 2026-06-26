import PageHeader from '@/components/common/PageHeader';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import clsx from 'clsx';
import { omit } from 'lodash';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    Clock,
    Database,
    XCircle,
} from 'lucide-react';
import { useMemo, useState } from 'react';

interface EntityCell {
    entity_type: string;
    status: string | null;
    records_synced: number | null;
    started_at: string | null;
    finished_at: string | null;
    error_message: string | null;
}

interface AccountSummary {
    account: {
        id: string;
        name: string;
        business_name: string | null;
        last_synced_at: string | null;
    };
    entities: EntityCell[];
}

interface RecentRun {
    id: number;
    entity_type: string;
    account_name: string | null;
    scope_id: number | string | null;
    status: string;
    records_synced: number | null;
    started_at: string | null;
    finished_at: string | null;
    duration_seconds: number | null;
    error_message: string | null;
}

interface MetaUserOption {
    id: number;
    name: string;
}

interface Props {
    workspace: Workspace;
    summary: AccountSummary[];
    recent: PaginatedData<RecentRun>;
    entityTypes: string[];
    metaUsers: MetaUserOption[];
    failedLast24h: number;
    totalRuns24h: number;
    successRuns24h: number;
    lastSuccessfulRun: {
        entity_type: string;
        account_name: string | null;
        started_at: string | null;
    } | null;
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: {
            status?: string;
            entity_type?: string;
            scope_id?: string;
            meta_user?: string;
        };
    };
}

const ENTITY_LABELS: Record<string, string> = {
    campaigns: 'Campaigns',
    ad_sets: 'Ad sets',
    ads: 'Ads',
    ad_creatives: 'Creatives',
    ad_insights: 'Insights',
    budget_snapshots: 'Budgets',
    ad_accounts: 'Ad accounts',
};

function StatusPill({ status }: { status: string | null }) {
    if (!status) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full bg-stone-100 px-2 py-0.5 font-mono text-[10px] tracking-wide text-stone-500 uppercase dark:bg-zinc-800 dark:text-zinc-500">
                <span className="h-1 w-1 rounded-full bg-stone-300" />
                Never
            </span>
        );
    }
    const config: Record<
        string,
        { Icon: typeof CheckCircle2; cls: string; label: string; dot: string }
    > = {
        success: {
            Icon: CheckCircle2,
            cls: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
            dot: 'bg-emerald-500',
            label: 'Success',
        },
        failed: {
            Icon: XCircle,
            cls: 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400',
            dot: 'bg-red-400',
            label: 'Failed',
        },
        rate_limited: {
            Icon: AlertTriangle,
            cls: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
            dot: 'bg-amber-400',
            label: 'Throttled',
        },
        running: {
            Icon: Clock,
            cls: 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',
            dot: 'bg-blue-400',
            label: 'Running',
        },
    };
    const c = config[status] ?? config.failed;

    return (
        <span
            className={clsx(
                'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 font-mono text-[10px] tracking-wide uppercase',
                c.cls,
            )}
        >
            <span className={clsx('h-1 w-1 rounded-full', c.dot)} />
            {c.label}
        </span>
    );
}

function formatRelative(ts: string | null) {
    if (!ts) return '—';
    const d = new Date(ts);
    const diff = (Date.now() - d.getTime()) / 1000;
    if (diff < 60) return `${Math.round(diff)}s ago`;
    if (diff < 3600) return `${Math.round(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.round(diff / 3600)}h ago`;
    return `${Math.round(diff / 86400)}d ago`;
}

/**
 * The "When" column sorts the real `started_at` timestamp, but the column reads
 * as "how recently" — so product wants its arrows by recency: ascending = most
 * recent first (secs → days), descending = oldest first (days → secs). That's
 * the inverse of a raw timestamp sort, so we flip just this column's direction
 * both when sending the sort to the server and when reading it back into the
 * arrow state (the function is symmetric, so it serves both directions).
 */
function flipWhenSort(sort?: string | number | null): string | undefined {
    if (typeof sort !== 'string' || sort === '') return undefined;

    const flipped = sort
        .split(',')
        .map((raw) => {
            const part = raw.trim();
            if (part === 'started_at') return '-started_at';
            if (part === '-started_at') return 'started_at';
            return part;
        })
        .filter(Boolean)
        .join(',');

    return flipped || undefined;
}

function formatDuration(seconds: number | null) {
    if (seconds == null) return '—';
    if (seconds > 60) return `${(seconds / 60).toFixed(2)}m`;
    return `${seconds}s`;
}

function StatCard({
    label,
    value,
    icon: Icon,
    tone = 'neutral',
}: {
    label: string;
    value: string | number;
    icon: typeof Activity;
    tone?: 'neutral' | 'success' | 'warning' | 'danger';
}) {
    const tones = {
        neutral: 'text-gray-700 dark:text-gray-200',
        success: 'text-emerald-600 dark:text-emerald-400',
        warning: 'text-amber-600 dark:text-amber-400',
        danger: 'text-red-600 dark:text-red-400',
    };
    const iconBg = {
        neutral:
            'bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-400',
        success:
            'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400',
        warning:
            'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
        danger: 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400',
    };
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-5 transition-colors hover:border-black/10 dark:border-white/6 dark:bg-zinc-900 dark:hover:border-white/10">
            <div className="flex items-start justify-between">
                <div>
                    <p className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                        {label}
                    </p>
                    <p
                        className={clsx(
                            'mt-2 text-2xl font-semibold tracking-tight',
                            tones[tone],
                        )}
                    >
                        {value}
                    </p>
                </div>
                <div
                    className={clsx(
                        'flex h-9 w-9 items-center justify-center rounded-xl',
                        iconBg[tone],
                    )}
                >
                    <Icon className="h-4 w-4" />
                </div>
            </div>
        </div>
    );
}

export default function MetaHealthDashboard({
    workspace,
    summary,
    recent,
    entityTypes,
    metaUsers,
    failedLast24h,
    totalRuns24h,
    successRuns24h,
    lastSuccessfulRun,
    query,
}: Props) {
    const indexUrl = `/workspaces/${workspace.slug}/integrations/meta/health`;

    // Reflect the server sort back into the arrow state, inverting the "When"
    // column so its arrow matches the recency order the user sees.
    const initialSorting = useMemo(
        () => toFrontendSort(flipWhenSort(query?.sort) ?? null),
        [query?.sort],
    );
    const [metaUserId, setMetaUserId] = useState(
        query?.filter?.meta_user ?? '',
    );

    const navigate = (overrides: Record<string, unknown> = {}) => {
        router.get(
            indexUrl,
            {
                sort: query?.sort,
                'filter[meta_user]': metaUserId || undefined,
                page: 1,
                per_page: query?.perPage ?? recent.per_page,
                ...overrides,
            },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
            },
        );
    };

    const handleMetaUserChange = (value: string) => {
        const next = value === 'all' ? '' : value;
        setMetaUserId(next);
        navigate({ 'filter[meta_user]': next || undefined, page: 1 });
    };
    const successRate24h =
        totalRuns24h > 0
            ? Math.round((successRuns24h / totalRuns24h) * 100)
            : 0;

    const summaryColumns: ColumnDef<AccountSummary>[] = [
        {
            id: 'account',
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Ad Account"
                    enabled={false}
                />
            ),
            cell: ({ row }) => (
                <div className="flex flex-col gap-0.5">
                    <span className="text-[12px] font-medium text-gray-700 dark:text-gray-200">
                        {row.original.account.name}
                    </span>
                    {row.original.account.business_name && (
                        <span className="text-[11px] text-gray-400 dark:text-gray-500">
                            {row.original.account.business_name}
                        </span>
                    )}
                    <span className="font-mono text-[10px] text-gray-300 dark:text-gray-600">
                        {row.original.account.id}
                    </span>
                </div>
            ),
        },
        ...entityTypes.map<ColumnDef<AccountSummary>>((entity) => ({
            id: entity,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title={ENTITY_LABELS[entity] ?? entity}
                    enabled={false}
                />
            ),
            cell: ({ row }) => {
                const cell = row.original.entities.find(
                    (e) => e.entity_type === entity,
                );
                if (!cell) return <StatusPill status={null} />;
                return (
                    <div className="flex flex-col gap-1">
                        <StatusPill status={cell.status} />
                        <div className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                            {formatRelative(cell.started_at)}
                        </div>
                        {cell.records_synced != null && (
                            <div className="font-mono text-[10px] text-gray-300 dark:text-gray-600">
                                {cell.records_synced.toLocaleString()} rows
                            </div>
                        )}
                        {cell.error_message && (
                            <div
                                className="max-w-[180px] truncate font-mono text-[10px] text-red-500 dark:text-red-400"
                                title={cell.error_message}
                            >
                                {cell.error_message}
                            </div>
                        )}
                    </div>
                );
            },
        })),
    ];

    const recentColumns: ColumnDef<RecentRun>[] = [
        {
            accessorKey: 'started_at',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="When" />
            ),
            cell: ({ row }) => (
                <div className="flex flex-col">
                    <span className="text-[12px] font-medium text-gray-700 dark:text-gray-300">
                        {formatRelative(row.original.started_at)}
                    </span>
                    <span className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                        {row.original.started_at
                            ? new Date(row.original.started_at).toLocaleString()
                            : '—'}
                    </span>
                </div>
            ),
        },
        {
            accessorKey: 'account_name',
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Account"
                    enabled={false}
                />
            ),
            cell: ({ row }) =>
                row.original.account_name ? (
                    <span className="text-[12px] text-gray-700 dark:text-gray-300">
                        {row.original.account_name}
                    </span>
                ) : (
                    <span className="text-[11px] text-gray-300 italic dark:text-gray-600">
                        workspace-wide
                    </span>
                ),
        },
        {
            accessorKey: 'entity_type',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Entity" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                    {ENTITY_LABELS[row.original.entity_type] ??
                        row.original.entity_type}
                </span>
            ),
        },
        {
            accessorKey: 'status',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Status" />
            ),
            cell: ({ row }) => <StatusPill status={row.original.status} />,
        },
        {
            accessorKey: 'records_synced',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Records" />
            ),
            cell: ({ row }) =>
                row.original.records_synced != null ? (
                    <span className="font-mono text-[12px] text-gray-700 dark:text-gray-300">
                        {row.original.records_synced.toLocaleString()}
                    </span>
                ) : (
                    <span className="text-gray-300 dark:text-gray-600">—</span>
                ),
        },
        {
            accessorKey: 'duration_seconds',
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Duration"
                    enabled={false}
                />
            ),
            cell: ({ row }) =>
                row.original.duration_seconds != null ? (
                    <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                        {formatDuration(row.original.duration_seconds)}
                    </span>
                ) : (
                    <span className="text-gray-300 dark:text-gray-600">—</span>
                ),
        },
        {
            accessorKey: 'error_message',
            header: ({ column }) => (
                <SortableHeader column={column} title="Error" enabled={false} />
            ),
            cell: ({ row }) =>
                row.original.error_message ? (
                    <span
                        className="block max-w-md truncate font-mono text-[11px] text-red-500 dark:text-red-400"
                        title={row.original.error_message}
                    >
                        {row.original.error_message}
                    </span>
                ) : (
                    <span className="text-gray-300 dark:text-gray-600">—</span>
                ),
        },
    ];

    return (
        <AppLayout>
            <Head title="Meta Sync Health" />

            <div className="w-full space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Meta Sync Health"
                    description="Monitor sync status, throughput, and failures across every connected ad account."
                ></PageHeader>

                <div className="flex items-center gap-2">
                    <Select
                        value={metaUserId || 'all'}
                        onValueChange={handleMetaUserChange}
                    >
                        <SelectTrigger className="h-9 w-[200px] font-mono text-[11px]">
                            <SelectValue placeholder="All Meta users" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Meta users</SelectItem>
                            {metaUsers.map((u) => (
                                <SelectItem key={u.id} value={String(u.id)}>
                                    {u.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Connected Accounts"
                        value={summary.length}
                        icon={Database}
                    />
                    <StatCard
                        label="Runs (24h)"
                        value={totalRuns24h}
                        icon={Activity}
                    />
                    <StatCard
                        label="Success rate (24h)"
                        value={`${successRate24h}%`}
                        icon={CheckCircle2}
                        tone={
                            successRate24h >= 95
                                ? 'success'
                                : successRate24h >= 80
                                  ? 'warning'
                                  : 'danger'
                        }
                    />
                    <StatCard
                        label="Failed / Throttled (24h)"
                        value={failedLast24h}
                        icon={AlertTriangle}
                        tone={
                            failedLast24h === 0
                                ? 'success'
                                : failedLast24h < 5
                                  ? 'warning'
                                  : 'danger'
                        }
                    />
                </div>

                {lastSuccessfulRun && (
                    <div className="rounded-[14px] border border-emerald-100 bg-emerald-50/40 px-4 py-3 dark:border-emerald-500/15 dark:bg-emerald-500/5">
                        <p className="font-mono text-[11px] text-emerald-700 dark:text-emerald-400">
                            Last successful run:{' '}
                            {ENTITY_LABELS[lastSuccessfulRun.entity_type] ??
                                lastSuccessfulRun.entity_type}
                            {lastSuccessfulRun.account_name &&
                                ` · ${lastSuccessfulRun.account_name}`}{' '}
                            · {formatRelative(lastSuccessfulRun.started_at)}
                        </p>
                    </div>
                )}

                <div>
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="font-mono text-[10px] font-medium tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500">
                            Per-account status
                        </h2>
                        <span className="font-mono text-[10px] text-gray-300 dark:text-gray-600">
                            {summary.length} account
                            {summary.length === 1 ? '' : 's'}
                        </span>
                    </div>
                    <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                        <DataTable columns={summaryColumns} data={summary} />
                    </div>
                </div>

                <div>
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="font-mono text-[10px] font-medium tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500">
                            Recent runs
                        </h2>
                        <span className="font-mono text-[10px] text-gray-300 dark:text-gray-600">
                            {recent.total.toLocaleString()} total
                        </span>
                    </div>
                    <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                        <DataTable
                            columns={recentColumns}
                            data={recent.data || []}
                            initialSorting={initialSorting}
                            meta={{ ...omit(recent, ['data']) }}
                            onFetch={(params) => {
                                navigate({
                                    // Invert the "When" column so ascending shows
                                    // the most recent run first (secs → days).
                                    sort: flipWhenSort(params?.sort),
                                    page: params?.page ?? 1,
                                    per_page:
                                        params?.per_page ??
                                        query?.perPage ??
                                        recent.per_page,
                                });
                            }}
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
