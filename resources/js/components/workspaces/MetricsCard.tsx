import { CircleHelp, Info } from 'lucide-react';
import React from 'react';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type Props = {
    title: string;
    value: string | number;
    icon?: React.ReactNode;
    tooltip?: string;
    color?:
        | 'blue'
        | 'green'
        | 'red'
        | 'yellow'
        | 'purple'
        | 'orange'
        | 'default';
    className?: string;
    trend?: {
        value: number;
        isPositive: boolean;
    };
};

const colorVariants = {
    purple: {
        bg: 'bg-purple-50 dark:bg-purple-500/10',
        text: 'text-purple-600 dark:text-purple-400',
        icon: 'text-purple-500 dark:text-purple-400',
        border: 'border-purple-100 dark:border-purple-800',
    },
    orange: {
        bg: 'bg-orange-50 dark:bg-orange-500/10',
        text: 'text-orange-600 dark:text-orange-400',
        icon: 'text-orange-500 dark:text-orange-400',
        border: 'border-orange-100 dark:border-orange-800',
    },
    blue: {
        bg: 'bg-blue-50 dark:bg-blue-500/10',
        text: 'text-blue-600 dark:text-blue-400',
        icon: 'text-blue-500 dark:text-blue-400',
        border: 'border-blue-100 dark:border-blue-800',
    },
    green: {
        bg: 'bg-green-50 dark:bg-green-500/10',
        text: 'text-green-600 dark:text-green-400',
        icon: 'text-green-500 dark:text-green-400',
        border: 'border-green-100 dark:border-green-800',
    },
    red: {
        bg: 'bg-red-50 dark:bg-red-500/10',
        text: 'text-red-600 dark:text-red-400',
        icon: 'text-red-500 dark:text-red-400',
        border: 'border-red-100 dark:border-red-800',
    },
    yellow: {
        bg: 'bg-yellow-50 dark:bg-yellow-500/10',
        text: 'text-yellow-600 dark:text-yellow-400',
        icon: 'text-yellow-500 dark:text-yellow-400',
        border: 'border-yellow-100 dark:border-yellow-800',
    },
    default: {
        bg: '',
        text: 'text-gray-800 dark:text-white/90',
        icon: 'text-gray-500 dark:text-gray-400',
        border: 'border-gray-200 dark:border-gray-800',
    },
};

const MetricsCard = ({
    title,
    value,
    icon,
    tooltip,
    color = 'default',
    className = '',
    trend,
}: Props) => {
    const colors = colorVariants[color];

    // Format currency if needed
    const formattedValue =
        typeof value === 'number' && title.toLowerCase().includes('amount')
            ? new Intl.NumberFormat('en-US', {
                  style: 'currency',
                  currency: 'PHP',
              }).format(value)
            : value;

    return (
        <div
            className={`group relative rounded-lg border bg-white p-5 transition-all duration-200 hover:shadow-md ${className}`}
        >
            {/* Header with title and optional tooltip */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        {title}
                    </p>
                    {tooltip && (
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <button
                                        type="button"
                                        className="text-gray-400 transition-colors hover:text-gray-600 dark:hover:text-gray-300"
                                    >
                                        <CircleHelp className="h-3.5 w-3.5" />
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent
                                    side="top"
                                    className="max-w-[200px] text-xs"
                                >
                                    <p>{tooltip}</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    )}
                </div>
                {icon && (
                    <span
                        className={`${colors.icon} ${colors.bg} rounded-md p-1.5`}
                    >
                        {icon}
                    </span>
                )}
            </div>

            {/* Value and trend */}
            <div className="mt-3 flex items-end justify-between">
                <div>
                    <h4 className={`text-2xl font-bold`}>{formattedValue}</h4>
                </div>
            </div>
        </div>
    );
};

export default MetricsCard;
