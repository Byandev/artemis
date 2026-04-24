import { AlertTriangleIcon, PackageCheckIcon, PhoneIcon, RotateCcwIcon, TruckIcon } from 'lucide-react';

interface StatCardProps {
    title: string;
    value: number;
    icon: React.ComponentType<{ className?: string }>;
    suffix?: string;
}

export function StatCard({ title, value, icon: Icon, suffix }: StatCardProps) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-[18px] dark:border-white/6 dark:bg-zinc-900">
            <div className="mb-2 flex items-center gap-2">
                <Icon className="h-4 w-4 text-gray-400 dark:text-gray-500" />
                <span className="text-[11px] font-medium text-gray-400 dark:text-gray-500">
                    {title}
                </span>
            </div>
            <span className="font-mono text-[22px] font-semibold tracking-tight text-gray-900 tabular-nums dark:text-gray-100">
                {value.toLocaleString()}
                {suffix}
            </span>
        </div>
    );
}

interface RmoStatCardsProps {
    total_for_delivery_today: number;
    called_count: number;
    delivered_count: number;
    returning_count: number;
    problematic_count: number;
}

export function RmoStatCards({
    total_for_delivery_today,
    called_count,
    delivered_count,
    returning_count,
    problematic_count,
}: RmoStatCardsProps) {
    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
            <StatCard title="Total For Delivery Today" value={total_for_delivery_today || 0} icon={TruckIcon} />
            <StatCard title="Called" value={called_count || 0} icon={PhoneIcon} />
            <StatCard title="Delivered" value={delivered_count || 0} icon={PackageCheckIcon} />
            <StatCard title="Returning" value={returning_count || 0} icon={RotateCcwIcon} />
            <StatCard title="Problematic" value={problematic_count || 0} icon={AlertTriangleIcon} />
        </div>
    );
}
