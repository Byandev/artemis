import { TargetChecklistDrawer } from '@/components/checklist/target-checklist-drawer';
import PageHeader from '@/components/common/PageHeader';
import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import workspaces from '@/routes/workspaces';
import { PaginatedData } from '@/types';
import { Shop } from '@/types/models/Shop';
import { Workspace } from '@/types/models/Workspace';
import { Head, router, useForm } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import clsx from 'clsx';
import { omit } from 'lodash';
import {
    ListChecks,
    MoreHorizontal,
    RefreshCw,
    Search,
    Users,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner'; // Added toast import

const ChecklistsBadge = ({ pending }: { pending: number }) => {
    const hasPending = pending > 0;
    return (
        <span
            className={clsx(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium',
                hasPending
                    ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400'
                    : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
            )}
        >
            <span
                className={clsx(
                    'h-1.5 w-1.5 rounded-full',
                    hasPending ? 'bg-amber-500' : 'bg-emerald-500',
                )}
            />
            {hasPending ? `${pending} Pending` : 'Complete'}
        </span>
    );
};

interface ShopsPage {
    workspace: Workspace;
    pages: PaginatedData<Shop>;
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: {
            search?: string;
        };
    };
}

const Shops = ({ pages, workspace, query }: ShopsPage) => {
    const initialSorting = useMemo(() => {
        return toFrontendSort(query?.sort ?? null);
    }, [query?.sort]);

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [checklistDrawerOpen, setChecklistDrawerOpen] = useState(false);
    const [selectedShop, setSelectedShop] = useState<Shop | null>(null);
    const { post, processing } = useForm({});
    const canRefreshShops = usePermission(PERMISSIONS.RefreshShops);
    const canViewChecklist = usePermission(PERMISSIONS.ViewChecklist);
    const canUseShopActions = canRefreshShops || canViewChecklist;

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

    const refreshUsers = (shop: Shop) => {
        post(workspaces.shops.refreshUsers.url({ workspace, shop }), {
            onStart: () => toast.info(`Refreshing users for ${shop.name}...`),
            onSuccess: () =>
                toast.success(`${shop.name} users queued for refresh.`),
            onError: () =>
                toast.error(`Failed to refresh users for ${shop.name}.`),
        });
    };

    const refreshOrders = (shop: Shop) => {
        post(workspaces.shops.refreshOrders.url({ workspace, shop }), {
            onStart: () => toast.info(`Refreshing orders for ${shop.name}...`),
            onSuccess: () =>
                toast.success(`${shop.name} orders queued for refresh.`),
            onError: () =>
                toast.error(`Failed to refresh orders for ${shop.name}.`),
        });
    };

    const openChecklist = (shop: Shop) => {
        setSelectedShop(shop);
        setChecklistDrawerOpen(true);
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
            accessorKey: 'pending_required_checklists_count',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Checklists'} />
            ),
            cell: ({ row }) => (
                <ChecklistsBadge
                    pending={Number(
                        row.original.pending_required_checklists_count ?? 0,
                    )}
                />
            ),
        },
        {
            accessorKey: 'orders_last_synced_at',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Last Sync'} />
            ),
            cell: ({ row }) => {
                const date = row.original.orders_last_synced_at;
                return (
                    <span>
                        {date ? new Date(date).toLocaleString() : 'Never'}
                    </span>
                );
            },
        },
        ...(canUseShopActions
            ? [
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
                                      {canViewChecklist && (
                                          <DropdownMenuItem
                                              onClick={() =>
                                                  openChecklist(shop)
                                              }
                                          >
                                              <ListChecks className="mr-2 h-4 w-4" />
                                              View Checklist
                                          </DropdownMenuItem>
                                      )}
                                      {canRefreshShops && (
                                          <DropdownMenuItem
                                              onClick={() => refreshUsers(shop)}
                                              disabled={processing}
                                          >
                                              <Users className="mr-2 h-4 w-4" />
                                              Refresh users
                                          </DropdownMenuItem>
                                      )}
                                      {canRefreshShops && (
                                          <DropdownMenuItem
                                              onClick={() =>
                                                  refreshOrders(shop)
                                              }
                                              disabled={processing}
                                          >
                                              <RefreshCw className="mr-2 h-4 w-4" />
                                              Refresh orders
                                          </DropdownMenuItem>
                                      )}
                                  </DropdownMenuContent>
                              </DropdownMenu>
                          );
                      },
                  } as ColumnDef<Shop>,
              ]
            : []),
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Shops`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Shops"
                    description="Manage connected shops and sync customer data"
                />

                <div className="mb-3 flex items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                            placeholder="Search shop name..."
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                        />
                    </div>
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
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

                {canViewChecklist && (
                    <TargetChecklistDrawer
                        open={checklistDrawerOpen}
                        onOpenChange={(open) => {
                            setChecklistDrawerOpen(open);
                            if (!open) {
                                setSelectedShop(null);
                                router.reload({ only: ['pages'] });
                            }
                        }}
                        workspace={workspace}
                        target="shop"
                        targetId={selectedShop?.id ?? null}
                        targetName={selectedShop?.name ?? ''}
                    />
                )}
            </div>
        </AppLayout>
    );
};

export default Shops;
