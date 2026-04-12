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
import workspaces from '@/routes/workspaces';
import { PaginatedData } from '@/types';
import { Page } from '@/types/models/Page';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import clsx from 'clsx';
import { omit } from 'lodash';
import {
    Edit,
    MoreHorizontal,
    RefreshCw,
    Search,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface PagesProps {
    workspace: Workspace;
    pages: PaginatedData<Page>;
    query?: {
        sort?: string | null
        perPage?: number | string
        page?: number | string
        filter?: {
            search?: string
        }
    }
}

const StatusBadge = ({ status }: { status: 'active' | 'inactive' }) => {
    const isActive = status === 'active';
    return (
        <span
            className={clsx(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-mono text-[11px] font-medium uppercase tracking-wide',
                isActive
                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'
                    : 'bg-red-50 text-red-500 dark:bg-red-500/10 dark:text-red-400',
            )}
        >
            <span className={clsx('h-1.5 w-1.5 rounded-full', isActive ? 'bg-emerald-500' : 'bg-red-400')} />
            {isActive ? 'Active' : 'Inactive'}
        </span>
    );
};

const EnableBadge = ({ isEnabled }: { isEnabled: boolean }) => {
    return (
        <span
            className={clsx(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium',
                isEnabled
                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'
                    : 'bg-red-50 text-red-500 dark:bg-red-500/10 dark:text-red-400',
            )}
        >
            <span className={clsx('h-1.5 w-1.5 rounded-full', isEnabled ? 'bg-emerald-500' : 'bg-red-500')} />
            {isEnabled ? 'Enabled' : 'Disabled'}
        </span>
    );
};

const Pages = ({ pages, workspace, query }: PagesProps) => {
    const initialSorting = useMemo(() => {
        return toFrontendSort(query?.sort ?? null);
    }, [query?.sort]);


    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');

    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(
                workspaces.pages.index({ workspace }),
                {
                    sort: query?.sort,
                    'filter[search]': searchValue || undefined,
                    page: searchValue ? 1 : query?.page ?? 1
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['pages'],
                },
            );
        }, 500);

        return () => clearTimeout(timer);
    }, [searchValue]);

    const handleEdit = (page: Page) => {
        router.get(`/workspaces/${workspace.slug}/pages/${page.id}/edit`);
    };

    const handleCreate = () => {
        router.get(`/workspaces/${workspace.slug}/pages/create`);
    };

    const refresh = (page: Page) => {
        setProcessing(true);
        router.post(workspaces.pages.refresh.url({ workspace, page }), {}, {
            onSuccess: () => alert('Refresh Started'),
            onFinish: () => setProcessing(false),
        });
    };


    const columns: ColumnDef<Page>[] = [
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title={'Name'} />
            ),
        },
        {
            accessorKey: 'shop_name',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Shop'} />
            ),
            cell: ({ row }) => row.original.shop?.name || '-',
        },
        {
            accessorKey: 'owner_name',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Owner'} />
            ),
            cell: ({ row }) => row.original.owner?.name || '-',
        },
        // {
        //     accessorKey: 'current_budget',
        //     header: ({ column }) => (
        //         <SortableHeader column={column} title={'Current Budget'} enabled={false} />
        //     ),
        //     cell: ({ row }) => currencyFormatter(row.original.current_budget ?? 0),
        // },
        {
            accessorKey: 'orders_last_synced_at',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Last Sync'} />
            ),
            cell: ({ row }) => {
                const date = row.original.orders_last_synced_at;
                const isUpdated = Boolean(row.original.is_sync_logic_updated);
                return (
                    <div className="flex items-center gap-2">
                        <span>{date ? new Date(date).toLocaleString() : 'Never'}</span>
                        <span className={clsx(
                            'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium',
                            isUpdated
                                ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400'
                                : 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
                        )}>
                            {isUpdated ? 'Updated' : 'Legacy'}
                        </span>
                    </div>
                );
            },
        },
        {
            accessorKey: 'deleted_at',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Status'} />
            ),
            cell: ({ row }) => <StatusBadge status={row.original.status} />,
        },
        {
            accessorKey: 'parcel_journey_enabled',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Parcel Journey'} />
            ),
            cell: ({ row }) => {
                const isEnabled = Boolean(row.original.parcel_journey_enabled);

                return <EnableBadge isEnabled={isEnabled} />;
            },
        },
        {
            id: 'actions',
            cell: ({ row }) => {
                const page = row.original;

                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 transition-all hover:border-black/12 hover:bg-stone-100 hover:text-gray-600 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-500 dark:hover:border-white/12 dark:hover:bg-zinc-700 dark:hover:text-gray-300">
                                <MoreHorizontal className="h-3.5 w-3.5" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-44">
                                <DropdownMenuItem onClick={() => handleEdit(page)}>
                                    <Edit />
                                    Edit
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => refresh(page)} disabled={processing}>
                                    <RefreshCw className={processing ? 'animate-spin' : ''} />
                                    {processing ? 'Refreshing…' : 'Refresh Orders'}
                                </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Pages`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader title="Pages" description="Manage your shop pages and their connected stores">
                    <Button size="sm" onClick={handleCreate}>
                        Add New Page
                    </Button>
                </PageHeader>

                <div className="mb-3 flex items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 dark:border-white/6 bg-stone-100 dark:bg-zinc-800 pl-8 pr-3 font-mono! text-[12px]! text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-600 outline-none transition-all focus:border-emerald-500 dark:focus:border-emerald-400 focus:ring-2 focus:ring-emerald-500/15"
                            placeholder="Search page name..."
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                        />
                    </div>
                </div>

                <div className="rounded-[14px] border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={pages.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(pages, ['data']) }}
                        onFetch={(params) => {
                            console.log(params)
                            router.get(
                                workspaces.pages.index({ workspace }),
                                {
                                    sort: params?.sort,
                                    'filter[search]': searchValue || undefined,
                                    page: params?.page ?? 1,
                                    per_page: params?.per_page,
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
};

export default Pages;
