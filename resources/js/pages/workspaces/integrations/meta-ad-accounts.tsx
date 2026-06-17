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
import axios from 'axios';
import clsx from 'clsx';
import { omit } from 'lodash';
import { Facebook, Search, Star } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface AdAccount {
    id: number;
    name: string;
    business_name: string | null;
    currency: string | null;
    country_code: string | null;
    account_status: number | null;
    last_synced_at: string | null;
    uses_system_user: boolean;
    active_sync: boolean;
    meta_users?: { id: number; name: string }[];
}

interface MetaUserOption {
    id: number;
    name: string;
}

interface Props {
    workspace: Workspace;
    adAccounts: PaginatedData<AdAccount>;
    metaUsers: MetaUserOption[];
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: { search?: string; meta_user?: string };
        showAll?: boolean;
    };
}

const ACCOUNT_STATUS: Record<
    number,
    { label: string; cls: string; dot: string }
> = {
    1: {
        label: 'Active',
        cls: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
        dot: 'bg-emerald-500',
    },
    2: {
        label: 'Disabled',
        cls: 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400',
        dot: 'bg-red-400',
    },
    3: {
        label: 'Unsettled',
        cls: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        dot: 'bg-amber-400',
    },
    7: {
        label: 'Pending Risk Review',
        cls: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        dot: 'bg-amber-400',
    },
    8: {
        label: 'Pending Settlement',
        cls: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        dot: 'bg-amber-400',
    },
    9: {
        label: 'In Grace Period',
        cls: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        dot: 'bg-amber-400',
    },
    100: {
        label: 'Pending Closure',
        cls: 'bg-stone-100 text-stone-600 dark:bg-zinc-800 dark:text-zinc-400',
        dot: 'bg-stone-400',
    },
    101: {
        label: 'Closed',
        cls: 'bg-stone-100 text-stone-600 dark:bg-zinc-800 dark:text-zinc-400',
        dot: 'bg-stone-400',
    },
};

function StatusBadge({ status }: { status: number | null }) {
    if (status == null) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full bg-stone-100 px-2 py-0.5 font-mono text-[10px] tracking-wide text-stone-500 uppercase dark:bg-zinc-800 dark:text-zinc-500">
                <span className="h-1 w-1 rounded-full bg-stone-300" />
                Unknown
            </span>
        );
    }
    const cfg = ACCOUNT_STATUS[status] ?? {
        label: `Status ${status}`,
        cls: 'bg-stone-100 text-stone-600 dark:bg-zinc-800 dark:text-zinc-400',
        dot: 'bg-stone-400',
    };
    return (
        <span
            className={clsx(
                'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 font-mono text-[10px] tracking-wide uppercase',
                cfg.cls,
            )}
        >
            <span className={clsx('h-1 w-1 rounded-full', cfg.dot)} />
            {cfg.label}
        </span>
    );
}

function formatRelative(ts: string | null) {
    if (!ts) return 'Never';
    const d = new Date(ts);
    const diff = (Date.now() - d.getTime()) / 1000;
    if (diff < 60) return `${Math.round(diff)}s ago`;
    if (diff < 3600) return `${Math.round(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.round(diff / 3600)}h ago`;
    return `${Math.round(diff / 86400)}d ago`;
}

