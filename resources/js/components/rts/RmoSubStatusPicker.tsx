import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { RmoSubStatus } from '@/types/models/Pancake/OrderForDelivery';
import { Check, ChevronDown, X } from 'lucide-react';

export type RmoSubStatusType = 'cx' | 'rider';

interface MenuItemsProps {
    cxStatuses: RmoSubStatus[];
    riderStatuses: RmoSubStatus[];
    /** Marked with a check; omit for the bulk menu, which has no current value. */
    cxStatusId?: number | null;
    riderStatusId?: number | null;
    onChange: (type: RmoSubStatusType, statusId: number | null) => void;
    /** Always offer "Clear" per kind, not only when one is set (bulk menu). */
    clearable?: boolean;
}

/**
 * The customer and rider statuses as one list, one section each. Shared by the
 * row picker and the bulk "Set status" menu.
 */
export function RmoSubStatusMenuItems({
    cxStatuses,
    riderStatuses,
    cxStatusId = null,
    riderStatusId = null,
    onChange,
    clearable = false,
}: MenuItemsProps) {
    const sections = (
        [
            ['cx', 'Customer', cxStatuses, cxStatusId],
            ['rider', 'Rider', riderStatuses, riderStatusId],
        ] as const
    ).filter(([, , options]) => options.length > 0);

    if (sections.length === 0) {
        return (
            <p className="px-2 py-1.5 text-[12px] text-gray-400 dark:text-gray-500">
                No statuses yet — add them in Settings → RMO statuses.
            </p>
        );
    }

    return (
        <div className="max-h-80 overflow-y-auto">
            {sections.map(([type, label, options, currentId], index) => (
                <div key={type}>
                    {index > 0 && <DropdownMenuSeparator />}
                    <p className="px-2 pt-1 pb-1.5 font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                        {label} status
                    </p>
                    {options.map((o) => {
                        const isActive = o.id === currentId;
                        return (
                            <DropdownMenuItem
                                key={o.id}
                                onClick={() => onChange(type, o.id)}
                                className={cn(
                                    'flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-[12px]',
                                    isActive
                                        ? 'bg-gray-50 font-semibold dark:bg-zinc-800'
                                        : 'text-gray-600 dark:text-gray-400',
                                )}
                            >
                                <span className="flex-1">{o.name}</span>
                                {isActive && (
                                    <Check className="h-3 w-3 text-emerald-500" />
                                )}
                            </DropdownMenuItem>
                        );
                    })}
                    {(clearable || currentId !== null) && (
                        <DropdownMenuItem
                            onClick={() => onChange(type, null)}
                            className="flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-[12px] text-gray-500 dark:text-gray-400"
                        >
                            <X className="h-3 w-3" />
                            <span className="flex-1">
                                Clear {label.toLowerCase()} status
                            </span>
                        </DropdownMenuItem>
                    )}
                </div>
            ))}
        </div>
    );
}

interface Props {
    type: RmoSubStatusType;
    statuses: RmoSubStatus[];
    statusId: number | null;
    onChange: (statusId: number | null) => void;
    disabled?: boolean;
}

/**
 * One RMO row status — customer or rider — as a dropdown of that kind's
 * workspace-defined statuses. The pill shows the picked status by name.
 */
export function RmoSubStatusPicker({
    type,
    statuses,
    statusId,
    onChange,
    disabled = false,
}: Props) {
    const current = statuses.find((o) => o.id === statusId);

    const pill = cn(
        'inline-flex max-w-full items-center rounded-lg border px-2 py-1 text-left text-[11px] font-medium',
        current
            ? 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-300'
            : 'border-dashed border-gray-300 text-gray-400 dark:border-zinc-700 dark:text-gray-500',
    );
    const content = current ? (
        <span className="max-w-[160px] truncate">{current.name}</span>
    ) : (
        <span>Set status</span>
    );

    if (disabled) {
        return (
            <span className={cn(pill, 'cursor-not-allowed opacity-60')}>
                {content}
            </span>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className={cn(
                    pill,
                    'relative pr-5 font-[inherit] outline-none hover:opacity-80',
                )}
            >
                {content}
                <ChevronDown className="absolute top-1/2 right-1.5 h-2.5 w-2.5 -translate-y-1/2 opacity-60" />
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="w-56 overflow-hidden p-1"
            >
                <RmoSubStatusMenuItems
                    cxStatuses={type === 'cx' ? statuses : []}
                    riderStatuses={type === 'rider' ? statuses : []}
                    cxStatusId={type === 'cx' ? statusId : null}
                    riderStatusId={type === 'rider' ? statusId : null}
                    onChange={(_, id) => onChange(id)}
                />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
