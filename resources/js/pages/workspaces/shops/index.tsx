import ComponentCard from '@/components/common/ComponentCard';
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
import { Workspace } from '@/types/models/Workspace';
import { Head, router, useForm } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import {
    MoreHorizontal,
    RefreshCw,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Shop } from '@/types/models/Shop';

interface ShopsPage {
    workspace: Workspace;
    pages: PaginatedData<Shop>;
    query?: {
        sort?: string | null
        perPage?: number | string
        page?: number | string
        filter?: {
            search?: string
        }
    }
}


const Shops = ({ pages, workspace, query }: ShopsPage) => {
    const initialSorting = useMemo(() => {
        return toFrontendSort(query?.sort ?? null);
    }, [query?.sort]);

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const { post, processing } = useForm({});

    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(
                workspaces.shops.index({ workspace }),
                {
                    sort: query?.sort,
                    'filter[search]': searchValue || undefined,
                    page: searchValue ? 1 : (query?.page ?? 1),
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                },
            );
        }, 500);

        return () => clearTimeout(timer);
    }, [searchValue]);

    const refresh = (shop: Shop) => {
        post(workspaces.shops.refresh.url({ workspace, shop }), {
            onSuccess: () => alert('Refresh Started'),
        });
    };

    const columns: ColumnDef<Shop>[] = [
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title={'Name'} />
            ),
        },
        {
            accessorKey: 'customers_last_synced_at',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Customer Last Sync'} />
            ),
            cell: ({ row }) => {
                const date = row.original.customers_last_synced_at;
                return date ? new Date(date).toLocaleString() : 'Never';
            },
        },
        {
            id: 'actions',
            cell: ({ row }) => {
                const shop = row.original;

                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="sm">
                                <MoreHorizontal className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                onClick={() => refresh(shop)}
                                disabled={processing}
                            >
                                <RefreshCw
                                    className={`mr-2 h-4 w-4 ${processing ? 'animate-spin' : ''}`}
                                />
                                {processing
                                    ? 'Refreshing...'
                                    : 'Refresh customers'}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Shops`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader title="Shops" description="Manage connected shops and sync customer data" />

                <div className="space-y-5 sm:space-y-6">
                    <ComponentCard desc="List of shop">
                        <div>
                            <div className="flex flex-col gap-2 border-b border-black/6 dark:border-white/6 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <input
                                    className="w-full max-w-sm px-3 py-2.5 bg-stone-100 dark:bg-zinc-800 border border-black/6 dark:border-white/6 rounded-[10px] text-[13px] text-gray-900 dark:text-gray-100 placeholder:text-gray-300 dark:placeholder:text-gray-600 outline-none transition-all focus:border-emerald-500 dark:focus:border-emerald-400 focus:ring-2 focus:ring-emerald-500/15"
                                    placeholder="Search shop name"
                                    value={searchValue}
                                    onChange={(e) =>
                                        setSearchValue(e.target.value)
                                    }
                                />
                            </div>

                            <DataTable
                                columns={columns}
                                enableInternalPagination={false}
                                data={pages.data || []}
                                initialSorting={initialSorting}
                                meta={{ ...omit(pages, ['data']) }}
                                onFetch={(params) => {
                                    router.get(
                                        workspaces.shops.index({ workspace }),
                                        {
                                            sort: params?.sort,
                                            'filter[search]':
                                                searchValue || undefined,
                                            page: params?.page ?? 1,
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
                    </ComponentCard>
                </div>

            </div>
        </AppLayout>
    );
};

export default Shops;