export default function MetaAdAccounts({
    workspace,
    adAccounts,
    metaUsers,
    query,
}: Props) {
    const indexUrl = `/workspaces/${workspace.slug}/integrations/meta/ad-accounts`;

    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );
    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [metaUserId, setMetaUserId] = useState(
        query?.filter?.meta_user ?? '',
    );
    const [showAll, setShowAll] = useState(query?.showAll ?? false);
    const [syncToggles, setSyncToggles] = useState<Record<number, boolean>>(
        () =>
            Object.fromEntries(
                adAccounts.data.map((a) => [a.id, a.active_sync]),
            ),
    );

    useEffect(() => {
        setSyncToggles(
            Object.fromEntries(
                adAccounts.data.map((a) => [a.id, a.active_sync]),
            ),
        );
    }, [adAccounts.data]);

    const toggleSync = (adAccount: AdAccount) => {
        const next = !syncToggles[adAccount.id];
        setSyncToggles((prev) => ({ ...prev, [adAccount.id]: next }));
        axios
            .patch(
                `/workspaces/${workspace.slug}/integrations/meta/ad-accounts/${adAccount.id}/toggle-sync`,
            )
            .catch(() => {
                setSyncToggles((prev) => ({ ...prev, [adAccount.id]: !next }));
            });
    };

    const navigate = (overrides: Record<string, unknown> = {}) => {
        router.get(
            indexUrl,
            {
                sort: query?.sort,
                'filter[search]': searchValue || undefined,
                'filter[meta_user]': metaUserId || undefined,
                show_all: showAll ? 1 : undefined,
                page: 1,
                per_page: query?.perPage ?? adAccounts.per_page,
                ...overrides,
            },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['adAccounts', 'query'],
            },
        );
    };

    useEffect(() => {
        const t = setTimeout(
            () => navigate({ page: searchValue ? 1 : (query?.page ?? 1) }),
            400,
        );
        return () => clearTimeout(t);
    }, [searchValue]); // eslint-disable-line react-hooks/exhaustive-deps

    const handleShowAllToggle = () => {
        const next = !showAll;
        setShowAll(next);
        navigate({ show_all: next ? 1 : undefined, page: 1 });
    };

    const handleMetaUserChange = (value: string) => {
        const next = value === 'all' ? '' : value;
        setMetaUserId(next);
        navigate({ 'filter[meta_user]': next || undefined, page: 1 });
    };

    const columns: ColumnDef<AdAccount>[] = [
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Account" />
            ),
            cell: ({ row }) => (
                <div className="flex flex-col gap-0.5">
                    <span className="flex items-center gap-1 text-[12px] font-medium text-gray-700 dark:text-gray-200">
                        {row.original.name}
                        {row.original.uses_system_user && (
                            <Star className="h-3 w-3 shrink-0 fill-amber-400 text-amber-400" />
                        )}
                    </span>
                    {row.original.business_name && (
                        <span className="text-[11px] text-gray-400 dark:text-gray-500">
                            {row.original.business_name}
                        </span>
                    )}
                    <span className="font-mono text-[10px] text-gray-300 dark:text-gray-600">
                        {row.original.id}
                    </span>
                </div>
            ),
        },
        {
            accessorKey: 'account_status',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Status" />
            ),
            cell: ({ row }) => (
                <StatusBadge status={row.original.account_status} />
            ),
        },
        {
            accessorKey: 'currency',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Currency" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                    {row.original.currency ?? '—'}
                </span>
            ),
        },
        {
            accessorKey: 'country_code',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Country" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                    {row.original.country_code ?? '—'}
                </span>
            ),
        },
        {
            id: 'meta_users',
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Connected via"
                    enabled={false}
                />
            ),
            cell: ({ row }) => {
                const users = row.original.meta_users ?? [];
                if (users.length === 0)
                    return (
                        <span className="text-gray-300 dark:text-gray-600">
                            —
                        </span>
                    );
                return (
                    <div className="flex flex-wrap gap-1">
                        {users.map((u) => (
                            <span
                                key={u.id}
                                className="inline-flex items-center gap-1 rounded-md bg-stone-100 px-1.5 py-0.5 text-[10px] text-gray-600 dark:bg-zinc-800 dark:text-gray-400"
                            >
                                <Facebook className="h-2.5 w-2.5" />
                                {u.name}
                            </span>
                        ))}
                    </div>
                );
            },
        },
        {
            accessorKey: 'last_synced_at',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Last Synced" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                    {formatRelative(row.original.last_synced_at)}
                </span>
            ),
        },
        {
            id: 'active_sync',
            header: ({ column }) => (
                <SortableHeader column={column} title="Sync" enabled={false} />
            ),
            cell: ({ row }) => {
                const enabled =
                    syncToggles[row.original.id] ?? row.original.active_sync;
                return (
                    <button
                        type="button"
                        role="switch"
                        aria-checked={enabled}
                        onClick={() => toggleSync(row.original)}
                        className={clsx(
                            'relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 focus-visible:outline-none',
                            enabled
                                ? 'bg-emerald-500'
                                : 'bg-stone-200 dark:bg-zinc-700',
                        )}
                    >
                        <span
                            className={clsx(
                                'pointer-events-none inline-block h-3.5 w-3.5 rounded-full bg-white shadow-sm transition-transform',
                                enabled ? 'translate-x-4' : 'translate-x-0.5',
                            )}
                        />
                    </button>
                );
            },
        },
    ];

    return (
        <AppLayout>
            <Head title="Meta Ads · Ad Accounts" />

            <div className="w-full space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Ad Accounts"
                    description="All Meta ad accounts visible through your connected Facebook users."
                ></PageHeader>

                <div className="flex items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            type="text"
                            placeholder="Search ad accounts..."
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                        />
                    </div>

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

                    <button
                        type="button"
                        onClick={handleShowAllToggle}
                        className={clsx(
                            'flex h-9 items-center gap-1.5 rounded-[10px] border px-3 font-mono text-[11px] transition-colors',
                            showAll
                                ? 'border-emerald-500/40 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-400'
                                : 'border-black/6 bg-stone-100 text-gray-500 hover:border-black/10 hover:text-gray-700 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-400 dark:hover:border-white/10 dark:hover:text-gray-200',
                        )}
                    >
                        {showAll ? 'Showing all' : 'Active sync only'}
                    </button>
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={adAccounts.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(adAccounts, ['data']) }}
                        onFetch={(params) => {
                            navigate({
                                sort: params?.sort,
                                page: params?.page ?? 1,
                                per_page:
                                    params?.per_page ??
                                    query?.perPage ??
                                    adAccounts.per_page,
                            });
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
