import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import { rtsConfig } from './constants';
import { fullDate, shortDate } from './formatters';
import type { DailyTrendEntry } from './types';
import { CartesianGrid, Line, LineChart, XAxis, YAxis } from 'recharts';

interface Props {
    data: DailyTrendEntry[];
}

export default function RtsTrendChart({ data }: Props) {
    if (data.length === 0) {
        return (
            <div className="flex h-52 items-center justify-center text-sm text-gray-400 dark:text-gray-500">
                No data.
            </div>
        );
    }

    return (
        <div className="p-5">
            <ChartContainer id="csr-rts" config={rtsConfig} className="h-[200px] w-full">
                <LineChart data={data}>
                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="currentColor" className="text-black/5 dark:text-white/5" />
                    <XAxis dataKey="date" tickLine={false} axisLine={false} tick={{ fontSize: 10 }} tickFormatter={shortDate} />
                    <YAxis tickLine={false} axisLine={false} tick={{ fontSize: 10 }} tickFormatter={(v) => `${v}%`} domain={[0, 'auto']} width={36} />
                    <ChartTooltip
                        content={
                            <ChartTooltipContent
                                labelFormatter={fullDate}
                                formatter={(v) => [`${Number(v).toFixed(1)}%`, 'RTS Rate']}
                            />
                        }
                    />
                    <Line
                        type="monotone"
                        dataKey="rts_rate"
                        stroke="var(--color-rts_rate)"
                        strokeWidth={2}
                        dot={{ r: 3, fill: 'var(--color-rts_rate)', strokeWidth: 0 }}
                        activeDot={{ r: 5, strokeWidth: 2 }}
                    />
                </LineChart>
            </ChartContainer>
        </div>
    );
}
