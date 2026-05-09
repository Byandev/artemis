import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { ColumnDef } from '@tanstack/react-table';
import { ApexOptions } from 'apexcharts';
import { omit } from 'lodash';
import { useCallback, useEffect, useMemo, useState } from 'react';
import Chart from 'react-apexcharts';

type BreakDownAnalytics = {
    id: number;
    name: string;
    total_orders: number;
    rts_rate_percentage: number;
    returned_count: number;
    delivered_count: number;
};

type Props = {
    workspace: Workspace;
    queryString: string;
};

const BreakdownPerShops = ({ workspace, queryString }: Props) => {
    const [paginatedData, setPaginatedData] =
        useState<PaginatedData<BreakDownAnalytics> | null>(null);
    const [allData, setAllData] = useState<BreakDownAnalytics[]>([]);
    const [loading, setLoading] = useState<boolean>(true);
    const [currentView, setCurrentView] = useState<'graph' | 'table'>('graph');
    const [currentPage, setCurrentPage] = useState<number>(1);

    const fetchData = useCallback(
        async (page: number = 1) => {
            setLoading(true);
            try {
                const params = new URLSearchParams(queryString);
                params.append('page', String(page));

                const res = await fetch(
                    `/workspaces/${workspace.slug}/rts/analytics/group-by/shops?${params.toString()}`,
                    { credentials: 'same-origin' },
                );
                if (res.ok) {
                    const result = await res.json();
                    setPaginatedData(result);
                }
            } catch (error) {
                console.error('Error fetching shops breakdown:', error);
            } finally {
                setLoading(false);
            }
        },
        [workspace.slug, queryString],
    );

    // Fetch all data for graph (without pagination)
    const fetchAllData = useCallback(async () => {
        try {
            const params = new URLSearchParams(queryString);
            params.append('all', '1');

            const res = await fetch(
                `/workspaces/${workspace.slug}/rts/analytics/group-by/shops?${params.toString()}`,
                { credentials: 'same-origin' },
            );
            if (res.ok) {
                const result = await res.json();
                setAllData(result.data ?? result);
            }
        } catch (error) {
            console.error('Error fetching all shops data:', error);
        }
    }, [workspace.slug, queryString]);

    useEffect(() => {
        setCurrentPage(1);
        fetchData(1);
        fetchAllData();
    }, [fetchData, fetchAllData]);

    const data = paginatedData?.data ?? [];

    const percentageFormatter = useCallback(
        (value: number) => `${value.toFixed(2)}%`,
        [],
    );

    const chartOptions: ApexOptions = useMemo(
        () => ({
            colors: ['#10d3a1'],
            chart: {
                fontFamily: 'Outfit, sans-serif',
                type: 'bar',
                height: 250,
                toolbar: {
                    show: false,
                },
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '39%',
                    borderRadius: 5,
                    borderRadiusApplication: 'end',
                },
            },
            dataLabels: {
                enabled: false,
            },
            stroke: {
                show: true,
                width: 4,
                colors: ['transparent'],
            },
            xaxis: {
                categories: allData.map((item) => item.name),
                axisBorder: {
                    show: false,
                },
                axisTicks: {
                    show: false,
                },
                labels: {
                    formatter: (val: string) =>
                        val
                            .split(/\s+/)
                            .map((w) => w[0]?.toUpperCase() ?? '')
                            .join(''),
                },
            },
            legend: {
                show: true,
                position: 'top',
                horizontalAlign: 'left',
                fontFamily: 'Outfit',
            },
            yaxis: {
                title: {
                    text: undefined,
                },
                labels: {
                    formatter: percentageFormatter,
                },
            },
            grid: {
                yaxis: {
                    lines: {
                        show: true,
                    },
                },
            },
            fill: {
                opacity: 1,
            },
            tooltip: {
                x: {
                    show: false,
                },
                y: {
                    formatter: percentageFormatter,
                },
            },
        }),
        [allData, percentageFormatter],
    );

    const chartSeries = useMemo(
        () => [
            {
                name: 'RTS Rate %',
                data: allData.map((item) => item.rts_rate_percentage),
            },
        ],
        [allData],
    );

    const columns: ColumnDef<BreakDownAnalytics>[] = useMemo(
        () => [
            { accessorKey: 'name', header: 'Shop' },
            { accessorKey: 'total_orders', header: 'Total Orders' },
            { accessorKey: 'returned_count', header: 'Returned' },
            { accessorKey: 'delivered_count', header: 'Delivered' },
            {
                accessorKey: 'rts_rate_percentage',
                header: 'RTS Rate',
                cell: ({ row }) => `${row.original.rts_rate_percentage}%`,
            },
        ],
        [],
    );

    if (loading) {
        return (
            <div className="w-full rounded-xl border bg-card p-6 shadow-sm">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-semibold tracking-tight">
                        Breakdown per Shops
                    </h3>
                </div>
                <div className="flex h-32 items-center justify-center">
                    <p className="text-center text-sm text-muted-foreground">
                        Loading...
                    </p>
                </div>
            </div>
        );
    }

    if (data.length === 0) {
        return (
            <div className="w-full rounded-xl border bg-card p-6 shadow-sm">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-semibold tracking-tight">
                        Breakdown per Shops
                    </h3>
                </div>
                <div className="flex h-32 items-center justify-center">
                    <p className="text-center text-sm text-muted-foreground">
                        No data available.
                    </p>
                </div>
            </div>
        );
    }

    return (
        <div className="h-fit rounded-xl border bg-card p-6 shadow-sm">
            <div className="mb-6 flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
                <div>
                    <h3 className="text-lg font-semibold tracking-tight">
                        Breakdown per Shops
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        Analyze RTS rates for different shops
                    </p>
                </div>

                <div className="flex items-center gap-2">
                    <Button size="sm" variant="outline">
                        Export
                    </Button>

                    <div className="flex w-fit flex-row gap-1 rounded-lg bg-muted p-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            className={`h-auto rounded-md px-3 py-1.5 text-xs font-medium transition-all ${
                                currentView === 'graph'
                                    ? 'bg-background shadow-sm'
                                    : 'hover:bg-background/50'
                            }`}
                            onClick={() => setCurrentView('graph')}
                        >
                            Graph
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            className={`h-auto rounded-md px-3 py-1.5 text-xs font-medium transition-all ${
                                currentView === 'table'
                                    ? 'bg-background shadow-sm'
                                    : 'hover:bg-background/50'
                            }`}
                            onClick={() => setCurrentView('table')}
                        >
                            Table
                        </Button>
                    </div>
                </div>
            </div>

            <div className={currentView === 'graph' ? 'block' : 'hidden'}>
                <div className="custom-scrollbar max-w-full overflow-x-auto">
                    <div className="-ml-5 w-full pl-2">
                        <Chart
                            options={chartOptions}
                            series={chartSeries}
                            type="bar"
                            height={250}
                        />
                    </div>
                </div>
            </div>

            <div className={currentView === 'table' ? 'block' : 'hidden'}>
                <DataTable
                    columns={columns}
                    data={data}
                    enableInternalPagination={false}
                    meta={
                        paginatedData
                            ? { ...omit(paginatedData, ['data']) }
                            : undefined
                    }
                    onFetch={(params) => {
                        const page = params?.page ?? 1;
                        setCurrentPage(page);
                        fetchData(page);
                    }}
                />
            </div>
        </div>
    );
};

export default BreakdownPerShops;
