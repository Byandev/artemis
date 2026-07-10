import { cn } from '@/lib/utils';

const EmptyCell = () => <span className="text-gray-400">—</span>;

const pill =
    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-medium whitespace-nowrap';

/** Known ERP statuses get a colour; anything unexpected falls back to neutral. */
function statusTone(status: string): string {
    switch (status.trim().toUpperCase()) {
        case 'ACTIVE':
            return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400';
        case 'INACTIVE':
            return 'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-400';
        default:
            return 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400';
    }
}

export const StatusBadge = ({ value }: { value: string | null }) =>
    value ? (
        <span className={cn(pill, statusTone(value))}>
            <span className="h-1.5 w-1.5 rounded-full bg-current opacity-70" />
            {value}
        </span>
    ) : (
        <EmptyCell />
    );

function platformTone(platform: string): string {
    switch (platform.trim().toUpperCase()) {
        case 'FACEBOOK':
            return 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400';
        case 'TIKTOK':
            return 'bg-fuchsia-50 text-fuchsia-700 dark:bg-fuchsia-500/10 dark:text-fuchsia-400';
        default:
            return 'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-400';
    }
}

export const PlatformBadge = ({ value }: { value: string | null }) =>
    value ? (
        <span className={cn(pill, platformTone(value))}>{value}</span>
    ) : (
        <EmptyCell />
    );
