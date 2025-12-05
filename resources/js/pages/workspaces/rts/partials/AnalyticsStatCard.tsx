import React from 'react';
import { Card, CardHeader, CardContent } from '@/components/ui/card';

type Props = {
    title: string;
    value: string | number;
    className?: string;
}

const AnalyticsStatCard = ({ title, value, className = '' }: Props) => {
    return (
        <Card className={`p-4 gap-5 flex flex-col ${className}`}>
            <CardHeader className="p-0">
                <div>
                    <span className="text-xl sm:text-2xl md:text-3xl font-extrabold">
                        {typeof value === 'number'
                            ? new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value)
                            : value}
                    </span>
                </div>
            </CardHeader>
            <CardContent className="p-0">
                <p className="text-sm sm:text-md text-muted-foreground">{title}</p>
            </CardContent>
        </Card>
    )
}

export default AnalyticsStatCard;
