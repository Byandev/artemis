import ComponentCard from '@/components/common/ComponentCard';
import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { SimpleDateRangePicker } from '@/components/ui/simple-date-range-picker';
import { StatusBadge } from '@/components/ui/status-badge';
import AppLayout from '@/layouts/app-layout';
import { Campaign, PaginatedCampaigns } from '@/types/models/AdManager';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import moment from 'moment';
import { useEffect, useMemo, useState } from 'react';
import { type DateRange } from "react-day-picker";
import AdsManagerLayout from './partials/Layout';

const CampaignsPage = ({ workspace, campaigns: initialCampaigns, query }: { workspace: Workspace; campaigns: PaginatedCampaigns; query?: { search?: string; status?: string; start_date?: string; end_date?: string; page?: number | string } }) => {
    const [searchValue, setSearchValue] = useState(query?.search ?? '');
    const [statusFilter, setStatusFilter] = useState(query?.status ?? '');
    const [dateRange, setDateRange] = useState<DateRange | undefined>({
        from: query?.start_date ? new Date(query.start_date) : moment().startOf('month').toDate(),
        to: query?.end_date ? new Date(query.end_date) : moment().toDate()
    });

    const dateRangeStr = useMemo(() => ({
        to: moment(dateRange?.to).format('YYYY-MM-DD'),
        from: moment(dateRange?.from).format('YYYY-MM-DD'),
    }), [dateRange]);

    // Debounce search
    useEffect(() => {
        const currentSearchParam = query?.search ?? '';

        if (searchValue === currentSearchParam) {
            return;
        }

        const timer = setTimeout(() => {
            router.get(
                `/workspaces/${workspace.slug}/ads-manager/campaigns`,
                {
                    search: searchValue || undefined,
                    status: statusFilter || undefined,
                    start_date: dateRange?.from?.toISOString().split('T')[0],
                    end_date: dateRange?.to?.toISOString().split('T')[0],
                    page: 1,
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['campaigns'],
                },
            );
        }, 500);

        return () => clearTimeout(timer);
    }, [searchValue, query?.search]);

    // Sync date range with URL on mount
    useEffect(() => {
        if (!query?.start_date || !query?.end_date) {
            router.get(
                `/workspaces/${workspace.slug}/ads-manager/campaigns`,
                {
                    search: query?.search || undefined,
                    status: query?.status || undefined,
                    start_date: dateRange?.from?.toISOString().split('T')[0],
                    end_date: dateRange?.to?.toISOString().split('T')[0],
                    page: 1,
                },
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
            {
                search: searchValue || undefined,
                status: value || undefined,
                start_date: dateRange?.from?.toISOString().split('T')[0],
                end_date: dateRange?.to?.toISOString().split('T')[0],
                page: 1,
            },
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
            {
                search: searchValue || undefined,
                status: statusFilter || undefined,
                start_date: range?.from?.toISOString().split('T')[0],
                end_date: range?.to?.toISOString().split('T')[0],
                page: 1,
            },
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
        setDateRange({
            from: moment().startOf('month').toDate(),
            to: moment().toDate()
        });
        router.get(`/workspaces/${workspace.slug}/ads-manager/campaigns`, {}, {
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
            accessorKey: 'ad_account',
            header: ({ column }) => <SortableHeader column={column} title="Ad Account" />,
            cell: ({ row }) => <div className="font-medium">{row.original.ad_account?.name || 'N/A'}</div>,
        },
        {
            accessorKey: 'status',
            header: ({ column }) => <SortableHeader column={column} title="Status" />,
            cell: ({ row }) => <StatusBadge status={row.original.status} />,
        },
        {
            accessorKey: 'impressions',
            header: ({ column }) => <SortableHeader column={column} title="Impressions" />,
            cell: ({ row }) => Number(row.original.impressions || 0).toLocaleString(),
        },
        {
            accessorKey: 'clicks',
            header: ({ column }) => <SortableHeader column={column} title="Clicks" />,
            cell: ({ row }) => Number(row.original.clicks || 0).toLocaleString(),
        },
        {
            accessorKey: 'spend',
            header: ({ column }) => <SortableHeader column={column} title="Spend" />,
            cell: ({ row }) => `₱${Number(row.original.spend || 0).toFixed(2)}`,
        },
        {
            accessorKey: 'conversions',
            header: ({ column }) => <SortableHeader column={column} title="Conversions" />,
            cell: ({ row }) => Number(row.original.conversions || 0).toLocaleString(),
        },
        {
            accessorKey: 'ctr',
            header: ({ column }) => <SortableHeader column={column} title="CTR" />,
            cell: ({ row }) => `${Number(row.original.ctr || 0).toFixed(2)}%`,
        },
        {
            accessorKey: 'daily_budget',
            header: ({ column }) => <SortableHeader column={column} title="Daily Budget" />,
            cell: ({ row }) =>
                row.original.daily_budget
                    ? `₱${(row.original.daily_budget / 100).toFixed(2)}`
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
                            <div className="flex flex-col gap-3 rounded-t-xl border border-b-0 border-gray-100 px-3 py-3 sm:px-4 sm:py-4 lg:flex-row lg:items-center lg:justify-between dark:border-white/5">
                                <input
                                    className="w-full lg:max-w-sm border rounded-lg appearance-none px-3 py-2 sm:px-4 sm:py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:outline-hidden focus:ring-3 dark:bg-gray-900 dark:placeholder:text-white/30 bg-transparent text-gray-800 border-gray-300 focus:border-brand-300 focus:ring-brand-500/20 dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800"
                                    placeholder="Search campaigns..."
                                    value={searchValue}
                                    onChange={(e) => setSearchValue(e.target.value)}
                                />
                                <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 relative z-50">
                                    <select
                                        value={statusFilter}
                                        onChange={(e) => handleStatusFilterChange(e.target.value)}
                                        className="h-9 rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm text-gray-800 dark:text-white/90 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:border-brand-300 dark:focus:border-brand-800"
                                    >
                                        <option value="">All Status</option>
                                        <option value="ACTIVE">Active</option>
                                        <option value="PAUSED">Paused</option>
                                        <option value="ARCHIVED">Archived</option>
                                    </select>
                                    <SimpleDateRangePicker
                                        value={dateRange}
                                        onChange={handleDateRangeChange}
                                    />
                                    {(searchValue || statusFilter || dateRangeStr.from !== moment().startOf('month').format('YYYY-MM-DD') || dateRangeStr.to !== moment().format('YYYY-MM-DD')) && (
                                        <Button
                                            variant="outline"
                                            onClick={clearFilters}
                                        >
                                            Clear Filters
                                        </Button>
                                    )}
                                </div>
                            </div>

                            <DataTable
                                columns={columns}
                                data={initialCampaigns?.data || []}
                                enableInternalPagination={false}
                                meta={{
                                    current_page: initialCampaigns?.current_page,
                                    last_page: initialCampaigns?.last_page,
                                    per_page: initialCampaigns?.per_page,
                                    total: initialCampaigns?.total,
                                    from: initialCampaigns?.from,
                                    to: initialCampaigns?.to,
                                    links: initialCampaigns?.links,
                                }}
                                onFetch={(params) => {
                                    if (params?.page) {
                                        router.get(
                                            `/workspaces/${workspace.slug}/ads-manager/campaigns`,
                                            {
                                                search: searchValue || undefined,
                                                status: statusFilter || undefined,
                                                start_date: dateRange?.from
                                                    ? dateRange.from.toISOString().split('T')[0]
                                                    : undefined,
                                                end_date: dateRange?.to
                                                    ? dateRange.to.toISOString().split('T')[0]
                                                    : undefined,
                                                page: params.page,
                                            },
                                            {
                                                preserveState: true,
                                                replace: true,
                                                preserveScroll: true,
                                                only: ['campaigns'],
                                            },
                                        );
                                    }
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
