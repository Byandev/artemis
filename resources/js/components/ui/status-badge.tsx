import clsx from 'clsx';

type StatusConfig = {
    label: string;
    className: string;
};

const STATUS_VARIANTS: Record<string, StatusConfig> = {
    active: {
        label: 'ACTIVE',
        className: 'bg-emerald-50 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800',
    },
    paused: {
        label: 'PAUSED',
        className: 'bg-amber-50 dark:bg-amber-900 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800',
    },
};

interface StatusBadgeProps {
    status: string;
    variant?: 'default' | 'compact';
}

export const StatusBadge = ({ status, variant = 'default' }: StatusBadgeProps) => {
    const config =
        STATUS_VARIANTS[status] ??
        { label: `UNKNOWN (${status})`, className: 'bg-red-50 dark:bg-red-900 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-800' };

    return (
        <span
            className={clsx(
                'inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-semibold whitespace-nowrap cursor-default',
                config.className
            )}
        >
            {config.label}
        </span>
    );
};
