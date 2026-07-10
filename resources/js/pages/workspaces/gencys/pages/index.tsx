import PageHeader from '@/components/common/PageHeader';
import {
    PlatformBadge,
    StatusBadge,
} from '@/components/gencys/gencys-page-badges';
import GencysPagesFilters from '@/components/gencys/gencys-pages-filters';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { cn } from '@/lib/utils';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { debounce, omit } from 'lodash';
import { RefreshCw } from 'lucide-react';
import moment from 'moment';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

interface GencysPage {
    id: number;
    page_id: number | null;
    date_created: string | null;
    name: string | null;
    owner: string | null;
    intern_and_brand: string | null;
    gencys_intern_id: number | null;
    status: string | null;
    platform: string | null;
}

interface Filter {
    search?: string;
    status?: string;
    platform?: string;
}

interface Props {
    workspace: Workspace;
    pages: PaginatedData<GencysPage>;
    statuses: string[];
    platforms: string[];
    query: {
        sort: string;
        perPage: number | null;
        filter: Filter;
    };
}

export default function GencysPagesIndex({
    workspace,
    pages,
    statuses,
    platforms,
    query,
}: Props) {
    const { flash } = usePage().props as {
        flash?: { success?: string; error?: string };
    };
    const canSync = usePermission(PERMISSIONS.ViewGencysPages);

    const [searchValue, setSearchValue] = useState(query.filter?.search ?? '');
    const [syncing, setSyncing] = useState(false);

    const status = query.filter?.status;
    const platform = query.filter?.platform;

    const baseUrl = `/workspaces/${workspace.slug}/gencys/pages`;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    const handleSync = () => {
        router.post(
            `${baseUrl}/sync`,
            {},
            {
                preserveScroll: true,
                onStart: () => setSyncing(true),
                onFinish: () => setSyncing(false),
            },
        );
    };

    const initialSorting = useMemo(
        () => toFrontendSort(query.sort ?? null),
        [query.sort],
    );

    // Single source of truth for the request the table/filters issue.
    const fetchData = (
        overrides: Record<string, unknown> = {},
        filterOverrides: Filter = {},
    ) => {
        router.get(
            baseUrl,
            {
                filter: {
                    search: searchValue || undefined,
                    status,
                    platform,
                    ...filterOverrides,
                },
                sort: query.sort,
                per_page: query.perPage ?? pages.per_page ?? undefined,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const debouncedFetch = useMemo(
        () => debounce(() => fetchData({ page: 1 }), 400),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [searchValue],
    );

    useEffect(() => {
        if ((query.filter?.search ?? '') !== searchValue) debouncedFetch();
        return () => debouncedFetch.cancel();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchValue]);

    const columns = useMemo<ColumnDef<GencysPage>[]>(
        () => [
            {
                accessorKey: 'date_created',
                enableSorting: true,
                meta: { headerClassName: 'w-36', cellClassName: 'w-36' },
                header: ({ column }) => (
                    <SortableHeader column={column} title="Date Created" />
                ),
                cell: ({ row }) => (
                    <span className="font-mono text-[12px] text-gray-600 dark:text-gray-400">
                        {row.original.date_created
                            ? moment(row.original.date_created).format(
                                  'MMM D, YYYY',
                              )
                            : '—'}
                    </span>
                ),
            },
            {
                accessorKey: 'name',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Name" />
                ),
                cell: ({ row }) => (
                    <span className="text-[12px] font-medium text-gray-800 dark:text-gray-200">
                        {row.original.name ?? '—'}
                    </span>
                ),
            },
            {
                accessorKey: 'owner',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Owner" />
                ),
                cell: ({ row }) => (
                    <span className="text-[12px] text-gray-600 dark:text-gray-400">
                        {row.original.owner ?? '—'}
                    </span>
                ),
            },
            {
                accessorKey: 'intern_and_brand',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Intern and Brand" />
                ),
                cell: ({ row }) => (
                    <span
                        className={cn(
                            'text-[12px] text-gray-600 dark:text-gray-400',
                            // Matched back to a known intern — worth surfacing.
                            row.original.gencys_intern_id &&
                                'text-gray-800 dark:text-gray-200',
                        )}
                    >
                        {row.original.intern_and_brand ?? '—'}
                    </span>
                ),
            },
            {
                accessorKey: 'status',
                enableSorting: true,
                meta: { headerClassName: 'w-32', cellClassName: 'w-32' },
                header: ({ column }) => (
                    <SortableHeader column={column} title="Status" />
                ),
                cell: ({ row }) => <StatusBadge value={row.original.status} />,
            },
            {
                accessorKey: 'platform',
                enableSorting: true,
                meta: { headerClassName: 'w-32', cellClassName: 'w-32' },
                header: ({ column }) => (
                    <SortableHeader column={column} title="Platform" />
                ),
                cell: ({ row }) => (
                    <PlatformBadge value={row.original.platform} />
                ),
            },
        ],
        [],
    );

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Pages`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Pages"
                    description="Gencys ERP pages with their owner, intern and platform."
                >
                    {canSync && (
                        <button
                            onClick={handleSync}
                            disabled={syncing}
                            className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <RefreshCw
                                className={cn(
                                    'h-4 w-4',
                                    syncing && 'animate-spin',
                                )}
                            />
                            {syncing ? 'Syncing…' : 'Sync from Gencys ERP'}
                        </button>
                    )}
                </PageHeader>

                <GencysPagesFilters
                    search={searchValue}
                    onSearchChange={setSearchValue}
                    status={status}
                    onStatusChange={(value) =>
                        fetchData({ page: 1 }, { status: value })
                    }
                    statuses={statuses}
                    platform={platform}
                    onPlatformChange={(value) =>
                        fetchData({ page: 1 }, { platform: value })
                    }
                    platforms={platforms}
                />

                <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={pages.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(pages, ['data']) }}
                        onFetch={(params) => {
                            fetchData({
                                sort: params?.sort,
                                page: params?.page ?? 1,
                                per_page:
                                    params?.per_page ??
                                    query.perPage ??
                                    pages.per_page,
                            });
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
