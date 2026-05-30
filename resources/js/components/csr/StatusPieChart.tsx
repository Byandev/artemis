import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import { FALLBACK_COLOR, STATUS_COLORS, statusConfig } from './constants';
import type { StatusBreakdownEntry } from './types';
import { Cell, Pie, PieChart } from 'recharts';

interface Props {
    data: StatusBreakdownEntry[];
}

export default function StatusPieChart({ data }: Props) {
    if (data.length === 0) {
        return (
            <div className="flex h-52 items-center justify-center text-sm text-gray-400 dark:text-gray-500">
                No orders for this period.
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-4 p-5">
            <ChartContainer id="csr-status-pie" config={statusConfig} className="mx-auto h-[140px] w-[140px]">
                <PieChart>
                    <Pie
                        data={data}
                        dataKey="count"
                        nameKey="status"
                        cx="50%"
                        cy="50%"
                        innerRadius={40}
                        outerRadius={65}
                        paddingAngle={2}
                        strokeWidth={0}
                    >
                        {data.map((entry) => (
                            <Cell key={entry.status} fill={STATUS_COLORS[entry.status] ?? FALLBACK_COLOR} />
                        ))}
                    </Pie>
                    <ChartTooltip content={<ChartTooltipContent hideLabel />} />
                </PieChart>
            </ChartContainer>
            <div className="grid grid-cols-2 gap-x-4 gap-y-1.5">
                {data.map((entry) => (
                    <div key={entry.status} className="flex items-center justify-between gap-2">
                        <div className="flex min-w-0 items-center gap-1.5">
                            <span
                                className="h-2 w-2 shrink-0 rounded-full"
                                style={{ backgroundColor: STATUS_COLORS[entry.status] ?? FALLBACK_COLOR }}
                            />
                            <span className="truncate text-[10px] text-gray-500 dark:text-gray-400">
                                {entry.status}
                            </span>
                        </div>
                        <span className="font-mono text-[10px] font-semibold text-gray-700 tabular-nums dark:text-gray-300">
                            {entry.count}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}
