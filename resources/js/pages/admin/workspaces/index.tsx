import AdminSidebarLayout from '@/layouts/admin/admin-sidebar-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Search, Files, LayoutGrid, CreditCard, X } from 'lucide-react';
import PageHeader from '@/components/common/PageHeader';
import ComponentCard from '@/components/common/ComponentCard';

interface SubscriptionPlan {
    id: number;
    code: string;
    name: string;
    price_php: string;
}

interface Subscription {
    id: number;
    subscription_plan_id: number;
    status: string;
    current_period_end: string | null;
    trial_ends_at: string | null;
    plan: SubscriptionPlan;
}

interface Workspace {
    id: number;
    name: string;
    slug: string;
    owner?: { name: string };
    pages_count: number;
    subscription?: Subscription | null;
}

interface Props {
    workspaces: {
        data: Workspace[];
    };
    plans: SubscriptionPlan[];
    filters: {
        search: string;
    };
}

const statusColors: Record<string, string> = {
    active: 'bg-green-50 text-green-700 border-green-200 dark:bg-green-500/10 dark:text-green-400 dark:border-green-500/20',
    trialing: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500/20',
    past_due: 'bg-yellow-50 text-yellow-700 border-yellow-200 dark:bg-yellow-500/10 dark:text-yellow-400 dark:border-yellow-500/20',
    canceled: 'bg-zinc-100 text-zinc-600 border-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:border-zinc-700',
    expired: 'bg-red-50 text-red-700 border-red-200 dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/20',
};

