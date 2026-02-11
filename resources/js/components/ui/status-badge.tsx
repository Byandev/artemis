import clsx from 'clsx';

type StatusConfig = {
    className: string;
};

const STATUS_VARIANTS: Record<string, StatusConfig> = {
    active: {
        className: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    },
    paused: {
        className: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    },
    archived: {
        className: 'bg-slate-100 text-slate-700 dark:bg-slate-900/30 dark:text-slate-400',
    },
};

interface StatusBadgeProps {
    status: string;
}

export const StatusBadge = ({ status }: StatusBadgeProps) => {
    const normalizedStatus = status.toLowerCase();
    const config =
        STATUS_VARIANTS[normalizedStatus] ??
        { className: 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400' };

    return (
        <span
            className={clsx(
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                config.className
            )}
        >
            {status.toUpperCase()}
        </span>
    );
};
