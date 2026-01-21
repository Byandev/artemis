import ComponentCard from '@/components/common/ComponentCard';
import { Checkbox } from '@/components/ui/checkbox';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { StatusBadge } from '@/components/ui/status-badge';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { useAdsManagerSelectionStore } from '@/stores/useCampaignSelectionStore';
import { useHierarchicalFilter } from '@/hooks/useHierarchicalFilter';
import { AdSet, AVAILABLE_AD_METRICS, PaginatedAdSets } from '@/types/models/AdManager';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import moment from 'moment';
import { useEffect, useMemo, useState } from 'react';
import { type DateRange } from "react-day-picker";
import AdsManagerLayout from './partials/Layout';
import { MetricFiltersBar } from './partials/MetricFiltersBar';

interface MetricFilter {
    metric: string;
    operator: string;
    value: string;
}

const AdSetsPage = ({ workspace, adSets, query }: { workspace: Workspace; adSets: PaginatedAdSets; query?: { sort?: string; perPage?: number; page?: number; filter?: { search?: string; status?: string; start_date?: string; end_date?: string; campaign_ids?: string; ad_ids?: string }; metric_filters?: string; metrics?: string[] } }) => {
    const TABLE_ID = 'adSets';
    const { selections, setSelectedRows, clearSelection } = useAdsManagerSelectionStore();
    const selectedRowIds = selections[TABLE_ID] || {};
    const { encodedCampaignIds, encodedAdIds } = useHierarchicalFilter();

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

    // Memoize nav params to prevent unnecessary re-renders
    const getNavParams = useMemo(() => {
        return (overrides: Record<string, any> = {}) => ({
            sort: query?.sort,
            'filter[search]': searchValue || undefined,
            'filter[status]': statusFilter || undefined,
            'filter[start_date]': dateRange?.from ? moment(dateRange.from).format('YYYY-MM-DD') : undefined,
            'filter[end_date]': dateRange?.to ? moment(dateRange.to).format('YYYY-MM-DD') : undefined,
            'filter[campaign_ids]': encodedCampaignIds,
            'filter[ad_ids]': encodedAdIds,
            metric_filters: metricFilters.length > 0 ? encodeURIComponent(JSON.stringify(metricFilters)) : undefined,
            metrics: requestedMetrics,
            page: 1,
            ...overrides,
        });
    }, [query?.sort, searchValue, statusFilter, dateRange, encodedCampaignIds, encodedAdIds, metricFilters, requestedMetrics]);

    const dateRangeStr = useMemo(() => ({
        to: moment(dateRange?.to).format('YYYY-MM-DD'),
        from: moment(dateRange?.from).format('YYYY-MM-DD'),
    }), [dateRange]);

    // Debounce search
    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(
                `/workspaces/${workspace.slug}/ads-manager/ad-sets`,
                {
                    ...getNavParams(),
                    'filter[search]': searchValue || undefined,
                    page: 1,
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['adSets'],
                }
            );
        }, 500);

        return () => clearTimeout(timer);
    }, [searchValue]);

    // Sync date range with URL on mount
    useEffect(() => {
        if (!query?.filter?.start_date || !query?.filter?.end_date) {
            router.get(
                `/workspaces/${workspace.slug}/ads-manager/ad-sets`,
                getNavParams(),
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['adSets'],
                },
            );
        }
    }, []);

    const handleStatusFilterChange = (value: string) => {
        setStatusFilter(value);

        router.get(
            `/workspaces/${workspace.slug}/ads-manager/ad-sets`,
            getNavParams({ 'filter[status]': value || undefined }),
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['adSets'],
            },
        );
    };

    const handleMetricFilterChange = (filters: MetricFilter[]) => {
        setMetricFilters(filters);

        router.get(
            `/workspaces/${workspace.slug}/ads-manager/ad-sets`,
            getNavParams({ metric_filters: filters.length > 0 ? encodeURIComponent(JSON.stringify(filters)) : undefined }),
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['adSets'],
            },
        );
    };

    const handleDateRangeChange = (range: DateRange | undefined) => {
        setDateRange(range);

        router.get(
            `/workspaces/${workspace.slug}/ads-manager/ad-sets`,
            getNavParams({
                'filter[start_date]': range?.from ? moment(range.from).format('YYYY-MM-DD') : undefined,
                'filter[end_date]': range?.to ? moment(range.to).format('YYYY-MM-DD') : undefined,
            }),
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['adSets'],
            },
        );
    };

    const handleMetricsChange = (metrics: string[]) => {
        setSelectedMetrics(metrics);

        // Remove metric filters for disabled metrics
        const filteredMetricFilters = metricFilters.filter(filter => metrics.includes(filter.metric));
        setMetricFilters(filteredMetricFilters);

        router.get(
            `/workspaces/${workspace.slug}/ads-manager/ad-sets`,
            getNavParams({
                metrics: metrics.length > 0 ? metrics : undefined,
                metric_filters: filteredMetricFilters.length > 0 ? encodeURIComponent(JSON.stringify(filteredMetricFilters)) : undefined,
            }),
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['adSets'],
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
        router.get(`/workspaces/${workspace.slug}/ads-manager/ad-sets`, {
            sort: undefined,
            'filter[search]': undefined,
            'filter[status]': undefined,
            'filter[start_date]': undefined,
            'filter[end_date]': undefined,
            'filter[campaign_ids]': undefined,
            'filter[ad_ids]': undefined,
            metric_filters: undefined,
            metrics: undefined,
            page: 1,
        }, {
            preserveState: false,
            replace: true,
            preserveScroll: true,
        });
    };

    const columns: ColumnDef<AdSet>[] = [
        {
            id: 'select',
            header: ({ table }) => (
                <div className="flex justify-start">
                    <Checkbox
                        checked={
                            adSets?.data?.length > 0 && adSets.data.every(row => selectedRowIds[row.id])
                                ? true
                                : adSets?.data?.some(row => selectedRowIds[row.id])
                                    ? 'indeterminate'
                                    : false
                        }
                        onCheckedChange={(value) => {
                            if (value) {
                                setSelectedRows(
                                    TABLE_ID,
                                    (adSets?.data || []).reduce(
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
            header: ({ column }) => <SortableHeader column={column} title="Ad Set Name" />,
            cell: ({ row }) => <div className="font-medium">{row.original.name}</div>,
        },
        {
            accessorKey: 'campaign_id',
            header: ({ column }) => <SortableHeader column={column} title="Campaign" />,
            cell: ({ row }) => <div className="font-medium">{row.original.campaign?.name || 'N/A'}</div>,
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
        } as ColumnDef<AdSet>] : []),
        ...(selectedMetrics.includes('clicks') ? [{
            accessorKey: 'clicks',
            header: ({ column }: { column: any }) => <SortableHeader column={column} title="Clicks" />,
            cell: ({ row }: { row: any }) => Number(row.original.clicks || 0).toLocaleString(),
        } as ColumnDef<AdSet>] : []),
        ...(selectedMetrics.includes('spend') ? [{
            accessorKey: 'spend',
            header: ({ column }: { column: any }) => <SortableHeader column={column} title="Spend" />,
            cell: ({ row }: { row: any }) => `₱${Number(row.original.spend || 0).toFixed(2)}`,
        } as ColumnDef<AdSet>] : []),
        {
            accessorKey: 'daily_budget',
            header: ({ column }) => <SortableHeader column={column} title="Daily Budget" />,
            cell: ({ row }) =>
                row.original.daily_budget
                    ? `₱${Number(row.original.daily_budget).toFixed(2)}`
                    : 'N/A',
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Ad Sets`} />
            <AdsManagerLayout workspace={workspace} activeTab="adSets">
                <div className="space-y-5 sm:space-y-6">
                    <ComponentCard desc="Manage your ad sets">
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
                                searchPlaceholder="Search ad sets..."
                            />

                            <DataTable
                                columns={columns}
                                data={adSets?.data || []}
                                enableInternalPagination={false}
                                enableRowSelection={true}
                                initialSorting={initialSorting}
                                meta={{ ...omit(adSets, ['data']) }}
                                onFetch={(params) => {
                                    router.get(
                                        `/workspaces/${workspace.slug}/ads-manager/ad-sets`,
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

export default AdSetsPage;
