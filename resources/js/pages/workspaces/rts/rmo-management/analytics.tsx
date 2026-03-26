import AppLayout from '@/layouts/app-layout';
import DeliveryTrend from '@/pages/workspaces/rts/rmo-management/partials/DeliveryTrend';
import PerPageBreakdown from '@/pages/workspaces/rts/rmo-management/partials/PerPageBreakdown';
import StatusBreakdown from '@/pages/workspaces/rts/rmo-management/partials/StatusBreakdown';
import TopAssignees from '@/pages/workspaces/rts/rmo-management/partials/TopAssignees';
import { Workspace } from '@/types/models/Workspace';
import {
    CheckCircleIcon,
    ClipboardListIcon,
    ClockIcon,
    PhoneIcon,
    RotateCcw,
    TruckIcon,
    XCircleIcon,
} from 'lucide-react';
import React from 'react';

interface RmoStats {
    assigned_orders: number;
    total_called: number;
    total_pending: number;
    total_delivered: number;
    total_returning: number;
    total_undeliverable: number;
    total_out_for_delivery: number;
}

interface Props {
    workspace: Workspace;
    stats: RmoStats;
}

interface StatCardProps {
    title: string;
    value: number;
    icon: React.ComponentType<{ className?: string }>;
}

function StatCard({ title, value }: StatCardProps) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-[18px] dark:border-white/6 dark:bg-zinc-900">
            <div className="mb-2 flex items-center justify-between">
                <span className="text-[11px] font-medium text-gray-400 dark:text-gray-500">
                    {title}
                </span>
            </div>
            <span className="font-mono text-[22px] font-semibold tracking-tight text-gray-900 tabular-nums dark:text-gray-100">
                {value.toLocaleString()}
            </span>
        </div>
    );
}

export default function Analytics({ workspace, stats }: Props) {
    const currentStats = Array.isArray(stats) ? stats[0] : stats;

    const statCards = [
        {
            title: 'Assigned Orders',
            value: currentStats?.assigned_orders || 0,
            icon: ClipboardListIcon,
        },
        {
            title: 'Total Called',
            value: currentStats?.total_called || 0,
            icon: PhoneIcon,
        },
        {
            title: 'Total Pending',
            value: currentStats?.total_pending || 0,
            icon: ClockIcon,
        },
        {
            title: 'Total Delivered',
            value: currentStats?.total_delivered || 0,
            icon: CheckCircleIcon,
        },
        {
            title: 'Total Returning',
            value: currentStats?.total_returning || 0,
            icon: RotateCcw,
        },
        {
            title: 'Total Undeliverable',
            value: currentStats?.total_undeliverable || 0,
            icon: XCircleIcon,
        },
        {
            title: 'Out for Delivery',
            value: currentStats?.total_out_for_delivery || 0,
            icon: TruckIcon,
        },
    ];

    return (
        <AppLayout>
            <div className="px-6 py-6">
                {/* Header Section */}
                <div className="mb-8">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                        RMO Analytics Dashboard
                    </h1>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Track and monitor your delivery performance metrics
                    </p>
                </div>

                {/* Main Stats Grid */}
                <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {statCards.map((card, index) => (
                        <StatCard key={index} {...card} />
                    ))}
                </div>

                {/*/!* Charts Row 1: Delivery Trend + Status Breakdown *!/*/}
                {/*<div className="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-3">*/}
                {/*    <div className="lg:col-span-2">*/}
                {/*        <DeliveryTrend workspace={workspace} />*/}
                {/*    </div>*/}
                {/*    <div>*/}
                {/*        <StatusBreakdown workspace={workspace} />*/}
                {/*    </div>*/}
                {/*</div>*/}

                {/*/!* Charts Row 2: Top Assignees + Per Page *!/*/}
                {/*<div className="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-2">*/}
                {/*    <TopAssignees workspace={workspace} />*/}
                {/*    <PerPageBreakdown workspace={workspace} />*/}
                {/*</div>*/}
            </div>
        </AppLayout>
    );
}
