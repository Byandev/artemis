import React from 'react';
type Props = {
    title: string;
    value: string | number;
    className?: string;
}

const MetricsCard = ({ title, value, className = '' }: Props) => {
    return (
        <div className={`rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] ${className}`}>
            <p className="text-sm text-gray-500 dark:text-gray-400">
                {title}
            </p>
            <div className="mt-3 flex items-end justify-between">
                <div>
                    <h4 className="text-2xl font-bold text-gray-800 dark:text-white/90">
                        {typeof value === 'number' && title.toLowerCase().includes('amount')
                            ? new Intl.NumberFormat('en-US', { style: 'currency', currency: 'PHP' }).format(value)
                            : value}
                    </h4>
                </div>
            </div>
        </div>
    )
}

export default MetricsCard;