export default function Index({ workspaces, plans, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [editingWorkspace, setEditingWorkspace] = useState<Workspace | null>(null);

    useEffect(() => {
        const delayDebounceFn = setTimeout(() => {
            if (search !== (filters.search || '')) {
                router.get(
                    '/admin/workspaces',
                    { search },
                    { preserveState: true, replace: true },
                );
            }
        }, 300);
        return () => clearTimeout(delayDebounceFn);
    }, [search]);

    return (
        <AdminSidebarLayout>
            <Head title="Admin | Workspaces" />

            <div className="p-4 md:p-6">
                <PageHeader
                    title="Workspace Management"
                    description="Platform-wide overview of all created workspaces and their owners."
                >
                    <div className="relative w-full sm:w-64">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                        <input
                            type="text"
                            placeholder="Search workspaces..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full rounded-md border border-zinc-200 bg-white py-2 pl-10 pr-4 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-800 dark:bg-zinc-950"
                        />
                    </div>
                </PageHeader>

                <ComponentCard className="mt-6">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-zinc-100 bg-zinc-50/30 dark:border-zinc-800 dark:bg-zinc-900/50">
                                <tr className="text-zinc-500 uppercase text-[11px] font-bold tracking-wider">
                                    <th className="px-6 py-4">Workspace Info</th>
                                    <th className="px-6 py-4">Primary Owner</th>
                                    <th className="px-6 py-4 text-center">Resources</th>
                                    <th className="px-6 py-4 text-center">Subscription</th>
                                    <th className="px-6 py-4 text-center">Days Left</th>
                                    <th className="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-100 dark:divide-zinc-800">
                                {workspaces.data.length > 0 ? (
                                    workspaces.data.map((ws) => (
                                        <tr key={ws.id} className="group hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 transition-colors">
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400">
                                                        <LayoutGrid className="h-4 w-4" />
                                                    </div>
                                                    <div>
                                                        <div className="font-semibold text-zinc-900 dark:text-zinc-100">{ws.name}</div>
                                                        <div className="text-xs text-zinc-500 font-mono tracking-tighter">/{ws.slug}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-2 text-zinc-600 dark:text-zinc-400">
                                                    <div className="h-2 w-2 rounded-full bg-brand-500" />
                                                    {ws.owner?.name || 'Platform Admin'}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                <div className="inline-flex items-center gap-1.5 rounded-full bg-brand-50/50 dark:bg-brand-500/10 px-3 py-1 text-xs font-bold text-brand-600 dark:text-brand-400 border border-brand-100 dark:border-brand-500/20">
                                                    <Files className="h-3 w-3" />
                                                    {ws.pages_count} Pages
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                {ws.subscription ? (
                                                    <div className="inline-flex flex-col items-center gap-1">
                                                        <span className="text-xs font-semibold text-zinc-900 dark:text-zinc-100">
                                                            {ws.subscription.plan.name}
                                                        </span>
                                                        <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-medium ${statusColors[ws.subscription.status] || statusColors.canceled}`}>
                                                            {ws.subscription.status}
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-zinc-400 italic">No plan</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                {ws.subscription ? (
                                                    <DaysLeft subscription={ws.subscription} />
                                                ) : (
                                                    <span className="text-xs text-zinc-400">—</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <button
                                                    onClick={() => setEditingWorkspace(ws)}
                                                    className="rounded-md p-1.5 text-zinc-400 hover:text-brand-600 hover:bg-brand-50 dark:hover:text-brand-400 dark:hover:bg-brand-500/10 transition-colors"
                                                    title="Change subscription"
                                                >
                                                    <CreditCard className="h-4 w-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-20 text-center">
                                            <div className="flex flex-col items-center gap-2">
                                                <p className="text-zinc-500 italic">No workspaces found matching "{search}"</p>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </ComponentCard>
            </div>

            {editingWorkspace && (
                <SubscriptionModal
                    workspace={editingWorkspace}
                    plans={plans}
                    onClose={() => setEditingWorkspace(null)}
                />
            )}
        </AdminSidebarLayout>
    );
}

function DaysLeft({ subscription }: { subscription: Subscription }) {
    const endDate = subscription.status === 'trialing'
        ? subscription.trial_ends_at
        : subscription.current_period_end;

    if (!endDate) return <span className="text-xs text-zinc-400">—</span>;

    const now = new Date();
    const end = new Date(endDate);
    const diffMs = end.getTime() - now.getTime();
    const days = Math.ceil(diffMs / (1000 * 60 * 60 * 24));

    if (days < 0) {
        return <span className="text-xs font-medium text-red-500">Expired</span>;
    }

    const color = days <= 3
        ? 'text-red-600 dark:text-red-400'
        : days <= 7
          ? 'text-yellow-600 dark:text-yellow-400'
          : 'text-zinc-700 dark:text-zinc-300';

    return (
        <div className="flex flex-col items-center">
            <span className={`text-sm font-bold ${color}`}>{days}</span>
            <span className="text-[10px] text-zinc-500">days left</span>
        </div>
    );
}

function SubscriptionModal({
    workspace,
    plans,
    onClose,
}: {
    workspace: Workspace;
    plans: SubscriptionPlan[];
    onClose: () => void;
}) {
    const { data, setData, put, processing } = useForm({
        subscription_plan_id: workspace.subscription?.subscription_plan_id?.toString() || (plans[0]?.id?.toString() ?? ''),
        status: workspace.subscription?.status || 'active',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/admin/workspaces/${workspace.slug}/subscription`, {
            onSuccess: () => onClose(),
        });
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={onClose}>
            <div
                className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-zinc-900"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-center justify-between mb-5">
                    <div>
                        <h3 className="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                            Adjust Subscription
                        </h3>
                        <p className="text-sm text-zinc-500">{workspace.name}</p>
                    </div>
                    <button
                        onClick={onClose}
                        className="rounded-md p-1 text-zinc-400 hover:text-zinc-600 hover:bg-zinc-100 dark:hover:text-zinc-300 dark:hover:bg-zinc-800"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                            Plan
                        </label>
                        <select
                            value={data.subscription_plan_id}
                            onChange={(e) => {
                                const selectedPlan = plans.find((p) => p.id.toString() === e.target.value);
                                setData((prev) => ({
                                    ...prev,
                                    subscription_plan_id: e.target.value,
                                    status: selectedPlan?.code === 'free_trial' ? 'trialing' : 'active',
                                }));
                            }}
                            className="w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-700 dark:bg-zinc-800"
                        >
                            {plans.map((plan) => (
                                <option key={plan.id} value={plan.id}>
                                    {plan.name} — ₱{parseFloat(plan.price_php).toLocaleString()}/mo
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                            Status
                        </label>
                        <select
                            value={data.status}
                            onChange={(e) => setData('status', e.target.value)}
                            className="w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-700 dark:bg-zinc-800"
                        >
                            <option value="active">Active</option>
                            <option value="trialing">Trialing</option>
                            <option value="past_due">Past Due</option>
                            <option value="canceled">Canceled</option>
                            <option value="expired">Expired</option>
                        </select>
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-md border border-zinc-200 dark:border-zinc-700 px-4 py-2 text-sm font-medium text-zinc-600 dark:text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50 transition-colors"
                        >
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
