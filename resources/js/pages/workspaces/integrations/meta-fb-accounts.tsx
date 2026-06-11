import PageHeader from '@/components/common/PageHeader';
import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import {
    Facebook,
    Mail,
    MoreHorizontal,
    RefreshCw,
    Search,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface MetaUser {
    id: number;
    name: string;
    email: string | null;
    token_expires_at: string | null;
    last_synced_at: string | null;
    ad_accounts_count: number;
}

interface Props {
    workspace: Workspace;
    metaUsers: PaginatedData<MetaUser>;
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: { search?: string };
    };
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

export default function MetaFbAccounts({
    workspace,
    metaUsers,
    query,
}: Props) {
    const connectUrl = `/workspaces/${workspace.slug}/integrations/meta/connect`;
    const indexUrl = `/workspaces/${workspace.slug}/integrations/meta`;

    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );
    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');

    useEffect(() => {
        const t = setTimeout(() => {
            router.get(
                indexUrl,
                {
                    sort: query?.sort,
                    'filter[search]': searchValue || undefined,
                    page: searchValue ? 1 : (query?.page ?? 1),
                    per_page: query?.perPage ?? metaUsers.per_page,
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['metaUsers'],
                },
            );
        }, 400);
        return () => clearTimeout(t);
    }, [searchValue]); // eslint-disable-line react-hooks/exhaustive-deps

    const sync = (metaUserId: number) => {
        router.post(
            `${indexUrl}/users/${metaUserId}/sync-ad-accounts`,
            {},
            { preserveScroll: true },
        );
    };

    const columns: ColumnDef<MetaUser>[] = [
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Name" />
            ),
            cell: ({ row }) => (
                <div className="flex items-center gap-3">
                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-stone-100 dark:bg-zinc-800">
                        <Facebook className="h-3.5 w-3.5 text-gray-500 dark:text-gray-400" />
                    </div>
                    <div className="flex flex-col gap-0.5">
                        <span className="text-[12px] font-medium text-gray-700 dark:text-gray-200">
                            {row.original.name}
                        </span>
                        <span className="font-mono text-[10px] text-gray-300 dark:text-gray-600">
                            {row.original.id}
                        </span>
                    </div>
                </div>
            ),
        },
        {
            accessorKey: 'email',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Email" />
            ),
            cell: ({ row }) =>
                row.original.email ? (
                    <span className="flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                        <Mail className="h-3 w-3" />
                        {row.original.email}
                    </span>
                ) : (
                    <span className="text-gray-300 dark:text-gray-600">—</span>
                ),
        },
        {
            accessorKey: 'ad_accounts_count',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Ad Accounts" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-gray-700 dark:text-gray-300">
                    {row.original.ad_accounts_count}
                </span>
            ),
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
            accessorKey: 'token_expires_at',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Token Expires" />
            ),
            cell: ({ row }) =>
                row.original.token_expires_at ? (
                    <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                        {new Date(
                            row.original.token_expires_at,
                        ).toLocaleDateString()}
                    </span>
                ) : (
                    <span className="text-gray-300 dark:text-gray-600">—</span>
                ),
        },
        {
            id: 'actions',
            cell: ({ row }) => (
                <div className="flex justify-end">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 transition-all hover:border-black/12 hover:bg-stone-100 hover:text-gray-600 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-500 dark:hover:border-white/12 dark:hover:bg-zinc-700 dark:hover:text-gray-300">
                                <MoreHorizontal className="h-3.5 w-3.5" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-48">
                            <DropdownMenuItem
                                onClick={() => sync(row.original.id)}
                            >
                                <RefreshCw />
                                Sync ad accounts
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title="Meta Ads · FB Account" />

            <div className="w-full space-y-6 p-4 md:p-6">
                <PageHeader
                    title="FB Account"
                    description="Facebook users that have authorized this workspace to access their ad accounts."
                >
                    <Button
                        asChild
                        className="bg-emerald-600 text-white hover:bg-emerald-700"
                    >
                        <a className={'font-mono! text-sm'} href={connectUrl}>
                            <Facebook className="mr-2 h-4 w-4" />
                            Connect Meta Account
                        </a>
                    </Button>
                </PageHeader>

                <div className="flex items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            type="text"
                            placeholder="Search FB users..."
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                        />
                    </div>
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={metaUsers.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(metaUsers, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                indexUrl,
                                {
                                    sort: params?.sort,
                                    'filter[search]': searchValue || undefined,
                                    page: params?.page ?? 1,
                                    per_page:
                                        params?.per_page ??
                                        query?.perPage ??
                                        metaUsers.per_page,
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
        </AppLayout>
    );
}
