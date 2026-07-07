import { cn } from '@/lib/utils';
import {
    AlertTriangle,
    CalendarClock,
    CheckCircle2,
    Info,
    ShoppingCart,
    type LucideIcon,
} from 'lucide-react';
import { type AlertSeverity, type InventoryAlert } from './types';

const SEVERITY: Record<AlertSeverity, { wrap: string; icon: string }> = {
    critical: {
        wrap: 'border-red-500/20 bg-red-50/60 dark:border-red-400/20 dark:bg-red-500/5',
        icon: 'bg-red-100 text-red-500 dark:bg-red-500/15 dark:text-red-400',
    },
    warning: {
        wrap: 'border-amber-500/20 bg-amber-50/60 dark:border-amber-400/20 dark:bg-amber-500/5',
        icon: 'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400',
    },
    info: {
        wrap: 'border-blue-500/20 bg-blue-50/60 dark:border-blue-400/20 dark:bg-blue-500/5',
        icon: 'bg-blue-100 text-blue-500 dark:bg-blue-500/15 dark:text-blue-400',
    },
};

const ICONS: Record<string, LucideIcon> = {
    low_stock: AlertTriangle,
    reorder: ShoppingCart,
    overdue_po: CalendarClock,
    discrepancy: Info,
};

export default function AlertsFeed({ alerts }: { alerts: InventoryAlert[] }) {
    if (alerts.length === 0) {
        return (
            <div className="flex h-full min-h-[160px] flex-col items-center justify-center gap-2 text-center">
                <div className="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-50 text-emerald-500 dark:bg-emerald-500/10 dark:text-emerald-400">
                    <CheckCircle2 className="h-5 w-5" />
                </div>
                <p className="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                    All clear — no active alerts.
                </p>
            </div>
        );
    }

    return (
        <div className="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-4">
            {alerts.map((alert) => {
                const styles = SEVERITY[alert.severity];
                const Icon = ICONS[alert.type] ?? Info;
                return (
                    <div
                        key={alert.type}
                        className={cn(
                            'flex items-start gap-3 rounded-[12px] border px-3 py-2.5',
                            styles.wrap,
                        )}
                    >
                        <div
                            className={cn(
                                'flex h-7 w-7 shrink-0 items-center justify-center rounded-[9px]',
                                styles.icon,
                            )}
                        >
                            <Icon className="h-3.5 w-3.5" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-[12px] font-semibold text-gray-800 dark:text-gray-100">
                                {alert.title}
                            </p>
                            <p className="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                                {alert.description}
                            </p>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
