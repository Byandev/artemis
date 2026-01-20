import ComponentCard from '@/components/common/ComponentCard';
import { Checkbox } from '@/components/ui/checkbox';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { StatusBadge } from '@/components/ui/status-badge';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { AVAILABLE_AD_METRICS, Campaign, PaginatedCampaigns } from '@/types/models/AdManager';
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

const CampaignsPage = ({ workspace, campaigns, query }: { workspace: Workspace; campaigns: PaginatedCampaigns; query?: { sort?: string; perPage?: number; page?: number; filter?: { search?: string; status?: string; start_date?: string; end_date?: string }; metric_filters?: string; metrics?: string[] } }) => {
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

    const getNavParams = (overrides: Record<string, any> = {}) => ({
        sort: query?.sort,
        'filter[search]': searchValue || undefined,
        'filter[status]': statusFilter || undefined,
        'filter[start_date]': dateRange?.from ? moment(dateRange.from).format('YYYY-MM-DD') : undefined,
        'filter[end_date]': dateRange?.to ? moment(dateRange.to).format('YYYY-MM-DD') : undefined,
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
    const [dateRange, setDateRange] = useState<DateRange | undefined>({
        from: query?.filter?.start_date ? moment(query.filter.start_date).toDate() : moment().startOf('month').toDate(),
        to: query?.filter?.end_date ? moment(query.filter.end_date).toDate() : moment().toDate()
    });

    const dateRangeStr = useMemo(() => ({
        to: moment(dateRange?.to).format('YYYY-MM-DD'),
        from: moment(dateRange?.from).format('YYYY-MM-DD'),
    }), [dateRange]);

    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(
                `/workspaces/${workspace.slug}/ads-manager/campaigns`,
                {
                    ...getNavParams(),
                    'filter[search]': searchValue || undefined, // 👈 force override
                    page: 1,
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['campaigns'],
                }
            );
        }, 500);

        return () => clearTimeout(timer);
    }, [searchValue]);


    // Sync date range with URL on mount
    useEffect(() => {
        if (!query?.filter?.start_date || !query?.filter?.end_date) {
            router.get(
                `/workspaces/${workspace.slug}/ads-manager/campaigns`,
                getNavParams(),
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['campaigns'],
                },
            );
        }
    }, []);

    const handleStatusFilterChange = (value: string) => {
        setStatusFilter(value);

        router.get(
            `/workspaces/${workspace.slug}/ads-manager/campaigns`,
            getNavParams({ 'filter[status]': value || undefined }),
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['campaigns'],
            },
        );
    };

    const handleMetricFilterChange = (filters: MetricFilter[]) => {
        setMetricFilters(filters);

        router.get(
            `/workspaces/${workspace.slug}/ads-manager/campaigns`,
            getNavParams({ metric_filters: filters.length > 0 ? encodeURIComponent(JSON.stringify(filters)) : undefined }),
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['campaigns'],
            },
        );
    };

    const handleDateRangeChange = (range: DateRange | undefined) => {
        setDateRange(range);

        router.get(
            `/workspaces/${workspace.slug}/ads-manager/campaigns`,
            getNavParams({
                'filter[start_date]': range?.from ? moment(range.from).format('YYYY-MM-DD') : undefined,
                'filter[end_date]': range?.to ? moment(range.to).format('YYYY-MM-DD') : undefined,
            }),
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['campaigns'],
            },
        );
    };

    const handleMetricsChange = (metrics: string[]) => {
        setSelectedMetrics(metrics);

        // Remove metric filters for disabled metrics
        const filteredMetricFilters = metricFilters.filter(filter => metrics.includes(filter.metric));
        setMetricFilters(filteredMetricFilters);

        router.get(
            `/workspaces/${workspace.slug}/ads-manager/campaigns`,
            getNavParams({
                metrics: metrics.length > 0 ? metrics : undefined,
                metric_filters: filteredMetricFilters.length > 0 ? encodeURIComponent(JSON.stringify(filteredMetricFilters)) : undefined,
            }),
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['campaigns'],
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
        router.get(`/workspaces/${workspace.slug}/ads-manager/campaigns`, {
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

    const columns: ColumnDef<Campaign>[] = [
        {
            id: 'select',
            header: ({ table }) => (
                <div className="flex justify-start">
                    <Checkbox
                        checked={
                            table.getIsAllPageRowsSelected() ||
                            (table.getIsSomePageRowsSelected() && 'indeterminate')
                        }
                        onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
                        aria-label="Select all"
                    />
                </div>
            ),
            cell: ({ row }) => (
                <Checkbox
                    checked={row.getIsSelected()}
                    onCheckedChange={(value) => row.toggleSelected(!!value)}
                    aria-label="Select row"
                />
            ),
            enableSorting: false,
            enableHiding: false,
        },
        {
            accessorKey: 'name',
            header: ({ column }) => <SortableHeader column={column} title="Campaign Name" />,
            cell: ({ row }) => <div className="font-medium">{row.original.name}</div>,
        },
        {
            accessorKey: 'ad_account_id',
            header: ({ column }) => <SortableHeader column={column} title="Ad Account" />,
            cell: ({ row }) => <div className="font-medium">{row.original.ad_account?.name || 'N/A'}</div>,
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
        } as ColumnDef<Campaign>] : []),
        ...(selectedMetrics.includes('clicks') ? [{
            accessorKey: 'clicks',
            header: ({ column }: { column: any }) => <SortableHeader column={column} title="Clicks" />,
            cell: ({ row }: { row: any }) => Number(row.original.clicks || 0).toLocaleString(),
        } as ColumnDef<Campaign>] : []),
        ...(selectedMetrics.includes('spend') ? [{
            accessorKey: 'spend',
            header: ({ column }: { column: any }) => <SortableHeader column={column} title="Spend" />,
            cell: ({ row }: { row: any }) => `₱${Number(row.original.spend || 0).toFixed(2)}`,
        } as ColumnDef<Campaign>] : []),
        {
            accessorKey: 'daily_budget',
            header: ({ column }) => <SortableHeader column={column} title="Daily Budget" />,
            cell: ({ row }) =>
                row.original.daily_budget
                    ? `₱${Number(row.original.daily_budget).toFixed(2)}`
                    : 'N/A',
        },
        {
            accessorKey: 'start_time',
            header: ({ column }) => <SortableHeader column={column} title="Start Time" />,
            cell: ({ row }) => new Date(row.original.start_time).toLocaleDateString(),
        },
        {
            accessorKey: 'end_time',
            header: ({ column }) => <SortableHeader column={column} title="End Time" />,
            cell: ({ row }) =>
                row.original.end_time
                    ? new Date(row.original.end_time).toLocaleDateString()
                    : 'Ongoing',
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Campaigns`} />
            <AdsManagerLayout workspace={workspace} activeTab="campaigns">
                <div className="space-y-5 sm:space-y-6">
                    <ComponentCard desc="Manage your advertising campaigns">
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
                                searchPlaceholder="Search campaigns..."
                            />

                            <DataTable
                                columns={columns}
                                data={campaigns?.data || []}
                                enableInternalPagination={false}
                                enableRowSelection={true}
                                initialSorting={initialSorting}
                                meta={{ ...omit(campaigns, ['data']) }}
                                onFetch={(params) => {
                                    router.get(
                                        `/workspaces/${workspace.slug}/ads-manager/campaigns`,
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

export default CampaignsPage;
