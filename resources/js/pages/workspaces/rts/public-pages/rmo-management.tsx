import { RmoFilterBar } from '@/components/rts/RmoFilterBar';
import { publicParcelStatusConfig } from '@/components/rts/rmo-config';
import { createRmoColumns } from '@/components/rts/rmo-columns';
import { DataTable } from '@/components/ui/data-table';
import { Button } from '@/components/ui/button';
import { PaginatedData } from '@/types';
import { OrderForDelivery } from '@/types/models/Pancake/OrderForDelivery';
import { Workspace } from '@/types/models/Workspace';
import { User } from '@/types/models/Pancake/User';
import { router } from '@inertiajs/react';
import { omit } from 'lodash';
import { useEffect, useMemo, useState } from 'react';
import { toFrontendSort } from '@/lib/sort';
import publicPage from '@/routes/public-page';
import { User as UserIcon } from 'lucide-react';
import FormModal from './formModal';

const parcelStatusOptions = Object.keys(publicParcelStatusConfig);

interface Props {
    orders: PaginatedData<OrderForDelivery>;
    workspace: Workspace;
    query?: {
        sort?: string | null;
        filter?: {
            search?: string;
            status?: string;
            page_id?: string;
            shop_id?: string;
        };
        page?: number;
        perPage?: number;
    };
    users: User[];
}

