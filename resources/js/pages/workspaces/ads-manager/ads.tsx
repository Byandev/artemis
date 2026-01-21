import ComponentCard from '@/components/common/ComponentCard';
import { Checkbox } from '@/components/ui/checkbox';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { StatusBadge } from '@/components/ui/status-badge';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { useAdsManagerSelectionStore } from '@/stores/useCampaignSelectionStore';
import { useHierarchicalFilter } from '@/hooks/useHierarchicalFilter';
import { AVAILABLE_AD_METRICS } from '@/types/models/AdManager';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import moment from 'moment';
import { useEffect, useMemo, useState } from 'react';
import { type DateRange } from "react-day-picker";
import AdsManagerLayout from './partials/Layout';
import { MetricFiltersBar } from './partials/MetricFiltersBar';

interface Ad {
    id: number;
    name: string;
    status: string;
    impressions?: number;
    clicks?: number;
    spend?: number;
    campaign: {
        id: number;
        name: string;
    };
    ad_set: {
        id: number;
        name: string;
    };
    created_at: string;
    updated_at: string;
}

interface PaginatedAds {
    data: Ad[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface MetricFilter {
    metric: string;
    operator: string;
    value: string;
}

const AdsPage = ({ workspace, ads, query }: { workspace: Workspace; ads: PaginatedAds; query?: { sort?: string; perPage?: number; page?: number; filter?: { search?: string; status?: string; start_date?: string; end_date?: string; campaign_ids?: string; ad_set_ids?: string }; metric_filters?: string; metrics?: string[] } }) => {
    const TABLE_ID = 'ads';
    const { selections, setSelectedRows, clearSelection } = useAdsManagerSelectionStore();
    const selectedRowIds = selections[TABLE_ID] || {};
    const { encodedCampaignIds, encodedAdSetIds } = useHierarchicalFilter();

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

    const initialSorting = useMemo(() => {
        return toFrontendSort(query?.sort ?? null);
    }, [query?.sort]);

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [statusFilter, setStatusFilter] = useState(query?.filter?.status ?? '');
    const [dateRange, setDateRange] = useState<DateRange | undefined>({
        from: query?.filter?.start_date ? moment(query.filter.start_date).toDate() : moment().startOf('month').toDate(),
        to: query?.filter?.end_date ? moment(query.filter.end_date).toDate() : moment().toDate()
    });

    // Memoize encoded metric filters string
    const encodedMetricFilters = useMemo(() => {
        return metricFilters.length > 0 ? encodeURIComponent(JSON.stringify(metricFilters)) : undefined;
    }, [metricFilters]);

    // Memoize nav params to prevent unnecessary re-renders
    const getNavParams = useMemo(() => {
        return (overrides: Record<string, any> = {}) => ({
            sort: query?.sort,
            'filter[search]': searchValue || undefined,
            'filter[status]': statusFilter || undefined,
            'filter[start_date]': dateRange?.from ? moment(dateRange.from).format('YYYY-MM-DD') : undefined,
            'filter[end_date]': dateRange?.to ? moment(dateRange.to).format('YYYY-MM-DD') : undefined,
            'filter[campaign_ids]': encodedCampaignIds,
            'filter[ad_set_ids]': encodedAdSetIds,
            metric_filters: encodedMetricFilters,
            metrics: requestedMetrics,
            page: 1,
            ...overrides,
        });
    }, [query?.sort, searchValue, statusFilter, dateRange, encodedCampaignIds, encodedAdSetIds, encodedMetricFilters, requestedMetrics]);

    const dateRangeStr = useMemo(() => ({
        to: moment(dateRange?.to).format('YYYY-MM-DD'),
        from: moment(dateRange?.from).format('YYYY-MM-DD'),
    }), [dateRange]);

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

    // Sync date range with URL on mount
    useEffect(() => {
        if (!query?.filter?.start_date || !query?.filter?.end_date) {
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
        }
    }, []);

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
            getNavParams({ metric_filters: filters.length > 0 ? encodeURIComponent(JSON.stringify(filters)) : undefined, page: 1 }),
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['ads'],
            },
        );
    };

    const handleDateRangeChange = (range: DateRange | undefined) => {
        setDateRange(range);

        router.get(
            `/workspaces/${workspace.slug}/ads-manager/ads`,
            getNavParams({
                'filter[start_date]': range?.from ? moment(range.from).format('YYYY-MM-DD') : undefined,
                'filter[end_date]': range?.to ? moment(range.to).format('YYYY-MM-DD') : undefined,
            }),
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
                page: 1,
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
        clearSelection(TABLE_ID);
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
            'filter[campaign_ids]': undefined,
            'filter[ad_set_ids]': undefined,
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
            id: 'select',
            header: ({ table }) => (
                <div className="flex justify-start">
                    <Checkbox
                        checked={
                            ads?.data?.length > 0 && ads.data.every(row => selectedRowIds[row.id])
                                ? true
                                : ads?.data?.some(row => selectedRowIds[row.id])
                                    ? 'indeterminate'
                                    : false
                        }
                        onCheckedChange={(value) => {
                            if (value) {
                                setSelectedRows(
                                    TABLE_ID,
                                    (ads?.data || []).reduce(
                                        (acc, row) => ({ ...acc, [row.id]: true }),
                                        {}
                                    )
                                );
                            } else {
                                clearSelection(TABLE_ID);
                            }
                        }}
                        aria-label="Select all"
                    />
                </div>
            ),
            cell: ({ row }) => (
                <Checkbox
                    checked={selectedRowIds[row.original.id] || false}
                    onCheckedChange={(value) => {
                        setSelectedRows(TABLE_ID, {
                            ...selectedRowIds,
                            [row.original.id]: !!value,
                        });
                    }}
                    aria-label="Select row"
                />
            ),
            enableSorting: false,
            enableHiding: false,
        },
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
                                dateRange={dateRange}
                                dateRangeStr={dateRangeStr}
                                selectedMetrics={selectedMetrics}
                                availableMetrics={AVAILABLE_AD_METRICS}
                                onSearchChange={setSearchValue}
                                onStatusChange={handleStatusFilterChange}
                                onDateRangeChange={handleDateRangeChange}
                                onMetricsChange={handleMetricsChange}
                                onClearFilters={clearFilters}
                                searchPlaceholder="Search ads..."
                            />

                            <DataTable
                                columns={columns}
                                data={ads?.data || []}
                                enableInternalPagination={false}
                                enableRowSelection={true}
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
