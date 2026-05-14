import PageHeader from '@/components/common/PageHeader';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import AdminSidebarLayout from '@/layouts/admin/admin-sidebar-layout';
import { PaginatedData } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { debounce, omit } from 'lodash';
import {
    Check,
    CreditCard,
    Pencil,
    Plus,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';

interface SubscriptionPlan {
    id: number;
    code: string;
    name: string;
    price_php: string;
    order_limit: number | null;
    page_limit: number | null;
    data_retention_months: number;
    analytics_tier: string;
    parcel_journey_rate_php: string | null;
    parcel_journey_sms_enabled: boolean;
    support_tier: string;
    trial_days: number | null;
    is_active: boolean;
    sort_order: number;
    subscriptions_count: number;
}

interface Props {
    plans: PaginatedData<SubscriptionPlan>;
    filters: {
        search: string;
        sort?: string;
        direction?: string;
    };
}

export default function Index({ plans, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');

    const initialSorting = useMemo(() => {
        if (filters.sort) {
            return [{ id: filters.sort, desc: filters.direction === 'desc' }];
        }
        return [];
    }, [filters.sort, filters.direction]);

    const performQuery = useCallback(
        debounce((s: string) => {
            router.get(
                '/admin/subscription-plans',
                {
                    search: s || undefined,
                    page: 1,
                },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        }, 400),
        [],
    );

    useEffect(() => {
        if (search !== (filters.search || '')) {
            performQuery(search);
        }
        return () => performQuery.cancel();
    }, [search]);

    function handleDelete(plan: SubscriptionPlan) {
        if (confirm(`Are you sure you want to delete "${plan.name}"?`)) {
            router.delete(`/admin/subscription-plans/${plan.id}`);
        }
    }

    const columns: ColumnDef<SubscriptionPlan>[] = [
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Plan" />
            ),
            cell: ({ row }) => (
                <div className="flex items-center gap-3">
                    <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                        <CreditCard className="h-4 w-4" />
                    </div>
                    <div>
                        <div className="font-semibold text-zinc-900 dark:text-zinc-100">
                            {row.original.name}
                        </div>
                        <div className="font-mono text-xs tracking-tighter text-zinc-500">
                            {row.original.code}
                        </div>
                    </div>
                </div>
            ),
        },
        {
            accessorKey: 'price_php',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Price" />
            ),
            cell: ({ row }) => (
                <div>
                    <div className="font-medium text-zinc-900 dark:text-zinc-100">
                        {parseFloat(row.original.price_php) === 0
                            ? 'Free'
                            : `₱${parseFloat(row.original.price_php).toLocaleString()}`}
                    </div>
                    {row.original.trial_days ? (
                        <div className="text-xs text-zinc-500">
                            {row.original.trial_days}-day trial
                        </div>
                    ) : null}
                </div>
            ),
        },
        {
            id: 'limits',
            enableSorting: false,
            header: () => (
                <div className="text-center text-[11px] font-bold tracking-wider text-zinc-500 uppercase">
                    Limits
                </div>
            ),
            cell: ({ row }) => (
                <div className="text-center text-xs text-zinc-600 dark:text-zinc-400">
                    <div>
                        {row.original.order_limit
                            ? `${row.original.order_limit.toLocaleString()} orders`
                            : 'Unlimited orders'}
                    </div>
                    <div>
                        {row.original.page_limit
                            ? `${row.original.page_limit} pages`
                            : 'Unlimited pages'}
                    </div>
                </div>
            ),
        },
        {
            accessorKey: 'subscriptions_count',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Subscribers"
                    className="justify-center"
                />
            ),
            cell: ({ row }) => (
                <div className="text-center">
                    <span className="inline-flex items-center rounded-full border border-brand-100 bg-brand-50/50 px-2.5 py-0.5 text-xs font-bold text-brand-600 dark:border-brand-500/20 dark:bg-brand-500/10 dark:text-brand-400">
                        {row.original.subscriptions_count}
                    </span>
                </div>
            ),
        },
        {
            accessorKey: 'is_active',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Status"
                    className="justify-center"
                />
            ),
            cell: ({ row }) => (
                <div className="text-center">
                    {row.original.is_active ? (
                        <span className="inline-flex items-center gap-1 rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-500/10 dark:text-green-400">
                            <Check className="h-3 w-3" /> Active
                        </span>
                    ) : (
                        <span className="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-500 dark:bg-zinc-800">
                            <X className="h-3 w-3" /> Inactive
                        </span>
                    )}
                </div>
            ),
        },
        {
            id: 'actions',
            enableSorting: false,
            header: () => (
                <div className="text-right text-[11px] font-bold tracking-wider text-zinc-500 uppercase">
                    Actions
                </div>
            ),
            cell: ({ row }) => (
                <div className="flex items-center justify-end gap-1">
                    <Link
                        href={`/admin/subscription-plans/${row.original.id}/edit`}
                        className="rounded-md p-1.5 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                    >
                        <Pencil className="h-4 w-4" />
                    </Link>
                    <button
                        onClick={() => handleDelete(row.original)}
                        className="rounded-md p-1.5 text-zinc-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                    >
                        <Trash2 className="h-4 w-4" />
                    </button>
                </div>
            ),
        },
    ];

    return (
        <AdminSidebarLayout>
            <Head title="Admin | Subscription Plans" />

            <div className="p-4 md:p-6">
                <PageHeader
                    title="Subscription Plans"
                    description="Manage subscription plans available to workspaces."
                >
                    <div className="relative w-full sm:w-64">
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                        <input
                            type="text"
                            placeholder="Search plans..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full rounded-md border border-zinc-200 bg-white py-2 pr-4 pl-10 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-800 dark:bg-zinc-950"
                        />
                    </div>
                    <Link
                        href="/admin/subscription-plans/create"
                        className="inline-flex items-center gap-1.5 rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-700"
                    >
                        <Plus className="h-4 w-4" />
                        Add Plan
                    </Link>
                </PageHeader>

                <div className="mt-6 rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={plans.data || []}
                        enableInternalPagination={false}
                        initialSorting={initialSorting}
                        meta={{ ...omit(plans, ['data']) }}
                        onFetch={(params) => {
                            const sortStr =
                                params?.sort && params.sort !== null
                                    ? String(params.sort)
                                    : null;
                            router.get(
                                '/admin/subscription-plans',
                                {
                                    search: search || undefined,
                                    sort: sortStr
                                        ? sortStr.replace(/^-/, '')
                                        : undefined,
                                    direction: sortStr
                                        ? sortStr.startsWith('-')
                                            ? 'desc'
                                            : 'asc'
                                        : undefined,
                                    page: params?.page ?? 1,
                                    per_page: params?.per_page ?? undefined,
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
        </AdminSidebarLayout>
    );
}
