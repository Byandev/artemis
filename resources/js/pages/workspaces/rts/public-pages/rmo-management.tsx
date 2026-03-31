import { createRmoColumns } from '@/components/rts/rmo-columns';
import { authParcelStatusConfig, orderStatusConfig } from '@/components/rts/rmo-config';
import { FilterOption, RmoFilterControls } from '@/components/rts/RmoFilterControls';
import { RmoStatCards } from '@/components/rts/RmoStatCards';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { useRmoFilterState } from '@/hooks/useRmoFilterState';
import { toFrontendSort } from '@/lib/sort';
import publicPage from '@/routes/public-page';
import { PaginatedData } from '@/types';
import { OrderForDelivery } from '@/types/models/Pancake/OrderForDelivery';
import { User } from '@/types/models/Pancake/User';
import { Workspace } from '@/types/models/Workspace';
import { router } from '@inertiajs/react';
import { omit } from 'lodash';
import {
    BarChart3,
    ChevronDown,
    ChevronUp,
    User as UserIcon,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import FormModal from './formModal';

interface Props {
    orders: PaginatedData<OrderForDelivery>;
    workspace: Workspace;
    query?: {
        sort?: string | null;
        filter?: {
            search?: string;
            status?: string | string[];
            page_id?: string | string[];
            shop_id?: string | string[];
            parcel_status?: string | string[];
        };
        page?: number;
        perPage?: number;
    };
    users: User[];
    total_for_delivery_today: number;
    called_rate: number;
    successful_rate: number;
    unsuccessful_rate: number;
}

export default function RmoManagement({
    orders,
    workspace,
    query,
    users,
    total_for_delivery_today,
    called_rate,
    successful_rate,
    unsuccessful_rate,
}: Props) {
    const [isLoadingID, setIsLoadingID] = useState<number | null>(null);
    const [assigningOrderId, setAssigningOrderId] = useState<number | null>(null);
    const [userName, setUserName] = useState<string | false>(false);
    const [isOpen, setIsOpen] = useState(false);
    const [showStats, setShowStats] = useState(() => localStorage.getItem('rmo_show_stats') === 'true');
    const [pendingAssign, setPendingAssign] = useState<{ orderId: number; currentStatus: string } | null>(null);

    const {
        searchValue, setSearchValue,
        selectedStatuses, setSelectedStatuses,
        selectedPageIds,
        selectedParcelStatuses, setSelectedParcelStatuses,
        selectedShopIds,
        hasActiveFilters,
        clearAllFilters,
        buildParams,
    } = useRmoFilterState(query);

    const initialSorting = useMemo(() => toFrontendSort(query?.sort ?? null), [query?.sort]);

    const orderStatusOptions = useMemo<FilterOption[]>(
        () =>
            Object.keys(orderStatusConfig).map((status) => ({
                id: status,
                name: status.replace(/_/g, ' '),
                dot: orderStatusConfig[status]?.dot ?? 'bg-gray-400',
                text: orderStatusConfig[status]?.text ?? '',
            })),
        [],
    );

    const parcelStatusOptions = useMemo<FilterOption[]>(
        () =>
            Object.entries(authParcelStatusConfig).map(([key, config]) => ({
                id: key,
                name: config.label,
                dot: config.dot,
            })),
        [],
    );

    const navigate = useCallback(
        (sort?: string | null, page?: number) => {
            router.get(
                publicPage.rmoManagement({ workspace }),
                buildParams(sort, page),
                { preserveState: true, replace: true, preserveScroll: true },
            );
        },
        [workspace, buildParams],
    );

    useEffect(() => {
        const name = localStorage.getItem('user_name');
        if (name) setUserName(name);
    }, []);

    useEffect(() => {
        const timer = setTimeout(() => {
            navigate(
                query?.sort,
                searchValue || selectedStatuses.length || selectedPageIds.length ||
                selectedParcelStatuses.length || selectedShopIds.length
                    ? 1
                    : (query?.page ?? 1),
            );
        }, 500);
        return () => clearTimeout(timer);
    }, [searchValue, selectedStatuses, selectedPageIds, selectedParcelStatuses, selectedShopIds, query?.sort]);

    const doAssign = useCallback(
        (orderId: number, currentStatus: string, userId: string | null) => {
            const payload =
                userId === null
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
        },
        [workspace.slug],
    );

    const handleChangeStatus = useCallback(
        (status: string, orderId: number) => {
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
        },
        [workspace.slug],
    );

    const handleAssignToMe = useCallback(
        (orderId: number, currentStatus: string) => {
            const userId = localStorage.getItem('user_id');
            if (userId) {
                doAssign(orderId, currentStatus, userId);
            } else {
                setPendingAssign({ orderId, currentStatus });
                setIsOpen(true);
            }
        },
        [doAssign],
    );

    const handleUserSelected = useCallback(
        (userId: string) => {
            setUserName(localStorage.getItem('user_name') ?? '');
            if (pendingAssign) {
                doAssign(pendingAssign.orderId, pendingAssign.currentStatus, userId);
                setPendingAssign(null);
            }
        },
        [pendingAssign, doAssign],
    );

    const handleRemoveAssignee = useCallback(
        (orderId: number, currentStatus: string) => doAssign(orderId, currentStatus, null),
        [doAssign],
    );

    const columns = useMemo(
        () =>
            createRmoColumns({
                isLoadingID,
                assigningOrderId,
                onChangeStatus: handleChangeStatus,
                parcelStatusConfig: authParcelStatusConfig,
                normalizeParcelStatus: (s) => s?.toLowerCase(),
                onAssignToMe: handleAssignToMe,
                onRemoveAssignee: handleRemoveAssignee,
            }),
        [isLoadingID, assigningOrderId, handleChangeStatus, handleAssignToMe, handleRemoveAssignee],
    );

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
            <div className="border-b border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                <div className="mx-auto flex w-full items-center justify-between px-4 py-3 md:px-6">
                    <div className="flex items-center gap-3">
                        <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-600">
                            <span className="text-[11px] font-bold text-white">R</span>
                        </div>
                        <div className="h-4 w-px bg-black/8 dark:bg-white/8" />
                        <span className="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                            {new Date().toLocaleDateString('en-US', {
                                weekday: 'short',
                                month: 'short',
                                day: 'numeric',
                                year: 'numeric',
                            })}
                        </span>
                    </div>

                    {userName ? (
                        <button
                            onClick={() => setIsOpen(true)}
                            className="group flex items-center gap-2.5 rounded-xl border border-black/6 bg-stone-50 px-3 py-1.5 transition-all hover:border-black/12 hover:bg-white dark:border-white/6 dark:bg-zinc-800 dark:hover:border-white/12 dark:hover:bg-zinc-700"
                        >
                            <span className="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-[10px] font-bold text-white">
                                {userName.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase()}
                            </span>
                            <div className="flex flex-col items-start">
                                <span className="font-mono text-[9px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                    Logged in as
                                </span>
                                <span className="text-[12px] leading-tight font-semibold text-gray-800 dark:text-gray-100">
                                    {userName}
                                </span>
                            </div>
                            <span className="ml-1 text-[10px] font-medium text-gray-400 opacity-0 transition-opacity group-hover:opacity-100 dark:text-gray-500">
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

            <div className="mx-auto w-full p-4 md:p-6">
                <div className="mb-6 flex items-start justify-between">
                    <div>
                        <h1 className="text-[22px] font-semibold tracking-tight text-gray-900 dark:text-gray-100">
                            RMO Management
                        </h1>
                        <p className="mt-0.5 text-[13px] text-gray-400 dark:text-gray-500">
                            Delivery tracking for today's assigned orders
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            setShowStats((prev) => {
                                const next = !prev;
                                localStorage.setItem('rmo_show_stats', String(next));
                                return next;
                            })
                        }
                        className="flex items-center gap-1.5 rounded-lg text-[12px]"
                    >
                        <BarChart3 className="h-3.5 w-3.5" />
                        {showStats ? 'Hide' : 'Show'} Statistics
                        {showStats ? <ChevronUp className="h-3.5 w-3.5" /> : <ChevronDown className="h-3.5 w-3.5" />}
                    </Button>
                </div>

                {showStats && (
                    <div className="mb-6">
                        <RmoStatCards
                            total_for_delivery_today={total_for_delivery_today}
                            called_rate={called_rate}
                            successful_rate={successful_rate}
                            unsuccessful_rate={unsuccessful_rate}
                        />
                    </div>
                )}

                <div className="mb-4">
                    <RmoFilterControls
                        searchValue={searchValue}
                        onSearchChange={setSearchValue}
                        orderStatusOptions={orderStatusOptions}
                        selectedStatuses={selectedStatuses}
                        onStatusChange={setSelectedStatuses}
                        parcelStatusOptions={parcelStatusOptions}
                        selectedParcelStatuses={selectedParcelStatuses}
                        onParcelStatusChange={setSelectedParcelStatuses}
                        hasActiveFilters={hasActiveFilters}
                        onClearAll={clearAllFilters}
                    />
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={orders.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(orders, ['data']) }}
                        onFetch={(params) => {
                            navigate(params?.sort as string | undefined, Number(params?.page ?? 1));
                        }}
                    />
                </div>
            </div>
        </div>
    );
}
