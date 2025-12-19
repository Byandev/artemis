import React, { useEffect, useState, useCallback, useMemo } from 'react';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { ColumnDef } from '@tanstack/react-table';
import Chart from "react-apexcharts";
import { ApexOptions } from "apexcharts";
import { Workspace } from '@/types/models/Workspace';
import { PaginatedData } from '@/types';
import { omit } from 'lodash';

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
    const [paginatedData, setPaginatedData] = useState<PaginatedData<BreakDownAnalytics> | null>(null);
    const [loading, setLoading] = useState<boolean>(true);
    const [currentView, setCurrentView] = useState<'graph' | 'table'>('graph');
    const [currentPage, setCurrentPage] = useState<number>(1);

    const fetchData = useCallback(async (page: number = 1) => {
        setLoading(true);
        try {
            const params = new URLSearchParams(queryString);
            params.append('page', String(page));

            const res = await fetch(
                `/workspaces/${workspace.slug}/rts/analytics/group-by/shops?${params.toString()}`,
                { credentials: 'same-origin' }
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
    }, [workspace.slug, queryString]);

    useEffect(() => {
        setCurrentPage(1);
        fetchData(1);
    }, [fetchData]);

    const data = paginatedData?.data ?? [];

    const percentageFormatter = useCallback((value: number) => `${value.toFixed(2)}%`, []);

    const chartOptions: ApexOptions = useMemo(() => ({
        colors: ["#10d3a1"],
        chart: {
            fontFamily: "Outfit, sans-serif",
            type: "bar",
            height: 250,
            toolbar: {
                show: false,
            },
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: "39%",
                borderRadius: 5,
                borderRadiusApplication: "end",
            },
        },
        dataLabels: {
            enabled: false,
        },
        stroke: {
            show: true,
            width: 4,
            colors: ["transparent"],
        },
        xaxis: {
            categories: data.map((item) => item.name),
            axisBorder: {
                show: false,
            },
            axisTicks: {
                show: false,
            },
        },
        legend: {
            show: true,
            position: "top",
            horizontalAlign: "left",
            fontFamily: "Outfit",
        },
        yaxis: {
            title: {
                text: undefined,
            },
            labels: {
                formatter: percentageFormatter
            }
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
    }), [data, percentageFormatter]);

    const chartSeries = useMemo(() => [{
        name: 'RTS Rate %',
        data: data.map((item) => item.rts_rate_percentage),
    }], [data]);

    const columns: ColumnDef<BreakDownAnalytics>[] = useMemo(() => [
        { accessorKey: 'name', header: 'Shop' },
        { accessorKey: 'total_orders', header: 'Total Orders' },
        { accessorKey: 'returned_count', header: 'Returned' },
        { accessorKey: 'delivered_count', header: 'Delivered' },
        {
            accessorKey: 'rts_rate_percentage',
            header: 'RTS Rate',
            cell: ({ row }) => `${row.original.rts_rate_percentage}%`,
        },
    ], []);

    if (loading) {
        return (
            <div className="rounded-xl border bg-card p-6 shadow-sm w-full">
                <div className="flex justify-between items-center mb-4">
                    <h3 className="text-lg font-semibold tracking-tight">Breakdown per Shops</h3>
                </div>
                <div className="flex justify-center items-center h-32">
                    <p className="text-center text-sm text-muted-foreground">Loading...</p>
                </div>
            </div>
        );
    }

    if (data.length === 0) {
        return (
            <div className="rounded-xl border bg-card p-6 shadow-sm w-full">
                <div className="flex justify-between items-center mb-4">
                    <h3 className="text-lg font-semibold tracking-tight">Breakdown per Shops</h3>
                </div>
                <div className="flex justify-center items-center h-32">
                    <p className="text-center text-sm text-muted-foreground">No data available.</p>
                </div>
            </div>
        );
    }

    return (
        <div className="rounded-xl border bg-card p-6 shadow-sm h-fit">
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <div>
                    <h3 className="text-lg font-semibold tracking-tight">Breakdown per Shops</h3>
                    <p className="text-sm text-muted-foreground">Analyze RTS rates for different shops</p>
                </div>

                <div className="flex gap-2 items-center">
                    <Button size="sm" variant="outline">Export</Button>

                    <div className="flex flex-row gap-1 bg-muted p-1 rounded-lg w-fit">
                        <Button
                            variant="ghost"
                            size="sm"
                            className={`px-3 py-1.5 h-auto rounded-md text-xs font-medium transition-all ${currentView === 'graph'
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
                            className={`px-3 py-1.5 h-auto rounded-md text-xs font-medium transition-all ${currentView === 'table'
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
                <div className="max-w-full overflow-x-auto custom-scrollbar">
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
                    meta={paginatedData ? { ...omit(paginatedData, ['data']) } : undefined}
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
