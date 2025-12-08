import React from 'react';
import { ChartConfig, ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import { Bar, BarChart, XAxis } from 'recharts';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { ColumnDef } from '@tanstack/react-table';
import HeatmapMap, { HeatPoint } from './HeatmapMap';

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
    heatmapPoints?: HeatPoint[];
    loading?: boolean;
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
    heatmapPoints,
    loading
}: Props<T>) => {
    const views = React.useMemo(() => (availableViews && availableViews.length ? availableViews : (['graph', 'table'] as const)), [availableViews]) as Array<'graph' | 'table' | 'heatmap'>;

    const [currentView, setCurrentView] = React.useState<typeof views[number]>(views[0]);

    if (loading) {
        return (
            <div className='rounded-xl border bg-card p-6 shadow-sm w-full'>
                <div className='flex justify-between items-center mb-4'>
                    <h3 className='text-lg font-semibold tracking-tight'>{title}</h3>
                </div>
                <div className='flex justify-center items-center h-32'>
                    <p className='text-center text-sm text-muted-foreground'>Loading...</p>
                </div>
            </div>
        );
    }

    if (data.length === 0) {
        return (
            <NoDataView title={title} />
        );
    }

    return (
        <div className='rounded-xl border bg-card p-6 shadow-sm'>
            <div className='flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4'>
                <h3 className='text-lg font-semibold tracking-tight'>{title}</h3>
                <div className='flex gap-2 items-center'>
                    <Button size="sm" variant="outline">Export</Button>

                    <div className='flex flex-row gap-1 bg-muted p-1 rounded-lg w-fit'>
                        {views.map((view) => (
                            <Button
                                key={view}
                                variant="ghost"
                                size="sm"
                                className={`px-3 py-1.5 h-auto rounded-md text-xs font-medium transition-all ${currentView === view ? 'bg-background shadow-sm' : 'hover:bg-background/50'}`}
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
                                fill={b.fill ?? 'hsl(var(--primary))'}
                                radius={b.radius ?? 8}
                                name={b.name}
                            />
                        ))}
                    </BarChart>
                </ChartContainer>
            ) : currentView === 'heatmap' ? (
                <>
                    {heatmapPoints && heatmapPoints.length > 0 ? (
                        <HeatmapMap points={heatmapPoints} />
                    ) : (
                        <NoDataView title={title} />
                    )}
                </>
            ) : (
                <DataTable columns={columns} data={data} enableInternalPagination />
            )}
        </div>
    );
}

const NoDataView = ({ title }: { title: string }) => {
    return (
        <div className='rounded-xl border bg-card p-6 shadow-sm w-full'>
            <div className='flex justify-between items-center mb-4'>
                <h3 className='text-lg font-semibold tracking-tight'>{title}</h3>
            </div>
            <div className='flex justify-center items-center h-32'>
                <p className='text-center text-sm text-muted-foreground'>No data available.</p>
            </div>
        </div>
    );
}

export default BreakdownAnalyticsView;
