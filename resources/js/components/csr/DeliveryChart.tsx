import { ChartContainer, ChartLegend, ChartLegendContent, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import { deliveryConfig } from './constants';
import { fullDate, shortDate } from './formatters';
import type { DailyTrendEntry } from './types';
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';

interface Props {
    data: DailyTrendEntry[];
}

export default function DeliveryChart({ data }: Props) {
    if (data.length === 0) {
        return (
            <div className="flex h-52 items-center justify-center text-sm text-gray-400 dark:text-gray-500">
                No data.
            </div>
        );
    }

    return (
        <div className="p-5">
            <ChartContainer id="csr-delivery" config={deliveryConfig} className="h-[200px] w-full">
                <BarChart data={data} barGap={2} barCategoryGap="22%">
                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="currentColor" className="text-black/5 dark:text-white/5" />
                    <XAxis dataKey="date" tickLine={false} axisLine={false} tick={{ fontSize: 10 }} tickFormatter={shortDate} />
                    <YAxis tickLine={false} axisLine={false} tick={{ fontSize: 10 }} width={24} />
                    <ChartTooltip content={<ChartTooltipContent labelFormatter={fullDate} />} />
                    <Bar dataKey="delivered" fill="var(--color-delivered)" radius={[4, 4, 0, 0]} />
                    <Bar dataKey="returning" fill="var(--color-returning)" radius={[4, 4, 0, 0]} />
                    <ChartLegend content={<ChartLegendContent />} />
                </BarChart>
            </ChartContainer>
        </div>
    );
}
