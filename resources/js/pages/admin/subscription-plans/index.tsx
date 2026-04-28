import AdminSidebarLayout from '@/layouts/admin/admin-sidebar-layout';
import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Search, CreditCard, Plus, Pencil, Trash2, Check, X } from 'lucide-react';
import PageHeader from '@/components/common/PageHeader';
import ComponentCard from '@/components/common/ComponentCard';

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
    plans: SubscriptionPlan[];
    filters: {
        search: string;
    };
}

export default function Index({ plans, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');

    useEffect(() => {
        const delayDebounceFn = setTimeout(() => {
            if (search !== (filters.search || '')) {
                router.get(
                    '/admin/subscription-plans',
                    { search },
                    { preserveState: true, replace: true },
                );
            }
        }, 300);
        return () => clearTimeout(delayDebounceFn);
    }, [search]);

    function handleDelete(plan: SubscriptionPlan) {
        if (confirm(`Are you sure you want to delete "${plan.name}"?`)) {
            router.delete(`/admin/subscription-plans/${plan.id}`);
        }
    }

    return (
        <AdminSidebarLayout>
            <Head title="Admin | Subscription Plans" />

            <div className="p-4 md:p-6">
                <PageHeader
                    title="Subscription Plans"
                    description="Manage subscription plans available to workspaces."
                >
                    <div className="relative w-full sm:w-64">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                        <input
                            type="text"
                            placeholder="Search plans..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full rounded-md border border-zinc-200 bg-white py-2 pl-10 pr-4 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-800 dark:bg-zinc-950"
                        />
                    </div>
                    <Link
                        href="/admin/subscription-plans/create"
                        className="inline-flex items-center gap-1.5 rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 transition-colors"
                    >
                        <Plus className="h-4 w-4" />
                        Add Plan
                    </Link>
                </PageHeader>

                <ComponentCard className="mt-6">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-zinc-100 bg-zinc-50/30 dark:border-zinc-800 dark:bg-zinc-900/50">
                                <tr className="text-zinc-500 uppercase text-[11px] font-bold tracking-wider">
                                    <th className="px-6 py-4">Plan</th>
                                    <th className="px-6 py-4">Price</th>
                                    <th className="px-6 py-4 text-center">Limits</th>
                                    <th className="px-6 py-4 text-center">Subscribers</th>
                                    <th className="px-6 py-4 text-center">Status</th>
                                    <th className="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-100 dark:divide-zinc-800">
                                {plans.length > 0 ? (
                                    plans.map((plan) => (
                                        <tr key={plan.id} className="group hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 transition-colors">
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400">
                                                        <CreditCard className="h-4 w-4" />
                                                    </div>
                                                    <div>
                                                        <div className="font-semibold text-zinc-900 dark:text-zinc-100">{plan.name}</div>
                                                        <div className="text-xs text-zinc-500 font-mono tracking-tighter">{plan.code}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="font-medium text-zinc-900 dark:text-zinc-100">
                                                    {parseFloat(plan.price_php) === 0 ? 'Free' : `₱${parseFloat(plan.price_php).toLocaleString()}`}
                                                </div>
                                                {plan.trial_days ? (
                                                    <div className="text-xs text-zinc-500">{plan.trial_days}-day trial</div>
                                                ) : null}
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                <div className="text-xs text-zinc-600 dark:text-zinc-400">
                                                    <div>{plan.order_limit ? `${plan.order_limit.toLocaleString()} orders` : 'Unlimited orders'}</div>
                                                    <div>{plan.page_limit ? `${plan.page_limit} pages` : 'Unlimited pages'}</div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                <span className="inline-flex items-center rounded-full bg-brand-50/50 dark:bg-brand-500/10 px-2.5 py-0.5 text-xs font-bold text-brand-600 dark:text-brand-400 border border-brand-100 dark:border-brand-500/20">
                                                    {plan.subscriptions_count}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                {plan.is_active ? (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-green-50 dark:bg-green-500/10 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:text-green-400">
                                                        <Check className="h-3 w-3" /> Active
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-zinc-100 dark:bg-zinc-800 px-2.5 py-0.5 text-xs font-medium text-zinc-500">
                                                        <X className="h-3 w-3" /> Inactive
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Link
                                                        href={`/admin/subscription-plans/${plan.id}/edit`}
                                                        className="rounded-md p-1.5 text-zinc-400 hover:text-zinc-600 hover:bg-zinc-100 dark:hover:text-zinc-300 dark:hover:bg-zinc-800 transition-colors"
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </Link>
                                                    <button
                                                        onClick={() => handleDelete(plan)}
                                                        className="rounded-md p-1.5 text-zinc-400 hover:text-red-600 hover:bg-red-50 dark:hover:text-red-400 dark:hover:bg-red-500/10 transition-colors"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-20 text-center">
                                            <div className="flex flex-col items-center gap-2">
                                                <CreditCard className="h-8 w-8 text-zinc-300 dark:text-zinc-600" />
                                                <p className="text-zinc-500 italic">
                                                    {search ? `No plans found matching "${search}"` : 'No subscription plans yet.'}
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </ComponentCard>
            </div>
        </AdminSidebarLayout>
    );
}