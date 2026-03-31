import { AlertTriangleIcon, CheckCircleIcon, PercentIcon, TruckIcon } from 'lucide-react';

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
    called_rate: number;
    successful_rate: number;
    unsuccessful_rate: number;
}

export function RmoStatCards({
    total_for_delivery_today,
    called_rate,
    successful_rate,
    unsuccessful_rate,
}: RmoStatCardsProps) {
    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <StatCard title="Total For Delivery Today" value={total_for_delivery_today || 0} icon={TruckIcon} />
            <StatCard title="Called Rate" value={called_rate || 0} icon={PercentIcon} suffix="%" />
            <StatCard title="Successful Rate" value={successful_rate || 0} icon={CheckCircleIcon} suffix="%" />
            <StatCard title="Unsuccessful Rate" value={unsuccessful_rate || 0} icon={AlertTriangleIcon} suffix="%" />
        </div>
    );
}