export default function RmoManagement({ orders, workspace, query, users }: Props) {
    const [isLoadingID, setIsLoadingID] = useState<number | null>(null);
    const [assigningOrderId, setAssigningOrderId] = useState<number | null>(null);
    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [selectedStatus, setSelectedStatus] = useState<string>(query?.filter?.status ?? '');
    const [selectedPageId, setSelectedPageId] = useState<string>(query?.filter?.page_id ?? '');
    const [selectedParcelStatus, setSelectedParcelStatus] = useState<string>('');
    const [userName, setUserName] = useState<string | false>(false);
    const [isOpen, setIsOpen] = useState(false);
    // When "Assign to me" is clicked on a row before a user is set, remember the pending order
    const [pendingAssign, setPendingAssign] = useState<{ orderId: number; currentStatus: string } | null>(null);

    const initialSorting = useMemo(() => toFrontendSort(query?.sort ?? null), [query?.sort]);

    const uniquePages = useMemo(() => {
        const map = new Map<number, string>();
        orders.data?.forEach(order => {
            if (order.page?.id && order.page?.name) map.set(order.page.id, order.page.name);
        });
        return Array.from(map.entries()).map(([id, name]) => ({ id: String(id), name }));
    }, [orders.data]);

    useEffect(() => {
        const name = localStorage.getItem('user_name');
        if (name) setUserName(name);
    }, []);

    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(
                publicPage.rmoManagement({ workspace }),
                {
                    sort: query?.sort,
                    'filter[search]': searchValue || undefined,
                    'filter[status]': selectedStatus || undefined,
                    'filter[page_id]': selectedPageId || undefined,
                    'filter[parcel_status]': selectedParcelStatus || undefined,
                    'filter[shop_id]': query?.filter?.shop_id || undefined,
                    page: (searchValue || selectedStatus || selectedPageId || selectedParcelStatus) ? 1 : (query?.page ?? 1),
                },
                { preserveState: true, replace: true, preserveScroll: true, only: ['orders'] },
            );
        }, 500);
        return () => clearTimeout(timer);
    }, [searchValue, selectedStatus, selectedPageId, selectedParcelStatus, query?.sort, query?.filter?.shop_id]);

    const doAssign = (orderId: number, currentStatus: string, userId: string | null) => {
        const payload = userId === null
            ? { status: currentStatus, removeAssignee: true }
            : { status: currentStatus, userId };
        router.post(
            `/public/workspaces/${workspace.slug}/rts/rmo-management/${orderId}`,
            payload,
            {
                preserveScroll: true,
                onStart: () => setAssigningOrderId(orderId),
                onFinish: () => setAssigningOrderId(null),
            },
        );
    };

    const handleChangeStatus = (status: string, orderId: number) => {
        const userId = localStorage.getItem('user_id');
        router.post(
            `/public/workspaces/${workspace.slug}/rts/rmo-management/${orderId}`,
            { status, userId },
            {
                preserveScroll: true,
                onStart: () => setIsLoadingID(orderId),
                onFinish: () => setIsLoadingID(null),
            },
        );
    };

    // Called when "Assign to me" is clicked on a row
    const handleAssignToMe = (orderId: number, currentStatus: string) => {
        const userId = localStorage.getItem('user_id');
        if (userId) {
            doAssign(orderId, currentStatus, userId);
        } else {
            setPendingAssign({ orderId, currentStatus });
            setIsOpen(true);
        }
    };

    // Called when the user picker modal confirms a selection
    const handleUserSelected = (userId: string) => {
        const name = localStorage.getItem('user_name') ?? '';
        setUserName(name);
        if (pendingAssign) {
            doAssign(pendingAssign.orderId, pendingAssign.currentStatus, userId);
            setPendingAssign(null);
        }
    };

    const handleRemoveAssignee = (orderId: number, currentStatus: string) => {
        doAssign(orderId, currentStatus, null);
    };

    const columns = useMemo(() => createRmoColumns({
        isLoadingID,
        onChangeStatus: handleChangeStatus,
        parcelStatusConfig: publicParcelStatusConfig,
        normalizeParcelStatus: (s) => s?.toUpperCase(),
        onAssignToMe: handleAssignToMe,
        onRemoveAssignee: handleRemoveAssignee,
        assigningOrderId,
    }), [isLoadingID, assigningOrderId]);

    return (
        <div className="min-h-screen bg-stone-50 dark:bg-zinc-950">
            <FormModal
                open={isOpen}
                onOpenChange={(open) => {
                    setIsOpen(open);
                    if (!open) setPendingAssign(null);
                }}
                users={users}
                onSubmit={handleUserSelected}
            />

            {/* Top bar */}
            <div className="border-b border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900">
                <div className="mx-auto flex w-full max-w-(--breakpoint-2xl) items-center justify-between px-4 py-3 md:px-6">
                    {/* Brand + date */}
                    <div className="flex items-center gap-3">
                        <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-600">
                            <span className="text-[11px] font-bold text-white">R</span>
                        </div>
                        <div className="h-4 w-px bg-black/8 dark:bg-white/8" />
                        <span className="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                            {new Date().toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })}
                        </span>
                    </div>

                    {/* Identity button */}
                    {userName ? (
                        <button
                            onClick={() => setIsOpen(true)}
                            className="group flex items-center gap-2.5 rounded-xl border border-black/6 dark:border-white/6 bg-stone-50 dark:bg-zinc-800 px-3 py-1.5 transition-all hover:border-black/12 dark:hover:border-white/12 hover:bg-white dark:hover:bg-zinc-700"
                        >
                            <span className="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-[10px] font-bold text-white">
                                {userName.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase()}
                            </span>
                            <div className="flex flex-col items-start">
                                <span className="font-mono text-[9px] uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                    Logged in as
                                </span>
                                <span className="text-[12px] font-semibold text-gray-800 dark:text-gray-100 leading-tight">
                                    {userName}
                                </span>
                            </div>
                            <span className="ml-1 text-[10px] font-medium text-gray-400 dark:text-gray-500 opacity-0 transition-opacity group-hover:opacity-100">
                                Change
                            </span>
                        </button>
                    ) : (
                        <Button
                            size="sm"
                            onClick={() => setIsOpen(true)}
                            className="rounded-lg bg-emerald-600 px-4 text-[12px] font-medium text-white hover:bg-emerald-700"
                        >
                            <UserIcon className="mr-1.5 h-3.5 w-3.5" />
                            Set Identity
                        </Button>
                    )}
                </div>
            </div>

            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                {/* Page title */}
                <div className="mb-6">
                    <h1 className="text-[22px] font-semibold tracking-tight text-gray-900 dark:text-gray-100">
                        RMO Management
                    </h1>
                    <p className="mt-0.5 text-[13px] text-gray-400 dark:text-gray-500">
                        Delivery tracking for today's assigned orders
                    </p>
                </div>

                <RmoFilterBar
                    searchValue={searchValue}
                    onSearchChange={setSearchValue}
                    uniquePages={uniquePages}
                    selectedPageId={selectedPageId}
                    onPageChange={setSelectedPageId}
                    parcelStatusConfig={publicParcelStatusConfig}
                    parcelStatusOptions={parcelStatusOptions}
                    selectedParcelStatus={selectedParcelStatus}
                    onParcelStatusChange={setSelectedParcelStatus}
                    selectedStatus={selectedStatus}
                    onStatusChange={setSelectedStatus}
                />

                <div className="rounded-[14px] border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={orders.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(orders, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                publicPage.rmoManagement({ workspace }),
                                {
                                    sort: params?.sort,
                                    'filter[search]': searchValue || undefined,
                                    'filter[status]': selectedStatus || undefined,
                                    'filter[page_id]': selectedPageId || undefined,
                                    'filter[parcel_status]': selectedParcelStatus || undefined,
                                    'filter[shop_id]': query?.filter?.shop_id || undefined,
                                    page: params?.page ?? 1,
                                },
                                { preserveState: true, replace: true, preserveScroll: true },
                            );
                        }}
                    />
                </div>
            </div>
        </div>
    );
}
