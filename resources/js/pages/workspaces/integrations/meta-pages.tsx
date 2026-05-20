import PageHeader from '@/components/common/PageHeader';
import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { currencyFormatter } from '@/lib/utils';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import {
    Activity,
    BookOpenIcon,
    Database,
    ExternalLink,
    Facebook,
    Search,
    Wallet,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface PageRow {
    id: number;
    name: string | null;
    facebook_url: string | null;
    shop_id: number | null;
    owner_id: number | null;
    orders_last_synced_at: string | null;
    daily_budget: number | string;
    lifetime_budget: number | string;
    ad_sets_count: number;
}

interface Props {
    workspace: Workspace;
    pages: PaginatedData<PageRow>;
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: { search?: string };
    };
}

function formatMoney(value: number | string | null | undefined) {
    const n = typeof value === 'string' ? parseFloat(value) : (value ?? 0);
    if (!n) return '—';
    return currencyFormatter(n);
}

function StatCard({
    label,
    value,
    icon: Icon,
}: {
    label: string;
    value: string | number;
    icon: typeof Database;
}) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-5 transition-colors hover:border-black/10 dark:border-white/6 dark:bg-zinc-900 dark:hover:border-white/10">
            <div className="flex items-start justify-between">
                <div>
                    <p className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                        {label}
                    </p>
                    <p className="mt-2 text-2xl font-semibold tracking-tight text-gray-700 dark:text-gray-200">
                        {value}
                    </p>
                </div>
                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-400">
                    <Icon className="h-4 w-4" />
                </div>
            </div>
        </div>
    );
}

export default function MetaPages({ workspace, pages, query }: Props) {
    const indexUrl = `/workspaces/${workspace.slug}/integrations/meta/pages`;

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
                    per_page: query?.perPage ?? pages.per_page,
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['pages'],
                },
            );
        }, 400);
        return () => clearTimeout(t);
    }, [searchValue]); // eslint-disable-line react-hooks/exhaustive-deps

    const totalDailyBudget = pages.data.reduce(
        (sum, p) => sum + parseFloat(String(p.daily_budget || 0)),
        0,
    );
    const pagesWithAds = pages.data.filter((p) => p.ad_sets_count > 0).length;

    const columns: ColumnDef<PageRow>[] = [
        {
            accessorKey: 'id',
            enableSorting: false,
            header: ({ column }) => (
                <SortableHeader column={column} title="ID" enabled={false} />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                    {row.original.id}
                </span>
            ),
        },
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Page" />
            ),
            cell: ({ row }) => (
                <div className="flex items-center gap-3">
                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-stone-100 dark:bg-zinc-800">
                        <Facebook className="h-3.5 w-3.5 text-gray-500 dark:text-gray-400" />
                    </div>
                    <div className="flex flex-col gap-0.5">
                        <span className="text-[12px] font-medium text-gray-700 dark:text-gray-200">
                            {row.original.name ?? (
                                <span className="text-gray-300 italic dark:text-gray-600">
                                    Unnamed
                                </span>
                            )}
                        </span>
                        {row.original.facebook_url && (
                            <a
                                href={row.original.facebook_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1 font-mono text-[10px] text-emerald-600 hover:underline dark:text-emerald-400"
                            >
                                <ExternalLink className="h-2.5 w-2.5" />
                                Visit
                            </a>
                        )}
                    </div>
                </div>
            ),
        },
        {
            accessorKey: 'ad_sets_count',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Ad Sets" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-gray-700 dark:text-gray-300">
                    {row.original.ad_sets_count}
                </span>
            ),
        },
        {
            accessorKey: 'daily_budget',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Daily Budget" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[12px] font-medium text-gray-700 dark:text-gray-300">
                    {formatMoney(row.original.daily_budget)}
                </span>
            ),
        },
        {
            accessorKey: 'lifetime_budget',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Lifetime Budget" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-gray-500 dark:text-gray-400">
                    {formatMoney(row.original.lifetime_budget)}
                </span>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title="Meta Ads · Pages" />

            <div className="w-full space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Pages"
                    description="Workspace pages with current rolled-up Meta ad budget across the ad sets that touch each page."
                >
                    <Button variant="outline" size="sm" asChild>
                        <a
                            href={`/workspaces/${workspace.slug}/integrations/meta/ad-accounts`}
                        >
                            <Database className="mr-2 h-4 w-4" />
                            Ad Accounts
                        </a>
                    </Button>
                    <Button variant="outline" size="sm" asChild>
                        <a
                            href={`/workspaces/${workspace.slug}/integrations/meta/health`}
                        >
                            <Activity className="mr-2 h-4 w-4" />
                            Sync Health
                        </a>
                    </Button>
                </PageHeader>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <StatCard
                        label="Total Pages"
                        value={pages.total}
                        icon={BookOpenIcon}
                    />
                    <StatCard
                        label="Pages with active ads"
                        value={pagesWithAds}
                        icon={Facebook}
                    />
                    <StatCard
                        label="Total daily budget (this page)"
                        value={formatMoney(totalDailyBudget)}
                        icon={Wallet}
                    />
                </div>

                <div className="flex items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            type="text"
                            placeholder="Search pages..."
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                        />
                    </div>
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={pages.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(pages, ['data']) }}
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
                                        pages.per_page,
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
