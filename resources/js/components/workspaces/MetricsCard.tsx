import React from 'react';
type Props = {
    title: string;
    value: string | number;
    className?: string;
}

const MetricsCard = ({ title, value, className = '' }: Props) => {
    return (
        <div className={`relative overflow-hidden rounded-xl border bg-card p-6 shadow-sm transition-all hover:shadow-md ${className}`}>
            <div className="flex flex-col gap-3">
                <p className="text-sm font-medium text-muted-foreground uppercase tracking-wide">
                    {title}
                </p>
                <h4 className="text-3xl font-bold tracking-tight">
                    {typeof value === 'number' && title.toLowerCase().includes('amount')
                        ? new Intl.NumberFormat('en-US', { style: 'currency', currency: 'PHP' }).format(value)
                        : value}
                </h4>
            </div>
        </div>
    )
}

export default MetricsCard;
