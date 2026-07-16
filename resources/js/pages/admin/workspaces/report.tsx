import RtsTrendChart, {
    RtsMonthlyPoint,
} from '@/components/admin/client-report/rts-trend-chart';
import StatCard from '@/components/admin/client-report/stat-card';
import PageHeader from '@/components/common/PageHeader';
import AdminSidebarLayout from '@/layouts/admin/admin-sidebar-layout';
import { Head, Link, router } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import {
    ArrowLeft,
    BellRing,
    PhoneCall,
    RefreshCw,
    Timer,
    Undo2,
} from 'lucide-react';
import { useState } from 'react';

interface Report {
    range: { start: string; end: string; months: number };
    rts_rate: number;
    rts_monthly: RtsMonthlyPoint[];
    notifications_sent: number;
    rmo_called: number;
    call_time_seconds: number;
}

interface Props {
    workspace: { id: number; name: string; slug: string };
    report: Report;
}

const formatInt = (n: number) => (Number(n) || 0).toLocaleString('en-US');

const formatPercent = (rate: number) =>
    `${((Number(rate) || 0) * 100).toFixed(1)}%`;

// Seconds -> H:MM:SS (or M:SS under an hour), matching the CSR analytics view.
const formatCallTime = (seconds: number) => {
    const s = Math.max(0, Math.floor(Number(seconds) || 0));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    const pad = (n: number) => n.toString().padStart(2, '0');
    return h > 0 ? `${h}:${pad(m)}:${pad(sec)}` : `${pad(m)}:${pad(sec)}`;
};

const prettyDate = (iso: string) => {
    try {
        return format(parseISO(iso), 'd MMM yyyy');
    } catch {
        return iso;
    }
};

export default function ClientReport({ workspace, report }: Props) {
    const rangeLabel = `${prettyDate(report.range.start)} – ${prettyDate(report.range.end)}`;

    const [refreshing, setRefreshing] = useState(false);

    // Partial reload: re-invokes AdminClientReportController@show and swaps in
    // only the `report` prop (recomputed via ClientReport::build()) — no extra
    // route or XHR endpoint needed.
    const refresh = () => {
        router.reload({
            only: ['report'],
            onStart: () => setRefreshing(true),
            onFinish: () => setRefreshing(false),
        });
    };

    return (
        <AdminSidebarLayout>
            <Head title={`Admin | ${workspace.name} Report`} />

            <div className="p-4 md:p-6">
                <Link
                    href="/admin/workspaces"
                    className="mb-4 inline-flex items-center gap-1.5 text-sm text-zinc-500 transition-colors hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Workspaces
                </Link>

                <PageHeader
                    title={`${workspace.name} — Client Report`}
                    description={`Performance over the last ${report.range.months} months · ${rangeLabel}`}
                />

                <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label={`RTS Rate (${report.range.months}mo)`}
                        value={formatPercent(report.rts_rate)}
                        sub="Returning ÷ (returning + delivered)"
                        icon={Undo2}
                        accent="amber"
                    />
                    <StatCard
                        label="Notifications Sent"
                        value={formatInt(report.notifications_sent)}
                        sub="Parcel journey notifications"
                        icon={BellRing}
                        accent="brand"
                    />
                    <StatCard
                        label="RMO Called"
                        value={formatInt(report.rmo_called)}
                        sub="Total calls made"
                        icon={PhoneCall}
                        accent="blue"
                    />
                    <StatCard
                        label="Total Call Time"
                        value={formatCallTime(report.call_time_seconds)}
                        sub="Cumulative time on calls"
                        icon={Timer}
                        accent="violet"
                    />
                </div>

                <div className="mt-6 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <div className="mb-1 flex items-baseline justify-between">
                        <h3 className="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                            RTS Rate — Monthly Trend
                        </h3>
                        <div className="flex items-center gap-3">
                            <span className="font-mono text-xs text-zinc-400 dark:text-zinc-500">
                                {formatPercent(report.rts_rate)} avg
                            </span>
                            <button
                                type="button"
                                onClick={refresh}
                                disabled={refreshing}
                                className="inline-flex items-center gap-1.5 rounded-md border border-zinc-200 px-2 py-1 text-xs font-medium text-zinc-500 transition-colors hover:bg-zinc-50 hover:text-zinc-800 disabled:opacity-60 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                                title="Refresh report data"
                            >
                                <RefreshCw
                                    className={`h-3.5 w-3.5 ${refreshing ? 'animate-spin' : ''}`}
                                />
                                {refreshing ? 'Refreshing…' : 'Refresh'}
                            </button>
                        </div>
                    </div>
                    <p className="mb-4 text-xs text-zinc-500 dark:text-zinc-400">
                        Return-to-sender rate for each of the last{' '}
                        {report.range.months} months.
                    </p>
                    <RtsTrendChart data={report.rts_monthly} />
                </div>
            </div>
        </AdminSidebarLayout>
    );
}
