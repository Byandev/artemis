import ComponentCard from '@/components/common/ComponentCard';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { StatusBadge } from '@/components/ui/status-badge';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { Campaign, PaginatedCampaigns, AVAILABLE_AD_METRICS } from '@/types/models/AdManager';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import moment from 'moment';
import { useEffect, useMemo, useState } from 'react';
import { type DateRange } from "react-day-picker";
import { FiltersBar } from './partials/FiltersBar';
import AdsManagerLayout from './partials/Layout';

const CampaignsPage = ({ workspace, campaigns, query }: { workspace: Workspace; campaigns: PaginatedCampaigns; query?: { sort?: string; perPage?: number; page?: number; filter?: { search?: string; status?: string; start_date?: string; end_date?: string; impressions_greater_than?: string; clicks_greater_than?: string; spend_greater_than?: string; daily_budget_greater_than?: string }; metrics?: string[] } }) => {
    const [selectedMetrics, setSelectedMetrics] = useState<string[]>(query?.metrics ?? []);

    const requestedMetrics = selectedMetrics;

    const getNavParams = (overrides: Record<string, any> = {}) => ({
        sort: query?.sort,
        'filter[search]': searchValue || undefined,
        'filter[status]': statusFilter || undefined,
        'filter[impressions_greater_than]': impressionsGreaterThan || undefined,
        'filter[clicks_greater_than]': clicksGreaterThan || undefined,
        'filter[spend_greater_than]': spendGreaterThan || undefined,
        'filter[daily_budget_greater_than]': dailyBudgetGreaterThan || undefined,
        start_date: dateRange?.from ? moment(dateRange.from).format('YYYY-MM-DD') : undefined,
        end_date: dateRange?.to ? moment(dateRange.to).format('YYYY-MM-DD') : undefined,
        metrics: requestedMetrics,
        page: 1,
        ...overrides,
    });

    const initialSorting = useMemo(() => {
        return toFrontendSort(query?.sort ?? null);
    }, [query?.sort]);

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [statusFilter, setStatusFilter] = useState(query?.filter?.status ?? '');
    const [impressionsGreaterThan, setImpressionsGreaterThan] = useState(query?.filter?.impressions_greater_than ?? '');
    const [clicksGreaterThan, setClicksGreaterThan] = useState(query?.filter?.clicks_greater_than ?? '');
    const [spendGreaterThan, setSpendGreaterThan] = useState(query?.filter?.spend_greater_than ?? '');
    const [dailyBudgetGreaterThan, setDailyBudgetGreaterThan] = useState(query?.filter?.daily_budget_greater_than ?? '');
    const [dateRange, setDateRange] = useState<DateRange | undefined>({
        from: query?.filter?.start_date ? moment(query.filter.start_date).toDate() : moment().startOf('month').toDate(),
        to: query?.filter?.end_date ? moment(query.filter.end_date).toDate() : moment().toDate()
    });

    const dateRangeStr = useMemo(() => ({
        to: moment(dateRange?.to).format('YYYY-MM-DD'),
        from: moment(dateRange?.from).format('YYYY-MM-DD'),
    }), [dateRange]);

    // Debounce search
    useEffect(() => {
        const currentSearchParam = query?.filter?.search ?? '';

        if (searchValue === currentSearchParam) {
            return;
        }

        const timer = setTimeout(() => {
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
        }, 500);

        return () => clearTimeout(timer);
    }, [searchValue, query?.filter?.search]);

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

    const handleNumericFilterChange = () => {
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
    };

    const handleDateRangeChange = (range: DateRange | undefined) => {
        setDateRange(range);

        router.get(
            `/workspaces/${workspace.slug}/ads-manager/campaigns`,
            getNavParams({
                start_date: range?.from ? moment(range.from).format('YYYY-MM-DD') : undefined,
                end_date: range?.to ? moment(range.to).format('YYYY-MM-DD') : undefined,
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

        router.get(
            `/workspaces/${workspace.slug}/ads-manager/campaigns`,
            getNavParams({ metrics: metrics.length > 0 ? metrics : AVAILABLE_AD_METRICS }),
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
        setImpressionsGreaterThan('');
        setClicksGreaterThan('');
        setSpendGreaterThan('');
        setDailyBudgetGreaterThan('');
        setDateRange({
            from: moment().startOf('month').toDate(),
            to: moment().toDate()
        });
        router.get(`/workspaces/${workspace.slug}/ads-manager/campaigns`, getNavParams(), {
            preserveState: false,
            replace: true,
            preserveScroll: true,
        });
    };

    const columns: ColumnDef<Campaign>[] = [
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
                            <FiltersBar
                                searchValue={searchValue}
                                statusFilter={statusFilter}
                                impressionsGreaterThan={impressionsGreaterThan}
                                clicksGreaterThan={clicksGreaterThan}
                                spendGreaterThan={spendGreaterThan}
                                dailyBudgetGreaterThan={dailyBudgetGreaterThan}
                                dateRange={dateRange}
                                dateRangeStr={dateRangeStr}
                                selectedMetrics={selectedMetrics}
                                availableMetrics={AVAILABLE_AD_METRICS}
                                onSearchChange={setSearchValue}
                                onStatusChange={handleStatusFilterChange}
                                onImpressionsChange={setImpressionsGreaterThan}
                                onClicksChange={setClicksGreaterThan}
                                onSpendChange={setSpendGreaterThan}
                                onDailyBudgetChange={setDailyBudgetGreaterThan}
                                onDateRangeChange={handleDateRangeChange}
                                onMetricsChange={handleMetricsChange}
                                onNumericFilterChange={handleNumericFilterChange}
                                onClearFilters={clearFilters}
                                searchPlaceholder="Search campaigns..."
                            />

                            <DataTable
                                columns={columns}
                                data={campaigns?.data || []}
                                enableInternalPagination={false}
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
