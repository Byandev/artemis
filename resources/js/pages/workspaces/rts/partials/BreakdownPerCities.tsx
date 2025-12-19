import React, { useEffect, useState, useMemo } from 'react';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { ColumnDef } from '@tanstack/react-table';
import { Workspace } from '@/types/models/Workspace';
import HeatmapMap, { HeatPoint } from './HeatmapMap';

type PerCityBreakdownAnalytics = {
    id: number;
    name: string;
    city_name: string;
    province_name: string;
    total_orders: number;
    rts_rate_percentage: number;
    returned_count: number;
    delivered_count: number;
};

type Props = {
    workspace: Workspace;
    queryString: string;
};

const BreakdownPerCities = ({ workspace, queryString }: Props) => {
    const [data, setData] = useState<PerCityBreakdownAnalytics[]>([]);
    const [loading, setLoading] = useState<boolean>(true);
    const [currentView, setCurrentView] = useState<'heatmap' | 'table'>('heatmap');

    useEffect(() => {
        const fetchData = async () => {
            setLoading(true);
            try {
                const res = await fetch(
                    `/workspaces/${workspace.slug}/rts/analytics/group-by/cities${queryString ? `?${queryString}` : ''}`,
                    { credentials: 'same-origin' }
                );
                if (res.ok) {
                    const result = await res.json();
                    setData(result.data ?? []);
                }
            } catch (error) {
                console.error('Error fetching cities breakdown:', error);
            } finally {
                setLoading(false);
            }
        };

        fetchData();
    }, [workspace.slug, queryString]);

    const heatmapPoints: HeatPoint[] = useMemo(() => {
        return data
            .map((city) => ({
                city_name: city.city_name,
                province_name: city.province_name,
                value: city.rts_rate_percentage
            }))
            .filter((p): p is HeatPoint => p !== null);
    }, [data]);

    const columns: ColumnDef<PerCityBreakdownAnalytics>[] = useMemo(() => [
        { accessorKey: 'city_name', header: 'City Name' },
        { accessorKey: 'province_name', header: 'Province Name' },
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
                    <h3 className="text-lg font-semibold tracking-tight">Breakdown per Cities</h3>
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
                    <h3 className="text-lg font-semibold tracking-tight">Breakdown per Cities</h3>
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
                    <h3 className="text-lg font-semibold tracking-tight">Breakdown per Cities</h3>
                    <p className="text-sm text-muted-foreground">Geographical RTS rate distribution</p>
                </div>

                <div className="flex gap-2 items-center">
                    <Button size="sm" variant="outline">Export</Button>

                    <div className="flex flex-row gap-1 bg-muted p-1 rounded-lg w-fit">
                        <Button
                            variant="ghost"
                            size="sm"
                            className={`px-3 py-1.5 h-auto rounded-md text-xs font-medium transition-all ${currentView === 'heatmap'
                                ? 'bg-background shadow-sm'
                                : 'hover:bg-background/50'
                                }`}
                            onClick={() => setCurrentView('heatmap')}
                        >
                            Heatmap
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

            <div className={currentView === 'heatmap' ? 'block' : 'hidden'}>
                {heatmapPoints.length > 0 ? (
                    <HeatmapMap points={heatmapPoints} />
                ) : (
                    <div className="flex justify-center items-center h-32">
                        <p className="text-center text-sm text-muted-foreground">No data available.</p>
                    </div>
                )}
            </div>

            <div className={currentView === 'table' ? 'block' : 'hidden'}>
                <DataTable columns={columns} data={data} enableInternalPagination />
            </div>
        </div>
    );
};

export default BreakdownPerCities;
