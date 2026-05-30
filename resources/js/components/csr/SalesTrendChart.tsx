import { ChartContainer, ChartLegend, ChartLegendContent, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import { salesConfig } from './constants';
import { fullDate, pesoCompact, peso, shortDate } from './formatters';
import type { DailyTrendEntry } from './types';
import { Area, AreaChart, CartesianGrid, Line, XAxis, YAxis } from 'recharts';

interface Props {
    data: DailyTrendEntry[];
}

export default function SalesTrendChart({ data }: Props) {
    if (data.length === 0) {
        return (
            <div className="flex h-64 items-center justify-center text-sm text-gray-400 dark:text-gray-500">
                No trend data for this period.
            </div>
        );
    }

    return (
        <div className="p-5">
            <ChartContainer id="csr-sales-trend" config={salesConfig} className="h-[280px] w-full">
                <AreaChart data={data}>
                    <defs>
                        <linearGradient id="salesGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="5%" stopColor="var(--color-sales)" stopOpacity={0.2} />
                            <stop offset="95%" stopColor="var(--color-sales)" stopOpacity={0} />
                        </linearGradient>
                    </defs>
                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="currentColor" className="text-black/5 dark:text-white/5" />
                    <XAxis dataKey="date" tickLine={false} axisLine={false} tickMargin={10} tick={{ fontSize: 10 }} tickFormatter={shortDate} />
                    <YAxis yAxisId="sales" orientation="left" tickLine={false} axisLine={false} tick={{ fontSize: 10 }} tickFormatter={pesoCompact} width={52} />
                    <YAxis yAxisId="orders" orientation="right" tickLine={false} axisLine={false} tick={{ fontSize: 10 }} width={30} />
                    <ChartTooltip
                        content={
                            <ChartTooltipContent
                                labelFormatter={fullDate}
                                formatter={(v, name) =>
                                    name === 'sales'
                                        ? [peso(Number(v)), 'Sales']
                                        : [Number(v).toLocaleString(), 'Orders']
                                }
                            />
                        }
                    />
                    <Area yAxisId="sales" type="monotone" dataKey="sales" stroke="var(--color-sales)" strokeWidth={2} fill="url(#salesGrad)" dot={false} activeDot={{ r: 4, strokeWidth: 2 }} />
                    <Line yAxisId="orders" type="monotone" dataKey="orders" stroke="var(--color-orders)" strokeWidth={2} dot={false} activeDot={{ r: 4, strokeWidth: 2 }} />
                    <ChartLegend content={<ChartLegendContent />} />
                </AreaChart>
            </ChartContainer>
        </div>
    );
}
