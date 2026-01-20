import ComponentCard from '@/components/common/ComponentCard';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { StatusBadge } from '@/components/ui/status-badge';
import { useDateRange } from '@/hooks/use-date-range';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { Ad, AVAILABLE_AD_METRICS, MetricFilter, PaginatedAds } from '@/types/models/AdManager';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import moment from 'moment';
import { useEffect, useMemo, useState } from 'react';
import AdsManagerLayout from './partials/Layout';
import { MetricFiltersBar } from './partials/MetricFiltersBar';

interface PageProps {
    workspace: Workspace;
    ads: PaginatedAds;
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: {
            search?: string;
            status?: string;
            start_date?: string;
            end_date?: string;
        };
        metric_filters?: string;
        metrics?: string[];
    };
}

const AdsPage = ({ workspace, ads, query }: PageProps) => {
    const [selectedMetrics, setSelectedMetrics] = useState<string[]>(query?.metrics ?? []);
    const [metricFilters, setMetricFilters] = useState<MetricFilter[]>(() => {
        if (query?.metric_filters) {
            try {
                return JSON.parse(decodeURIComponent(query.metric_filters));
            } catch {
                return [];
            }
        }
        return [];
    });

    const requestedMetrics = selectedMetrics;

    // Use global date range state with automatic initialization from URL filters
    const { dateRange, setDateRange } = useDateRange({
        startDate: query?.filter?.start_date,
        endDate: query?.filter?.end_date
    });

    const dateRangeStr = useMemo(() => ({
        to: moment(dateRange?.to).format('YYYY-MM-DD'),
        from: moment(dateRange?.from).format('YYYY-MM-DD'),
    }), [dateRange]);

    const getNavParams = (overrides: Record<string, any> = {}) => ({
        sort: query?.sort,
        'filter[search]': searchValue || undefined,
        'filter[status]': statusFilter || undefined,
        'filter[start_date]': dateRange?.from ? dateRangeStr.from : undefined,
        'filter[end_date]': dateRange?.to ? dateRangeStr.to : undefined,
        metric_filters: metricFilters.length > 0 ? encodeURIComponent(JSON.stringify(metricFilters)) : undefined,
        metrics: requestedMetrics,
        page: 1,
        ...overrides,
    });

    const initialSorting = useMemo(() => {
        return toFrontendSort(query?.sort ?? null);
    }, [query?.sort]);

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [statusFilter, setStatusFilter] = useState(query?.filter?.status ?? '');

    // Debounce search
    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(
                `/workspaces/${workspace.slug}/ads-manager/ads`,
                {
                    ...getNavParams(),
                    'filter[search]': searchValue || undefined,
                    page: 1,
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['ads'],
                }
            );
        }, 500);

        return () => clearTimeout(timer);
    }, [searchValue]);

    // Sync date range with URL when dateRange changes
    useEffect(() => {
        router.get(
            `/workspaces/${workspace.slug}/ads-manager/ads`,
            getNavParams(),
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['ads'],
            },
        );
    }, [dateRange, dateRangeStr]);

    const handleStatusFilterChange = (value: string) => {
        setStatusFilter(value);

        router.get(
            `/workspaces/${workspace.slug}/ads-manager/ads`,
            getNavParams({ 'filter[status]': value || undefined }),
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['ads'],
            },
        );
    };

    const handleMetricFilterChange = (filters: MetricFilter[]) => {
        setMetricFilters(filters);

        router.get(
            `/workspaces/${workspace.slug}/ads-manager/ads`,
            getNavParams({ metric_filters: filters.length > 0 ? encodeURIComponent(JSON.stringify(filters)) : undefined }),
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['ads'],
            },
        );
    };

    const handleMetricsChange = (metrics: string[]) => {
        setSelectedMetrics(metrics);

        // Remove metric filters for disabled metrics
        const filteredMetricFilters = metricFilters.filter(filter => metrics.includes(filter.metric));
        setMetricFilters(filteredMetricFilters);

        router.get(
            `/workspaces/${workspace.slug}/ads-manager/ads`,
            getNavParams({
                metrics: metrics.length > 0 ? metrics : undefined,
                metric_filters: filteredMetricFilters.length > 0 ? encodeURIComponent(JSON.stringify(filteredMetricFilters)) : undefined,
            }),
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['ads'],
            },
        );
    };

    const clearFilters = () => {
        setSearchValue('');
        setStatusFilter('');
        setMetricFilters([]);
        setSelectedMetrics([]);
        setDateRange({
            from: moment().startOf('month').toDate(),
            to: moment().toDate()
        });
        router.get(`/workspaces/${workspace.slug}/ads-manager/ads`, {
            sort: undefined,
            'filter[search]': undefined,
            'filter[status]': undefined,
            'filter[start_date]': undefined,
            'filter[end_date]': undefined,
            metric_filters: undefined,
            metrics: undefined,
            page: 1,
        }, {
            preserveState: false,
            replace: true,
            preserveScroll: true,
        });
    };

    const columns: ColumnDef<Ad>[] = [
        {
            accessorKey: 'name',
            header: ({ column }) => <SortableHeader column={column} title="Ad Name" />,
            cell: ({ row }) => <div className="font-medium">{row.original.name}</div>,
        },
        {
            accessorKey: 'campaign_id',
            header: ({ column }) => <SortableHeader column={column} title="Campaign" />,
            cell: ({ row }) => <div>{row.original.campaign?.name || 'N/A'}</div>,
        },
        {
            accessorKey: 'ad_set_id',
            header: ({ column }) => <SortableHeader column={column} title="Ad Set" />,
            cell: ({ row }) => <div>{row.original.ad_set?.name || 'N/A'}</div>,
        },
        {
            accessorKey: 'status',
            header: ({ column }) => <SortableHeader column={column} title="Status" />,
            cell: ({ row }) => <StatusBadge status={row.original.status} />,
        },
        ...(selectedMetrics.includes('impressions') ? [{
            accessorKey: 'impressions',
            header: ({ column }: { column: any }) => <SortableHeader column={column} title="Impressions" />,
            cell: ({ row }: { row: any }) => Number(row.original.impressions || 0).toLocaleString(),
        } as ColumnDef<Ad>] : []),
        ...(selectedMetrics.includes('clicks') ? [{
            accessorKey: 'clicks',
            header: ({ column }: { column: any }) => <SortableHeader column={column} title="Clicks" />,
            cell: ({ row }: { row: any }) => Number(row.original.clicks || 0).toLocaleString(),
        } as ColumnDef<Ad>] : []),
        ...(selectedMetrics.includes('spend') ? [{
            accessorKey: 'spend',
            header: ({ column }: { column: any }) => <SortableHeader column={column} title="Spend" />,
            cell: ({ row }: { row: any }) => `₱${Number(row.original.spend || 0).toFixed(2)}`,
        } as ColumnDef<Ad>] : []),
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Ads`} />
            <AdsManagerLayout workspace={workspace} activeTab="ads">
                <div className="space-y-5 sm:space-y-6">
                    <ComponentCard desc="Manage your ads">
                        <div>
                            <MetricFiltersBar
                                metricFilters={metricFilters}
                                onMetricFiltersChange={handleMetricFilterChange}
                                searchValue={searchValue}
                                statusFilter={statusFilter}
                                selectedMetrics={selectedMetrics}
                                availableMetrics={AVAILABLE_AD_METRICS}
                                onSearchChange={setSearchValue}
                                onStatusChange={handleStatusFilterChange}
                                onMetricsChange={handleMetricsChange}
                                onClearFilters={clearFilters}
                                searchPlaceholder="Search ads..."
                            />

                            <DataTable
                                columns={columns}
                                data={ads?.data || []}
                                enableInternalPagination={false}
                                initialSorting={initialSorting}
                                meta={{ ...omit(ads, ['data']) }}
                                onFetch={(params) => {
                                    router.get(
                                        `/workspaces/${workspace.slug}/ads-manager/ads`,
                                        getNavParams({
                                            sort: params?.sort,
                                            page: params?.page ?? 1,
                                        }),
                                        {
                                            preserveState: false,
                                            replace: true,
                                            preserveScroll: true,
                                        },
                                    );
                                }}
                            />
                        </div>
                    </ComponentCard>
                </div>
            </AdsManagerLayout>
        </AppLayout>
    );
};

export default AdsPage;
