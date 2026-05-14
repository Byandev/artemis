import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import { useCallback, useEffect, useMemo, useState } from 'react';
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
    const [paginatedData, setPaginatedData] =
        useState<PaginatedData<PerCityBreakdownAnalytics> | null>(null);
    const [allCitiesData, setAllCitiesData] = useState<
        PerCityBreakdownAnalytics[]
    >([]);
    const [loading, setLoading] = useState<boolean>(true);
    const [currentView, setCurrentView] = useState<'heatmap' | 'table'>(
        'heatmap',
    );
    const [currentPage, setCurrentPage] = useState<number>(1);

    const fetchData = useCallback(
        async (page: number = 1) => {
            setLoading(true);
            try {
                const params = new URLSearchParams(queryString);
                params.append('page', String(page));

                const res = await fetch(
                    `/workspaces/${workspace.slug}/rts/analytics/group-by/cities?${params.toString()}`,
                    { credentials: 'same-origin' },
                );
                if (res.ok) {
                    const result = await res.json();
                    setPaginatedData(result);
                }
            } catch (error) {
                console.error('Error fetching cities breakdown:', error);
            } finally {
                setLoading(false);
            }
        },
        [workspace.slug, queryString],
    );

    // Fetch all cities data for heatmap (without pagination)
    const fetchAllCitiesData = useCallback(async () => {
        try {
            const params = new URLSearchParams(queryString);
            params.append('all', '1'); // Flag to get all data

            const res = await fetch(
                `/workspaces/${workspace.slug}/rts/analytics/group-by/cities?${params.toString()}`,
                { credentials: 'same-origin' },
            );
            if (res.ok) {
                const result = await res.json();
                setAllCitiesData(result.data ?? result);
            }
        } catch (error) {
            console.error('Error fetching all cities data:', error);
        }
    }, [workspace.slug, queryString]);

    useEffect(() => {
        setCurrentPage(1);
        fetchData(1);
        fetchAllCitiesData();
    }, [fetchData, fetchAllCitiesData]);

    const data = paginatedData?.data ?? [];

    const heatmapPoints: HeatPoint[] = useMemo(() => {
        return allCitiesData
            .map((city) => ({
                city_name: city.city_name,
                province_name: city.province_name,
                value: city.rts_rate_percentage,
            }))
            .filter((p): p is HeatPoint => p !== null);
    }, [allCitiesData]);

    const columns: ColumnDef<PerCityBreakdownAnalytics>[] = useMemo(
        () => [
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
        ],
        [],
    );

    if (loading) {
        return (
            <div className="w-full rounded-xl border bg-card p-6 shadow-sm">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-semibold tracking-tight">
                        Breakdown per Cities
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
                        Breakdown per Cities
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

    console.log(heatmapPoints);

    return (
        <div className="h-full rounded-lg border bg-card p-6">
            <div className="mb-6 flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
                <div>
                    <h3 className="text-lg font-semibold tracking-tight">
                        Breakdown per Cities
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        Geographical RTS rate distribution
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
                                currentView === 'heatmap'
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

            <div className={currentView === 'heatmap' ? 'block' : 'hidden'}>
                {heatmapPoints.length > 0 ? (
                    <HeatmapMap points={heatmapPoints} />
                ) : (
                    <div className="flex h-32 items-center justify-center">
                        <p className="text-center text-sm text-muted-foreground">
                            No data available.
                        </p>
                    </div>
                )}
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

export default BreakdownPerCities;
