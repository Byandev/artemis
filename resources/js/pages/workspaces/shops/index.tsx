import { TargetChecklistDrawer } from '@/components/checklist/target-checklist-drawer';
import PageHeader from '@/components/common/PageHeader';
import ValidateTokenButton from '@/components/pages/ValidateTokenButton';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
    LayoutGrid,
    ListChecks,
    MoreHorizontal,
    Pencil,
    Plus,
    RefreshCw,
    Search,
    Tags,
    Trash2,
    Users,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner'; // Added toast import
import OrderTagsModal from './partials/OrderTagsModal';

const inputClass =
    'h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400';
const labelClass =
    'block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500';
const errorClass = 'font-mono text-[11px] text-red-500';

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
    shopLimit?: number | null;
    shopCount?: number;
    shopLimitReached?: boolean;
}

const Shops = ({
    pages,
    workspace,
    query,
    shopLimit,
    shopCount,
    shopLimitReached,
}: ShopsPage) => {
    const initialSorting = useMemo(() => {
        return toFrontendSort(query?.sort ?? null);
    }, [query?.sort]);

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [checklistDrawerOpen, setChecklistDrawerOpen] = useState(false);
    const [selectedShop, setSelectedShop] = useState<Shop | null>(null);
    const [addOpen, setAddOpen] = useState(false);
    const [shopToDelete, setShopToDelete] = useState<Shop | null>(null);
    const [shopToEdit, setShopToEdit] = useState<Shop | null>(null);
    const [shopForTags, setShopForTags] = useState<Shop | null>(null);
    const { post, processing } = useForm({});
    const { delete: destroy, processing: deleting } = useForm({});
    const canRefreshShops = usePermission(PERMISSIONS.RefreshShops);
    const canCreateShops = usePermission(PERMISSIONS.CreateShops);
    const canEditShops = usePermission(PERMISSIONS.EditShops);
    const canDeleteShops = usePermission(PERMISSIONS.DeleteShops);
    const canViewChecklist = usePermission(PERMISSIONS.ViewChecklist);

    const addForm = useForm({
        shop_id: '',
        pos_token: '',
    });

    const editForm = useForm({
        name: '',
        pos_token: '',
    });

    const openEdit = (shop: Shop) => {
        setShopToEdit(shop);
        editForm.setData({
            name: shop.name ?? '',
            pos_token: shop.pos_token ?? '',
        });
        editForm.clearErrors();
    };

    const submitEditShop = (e: React.FormEvent) => {
        e.preventDefault();
        if (!shopToEdit) return;
        editForm.put(
            workspaces.shops.update.url({ workspace, shop: shopToEdit }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setShopToEdit(null);
                    editForm.reset();
                    toast.success('Shop updated.');
                },
                onError: () => toast.error('Failed to update shop.'),
            },
        );
    };

    const submitAddShop = (e: React.FormEvent) => {
        e.preventDefault();
        addForm.post(workspaces.shops.store.url({ workspace }), {
            preserveScroll: true,
            onSuccess: () => {
                setAddOpen(false);
                addForm.reset();
                toast.success('Shop added. Pages are syncing.');
            },
            onError: () => toast.error('Failed to add shop. Check the form.'),
        });
    };

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

    const refreshPages = (shop: Shop) => {
        post(workspaces.shops.refreshPages.url({ workspace, shop }), {
            preserveScroll: true,
            onStart: () =>
                toast.info(`Refreshing page list for ${shop.name}...`),
            onSuccess: () => router.reload({ only: ['pages'] }),
            onError: () =>
                toast.error(`Failed to refresh page list for ${shop.name}.`),
        });
    };

    const openChecklist = (shop: Shop) => {
        setSelectedShop(shop);
        setChecklistDrawerOpen(true);
    };

    const confirmDelete = () => {
        if (!shopToDelete) return;
        const shop = shopToDelete;
        destroy(workspaces.shops.destroy.url({ workspace, shop }), {
            preserveScroll: true,
            onStart: () => toast.info(`Deleting ${shop.name}...`),
            onSuccess: () => {
                setShopToDelete(null);
                toast.success(
                    `${shop.name} and its related data were deleted.`,
                );
            },
            onError: () => toast.error(`Failed to delete ${shop.name}.`),
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
        {
            accessorKey: 'created_at',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title={'Created'} />
            ),
            cell: ({ row }) => {
                const date = row.original.created_at;
                return (
                    <span>
                        {date ? new Date(date).toLocaleDateString() : '—'}
                    </span>
                );
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
                                onClick={() => setShopForTags(shop)}
                            >
                                <Tags className="mr-2 h-4 w-4" />
                                Order tags
                            </DropdownMenuItem>
                            {canViewChecklist && (
                                <DropdownMenuItem
                                    onClick={() => openChecklist(shop)}
                                >
                                    <ListChecks className="mr-2 h-4 w-4" />
                                    View Checklist
                                </DropdownMenuItem>
                            )}
                            {canRefreshShops && (
                                <DropdownMenuItem
                                    onClick={() => refreshPages(shop)}
                                    disabled={processing}
                                >
                                    <LayoutGrid className="mr-2 h-4 w-4" />
                                    Refresh pages
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
                                    onClick={() => refreshOrders(shop)}
                                    disabled={processing}
                                >
                                    <RefreshCw className="mr-2 h-4 w-4" />
                                    Refresh orders
                                </DropdownMenuItem>
                            )}
                            {canEditShops && (
                                <DropdownMenuItem
                                    onClick={() => openEdit(shop)}
                                >
                                    <Pencil className="mr-2 h-4 w-4" />
                                    Edit shop
                                </DropdownMenuItem>
                            )}
                            {canDeleteShops && (
                                <DropdownMenuItem
                                    onClick={() => setShopToDelete(shop)}
                                    className="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                >
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Delete shop
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        } as ColumnDef<Shop>,
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Shops`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Shops"
                    description="Manage connected shops and sync customer data"
                >
                    {canCreateShops && (
                        <div className="flex items-center gap-3">
                            {shopLimit != null && (
                                <span
                                    className={clsx(
                                        'font-mono text-[10px] tracking-wider whitespace-nowrap uppercase',
                                        shopLimitReached
                                            ? 'text-amber-600 dark:text-amber-400'
                                            : 'text-gray-400 dark:text-gray-500',
                                    )}
                                >
                                    {shopCount ?? 0}/{shopLimit} shops used
                                    {shopLimitReached &&
                                        ' · upgrade to add more'}
                                </span>
                            )}
                            <Button
                                size="sm"
                                onClick={() => setAddOpen(true)}
                                disabled={shopLimitReached}
                                title={
                                    shopLimitReached
                                        ? `Shop limit reached (${shopCount ?? 0}/${shopLimit}). Upgrade your plan to add more.`
                                        : undefined
                                }
                            >
                                <Plus className="h-4 w-4" />
                                Add Shop
                            </Button>
                        </div>
                    )}
                </PageHeader>

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

                {canCreateShops && (
                    <Dialog
                        open={addOpen}
                        onOpenChange={(open) => {
                            setAddOpen(open);
                            if (!open) addForm.clearErrors();
                        }}
                    >
                        <DialogContent>
                            <form onSubmit={submitAddShop}>
                                <DialogHeader>
                                    <DialogTitle>Add Shop</DialogTitle>
                                    <DialogDescription>
                                        Enter your shop ID and POS token. We'll
                                        fetch the shop's pages automatically.
                                    </DialogDescription>
                                </DialogHeader>

                                <div className="space-y-5 py-4">
                                    <div className="space-y-1.5">
                                        <label className={labelClass}>
                                            Shop ID{' '}
                                            <span className="text-red-400">
                                                *
                                            </span>
                                        </label>
                                        <input
                                            type="number"
                                            autoFocus
                                            className={inputClass}
                                            placeholder="e.g. 789"
                                            value={addForm.data.shop_id}
                                            onChange={(e) =>
                                                addForm.setData(
                                                    'shop_id',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {addForm.errors.shop_id && (
                                            <p className={errorClass}>
                                                {addForm.errors.shop_id}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-1.5">
                                        <label className={labelClass}>
                                            POS Token{' '}
                                            <span className="text-red-400">
                                                *
                                            </span>
                                        </label>
                                        <input
                                            type="text"
                                            className={inputClass}
                                            placeholder="Enter POS token"
                                            value={addForm.data.pos_token}
                                            onChange={(e) =>
                                                addForm.setData(
                                                    'pos_token',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <ValidateTokenButton
                                            url={`/workspaces/${workspace.slug}/shops/validate-pos-token`}
                                            payload={{
                                                shop_id: addForm.data.shop_id,
                                                token: addForm.data.pos_token,
                                            }}
                                            disabledReason={
                                                !addForm.data.shop_id
                                                    ? 'Enter Shop ID first'
                                                    : !addForm.data.pos_token
                                                      ? 'Enter a token first'
                                                      : undefined
                                            }
                                        />
                                        {addForm.errors.pos_token && (
                                            <p className={errorClass}>
                                                {addForm.errors.pos_token}
                                            </p>
                                        )}
                                    </div>

                                    {(
                                        addForm.errors as Record<
                                            string,
                                            string | undefined
                                        >
                                    ).shop_limit && (
                                        <p className={errorClass}>
                                            {
                                                (
                                                    addForm.errors as Record<
                                                        string,
                                                        string | undefined
                                                    >
                                                ).shop_limit
                                            }
                                        </p>
                                    )}
                                </div>

                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setAddOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={addForm.processing}
                                    >
                                        {addForm.processing
                                            ? 'Adding…'
                                            : 'Add Shop'}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                )}

                {canEditShops && (
                    <Dialog
                        open={!!shopToEdit}
                        onOpenChange={(open) => {
                            if (!open) {
                                setShopToEdit(null);
                                editForm.clearErrors();
                            }
                        }}
                    >
                        <DialogContent>
                            <form onSubmit={submitEditShop}>
                                <DialogHeader>
                                    <DialogTitle>Edit Shop</DialogTitle>
                                    <DialogDescription>
                                        Update the shop name or POS token. A
                                        changed token is re-verified before
                                        saving.
                                    </DialogDescription>
                                </DialogHeader>

                                <div className="space-y-5 py-4">
                                    <div className="space-y-1.5">
                                        <label className={labelClass}>
                                            Name{' '}
                                            <span className="text-red-400">
                                                *
                                            </span>
                                        </label>
                                        <input
                                            type="text"
                                            autoFocus
                                            className={inputClass}
                                            placeholder="Shop name"
                                            value={editForm.data.name}
                                            onChange={(e) =>
                                                editForm.setData(
                                                    'name',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {editForm.errors.name && (
                                            <p className={errorClass}>
                                                {editForm.errors.name}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-1.5">
                                        <label className={labelClass}>
                                            POS Token
                                        </label>
                                        <input
                                            type="text"
                                            className={inputClass}
                                            placeholder="POS token"
                                            value={editForm.data.pos_token}
                                            onChange={(e) =>
                                                editForm.setData(
                                                    'pos_token',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {shopToEdit &&
                                            editForm.data.pos_token && (
                                                <ValidateTokenButton
                                                    url={`/workspaces/${workspace.slug}/shops/validate-pos-token`}
                                                    payload={{
                                                        shop_id: String(
                                                            shopToEdit.id,
                                                        ),
                                                        token: editForm.data
                                                            .pos_token,
                                                    }}
                                                />
                                            )}
                                        {editForm.errors.pos_token && (
                                            <p className={errorClass}>
                                                {editForm.errors.pos_token}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setShopToEdit(null)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={editForm.processing}
                                    >
                                        {editForm.processing
                                            ? 'Saving…'
                                            : 'Save Changes'}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                )}

                {canDeleteShops && (
                    <AlertDialog
                        open={!!shopToDelete}
                        onOpenChange={(open) => !open && setShopToDelete(null)}
                    >
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>Delete Shop</AlertDialogTitle>
                                <AlertDialogDescription>
                                    Are you sure you want to delete{' '}
                                    <span className="font-medium">
                                        {shopToDelete?.name}
                                    </span>
                                    ? This will also remove its pages, products,
                                    users, orders, customers and delivery
                                    records. This action cannot be undone.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel disabled={deleting}>
                                    Cancel
                                </AlertDialogCancel>
                                <AlertDialogAction
                                    onClick={(e) => {
                                        e.preventDefault();
                                        confirmDelete();
                                    }}
                                    disabled={deleting}
                                    className="bg-red-600 text-white hover:bg-red-700"
                                >
                                    {deleting ? 'Deleting...' : 'Delete'}
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                )}

                <OrderTagsModal
                    open={!!shopForTags}
                    onOpenChange={(open) => !open && setShopForTags(null)}
                    workspace={workspace}
                    shop={shopForTags}
                    canCreatePresets={canEditShops}
                />

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
