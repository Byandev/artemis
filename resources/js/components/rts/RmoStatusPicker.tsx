import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import {
    ORDER_STATUSES,
    OrderStatus,
} from '@/types/models/Pancake/OrderForDelivery';
import { Check, ChevronDown } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { orderStatusConfig } from './rmo-config';

interface Props {
    currentStatus: OrderStatus;
    onChangeStatus: (status: string) => void;
    disabled?: boolean;
}

export function RmoStatusPicker({
    currentStatus,
    onChangeStatus,
    disabled = false,
}: Props) {
    const [status, setStatus] = useState(currentStatus);

    useEffect(() => {
        setStatus(currentStatus);
    }, [currentStatus]);

    const cfg = useMemo(() => orderStatusConfig[status], [status]);

    if (disabled) {
        return (
            <span
                className={cn(
                    'inline-flex cursor-not-allowed items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium opacity-60',
                    cfg?.pill ??
                        'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-400',
                )}
            >
                <span
                    className={`h-1.5 w-1.5 shrink-0 rounded-full ${cfg?.dot ?? 'bg-gray-400'}`}
                />
                <span className="max-w-[110px] truncate">{status}</span>
            </span>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className={cn(
                    'group inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-[inherit] text-[11px] font-medium transition-all outline-none hover:opacity-80',
                    cfg?.pill ??
                        'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-400',
                )}
            >
                <span
                    className={`h-1.5 w-1.5 shrink-0 rounded-full ${cfg?.dot ?? 'bg-gray-400'}`}
                />
                <span className="max-w-[110px] truncate">{status}</span>
                <ChevronDown className="h-3 w-3 shrink-0 opacity-60" />
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="w-52 overflow-hidden p-1"
            >
                <p className="px-2 pt-1 pb-1.5 font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                    Change status
                </p>
                <div className="max-h-72 overflow-y-auto">
                    {ORDER_STATUSES.map((s) => {
                        const sCfg = orderStatusConfig[s];
                        const isActive = s === currentStatus;
                        return (
                            <DropdownMenuItem
                                key={s}
                                onClick={() => {
                                    setStatus(s);
                                    onChangeStatus(s);
                                }}
                                className={cn(
                                    'flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-[12px]',
                                    isActive
                                        ? 'bg-gray-50 dark:bg-zinc-800'
                                        : 'text-gray-600 dark:text-gray-400',
                                )}
                            >
                                <span
                                    className={`h-1.5 w-1.5 shrink-0 rounded-full ${sCfg?.dot ?? 'bg-gray-400'}`}
                                />
                                <span
                                    className={cn(
                                        'flex-1',
                                        isActive
                                            ? `font-semibold ${sCfg?.text ?? ''}`
                                            : '',
                                    )}
                                >
                                    {s}
                                </span>
                                {isActive && (
                                    <Check className="h-3 w-3 text-emerald-500" />
                                )}
                            </DropdownMenuItem>
                        );
                    })}
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
