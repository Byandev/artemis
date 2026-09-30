import StatCard from '@/components/admin/client-report/stat-card';
import PageHeader from '@/components/common/PageHeader';
import { Skeleton } from '@/components/ui/skeleton';
import AdminSidebarLayout from '@/layouts/admin/admin-sidebar-layout';
import { currencyFormatter } from '@/lib/utils';
import { Deferred, Head } from '@inertiajs/react';
import { Banknote, FileCheck, UserCheck, UserX } from 'lucide-react';

interface DashboardStats {
    active_clients: number;
    inactive_clients: number;
    paid_invoices_count: number;
    paid_invoices_total: number;
    monthly_revenue: number;
}

interface Props {
    stats?: DashboardStats;
}

const currentMonth = new Date().toLocaleString('en-US', {
    month: 'long',
    year: 'numeric',
});

function StatCardsSkeleton() {
    return (
        <>
            {Array.from({ length: 4 }).map((_, i) => (
                <div
                    key={i}
                    className="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <div className="flex items-start justify-between gap-3">
                        <Skeleton className="h-3 w-24" />
                        <Skeleton className="h-8 w-8 rounded-lg" />
                    </div>
                    <Skeleton className="mt-3 h-7 w-20" />
                    <Skeleton className="mt-2 h-3 w-32" />
                </div>
            ))}
        </>
    );
}

function StatCards({ stats }: { stats: DashboardStats }) {
    return (
        <>
            <StatCard
                label="Total Active Clients"
                value={stats.active_clients.toLocaleString()}
                sub="Active subscription"
                icon={UserCheck}
                accent="brand"
            />
            <StatCard
                label="Total Paid Invoices"
                value={stats.paid_invoices_count.toLocaleString()}
                sub={`${currencyFormatter(stats.paid_invoices_total)} collected`}
                icon={FileCheck}
                accent="blue"
            />
            <StatCard
                label="Monthly Revenue"
                value={currencyFormatter(stats.monthly_revenue)}
                sub={`Paid invoices in ${currentMonth}`}
                icon={Banknote}
                accent="violet"
            />
            <StatCard
                label="Total Inactive Clients"
                value={stats.inactive_clients.toLocaleString()}
                sub="Trialing, lapsed or no subscription"
                icon={UserX}
                accent="amber"
            />
        </>
    );
}

export default function AdminDashboard({ stats }: Props) {
    return (
        <AdminSidebarLayout>
            <Head title="Admin | Dashboard" />
            <div className="p-4 md:p-6">
                <PageHeader title="Dashboard" />
                <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Deferred data="stats" fallback={<StatCardsSkeleton />}>
                        {stats ? <StatCards stats={stats} /> : null}
                    </Deferred>
                </div>
            </div>
        </AdminSidebarLayout>
    );
}
