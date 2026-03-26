import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import BreakdownPerCategory from '@/pages/workspaces/rts/rmo-management/partials/BreakdownPerCategory';
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
    workspace: Workspace[]; // or Workspace if it's a single object
    stats: RmoStats[]; // or RmoStats if it's a single object
}

interface StatCardProps {
    title: string;
    value: number;
    icon: React.ComponentType<{ className?: string }>;
    color: string;
}

function StatCard({ title, value, icon: Icon }: StatCardProps) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-[18px] dark:border-white/6 dark:bg-zinc-900">
            <div className="mb-2 flex items-center justify-between">
                <span className="text-[11px] font-medium text-gray-400 dark:text-gray-500">
                    {title}
                </span>
                <span className="flex items-center gap-1 text-[11px] font-medium text-emerald-600 dark:text-emerald-400">
                    <span className="text-[9px]">▲</span>+28.7%
                </span>
            </div>
            <span className="font-mono text-[22px] font-semibold tracking-tight text-gray-900 tabular-nums dark:text-gray-100">
                {value.toLocaleString()}
            </span>
        </div>
    );
}

export default function Analytics({ workspace, stats }: Props) {
    // Get the first stats object if it's an array, or handle accordingly
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

    // Calculate summary metrics
    const totalOrders = Object.values(currentStats || {}).reduce(
        (sum, val) => sum + (typeof val === 'number' ? val : 0),
        0,
    );

    const completionRate = currentStats?.total_delivered
        ? ((currentStats.total_delivered / totalOrders) * 100).toFixed(1)
        : 0;

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

                {/* Detailed Breakdown Section */}
                <div className="mt-8">
                    <div className="mb-4">
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                            Category Breakdown
                        </h2>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            Detailed analysis by category and region
                        </p>
                    </div>
                    <BreakdownPerCategory />
                </div>
            </div>
        </AppLayout>
    );
}
