import React from 'react';
import { ChartConfig, ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import { Bar, BarChart, XAxis } from 'recharts';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { ColumnDef } from '@tanstack/react-table';

type BarSpec<T> = {
    dataKey: Extract<keyof T, string>;
    fill?: string;
    radius?: number;
    name?: string;
};

type Props<T> = {
    data: Array<T>;
    chartConfig?: ChartConfig;
    columns: ColumnDef<T>[];
    className?: string;
    title: string;
    xKey?: Extract<keyof T, string>;
    bars?: BarSpec<T>[];
    availableViews?: Array<'graph' | 'table' | 'heatmap'>;
    renderHeatmap?: (data: T[]) => React.ReactNode;
}

const BreakdownAnalyticsView = <T,>({
    data,
    chartConfig,
    columns,
    className,
    title,
    xKey,
    bars,
    availableViews,
    renderHeatmap,
}: Props<T>) => {
    const views = React.useMemo(() => (availableViews && availableViews.length ? availableViews : (['graph', 'table'] as const)), [availableViews]) as Array<'graph' | 'table' | 'heatmap'>;

    const [currentView, setCurrentView] = React.useState<typeof views[number]>(views[0]);

    return (
        <div className='border rounded-xl p-6 shadow-sm'>
            <div className='flex justify-between items-center mb-4'>
                <h2 className='text-lg font-medium mb-2'>{title}</h2>
                <div className='flex gap-2'>
                    <Button>Export</Button>

                    <div className='flex flex-row gap-2 mb-2 bg-gray-100 p-1 rounded-md w-fit'>
                        {views.map((view) => (
                            <Button
                                key={view}
                                variant="ghost"
                                size="sm"
                                className={`px-3 py-1 rounded-md font-medium focus:outline-none focus:ring-2 focus:ring-offset-1 ${currentView === view ? 'bg-white shadow-sm' : 'hover:bg-white/50'}`}
                                onClick={() => setCurrentView(view)}
                            >
                                {view[0].toUpperCase() + view.slice(1)}
                            </Button>
                        ))}
                    </div>
                </div>
            </div>

            {currentView === 'graph' ? (
                <ChartContainer config={chartConfig ?? {}} className={className}>
                    <BarChart accessibilityLayer data={data} height={100}>
                        <XAxis
                            dataKey={xKey}
                            tickLine={false}
                            tickMargin={10}
                            axisLine={false}
                            tickFormatter={(value) => String(value)}
                        />
                        <ChartTooltip content={<ChartTooltipContent />} />

                        {bars?.map((b) => (
                            <Bar
                                key={b.dataKey}
                                dataKey={b.dataKey}
                                fill={b.fill ?? 'var(--color-primary)'}
                                radius={b.radius ?? 4}
                                name={b.name}
                            />
                        ))}
                    </BarChart>
                </ChartContainer>
            ) : currentView === 'heatmap' ? (
                renderHeatmap ? (
                    <div className={className}>{renderHeatmap(data)}</div>
                ) : (
                    <div className='text-sm text-muted-foreground p-4'>No heatmap renderer provided.</div>
                )
            ) : (
                <DataTable columns={columns} data={data} />
            )}
        </div>
    );
}

export default BreakdownAnalyticsView;
