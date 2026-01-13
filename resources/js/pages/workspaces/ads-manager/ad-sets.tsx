import ComponentCard from '@/components/common/ComponentCard';
import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { SimpleDateRangePicker } from '@/components/ui/simple-date-range-picker';
import { StatusBadge } from '@/components/ui/status-badge';
import AppLayout from '@/layouts/app-layout';
import { AdSet, PaginatedAdSets } from '@/types/models/AdManager';
import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import axios from 'axios';
import { Grid3x3, List } from 'lucide-react';
import moment from 'moment';
import { useEffect, useMemo, useState } from 'react';
import { type DateRange } from "react-day-picker";
import AdsManagerLayout from './partials/Layout';

const AdSetsPage = ({ workspace, filters }: { workspace: Workspace; filters?: { start_date?: string; end_date?: string; status?: string; search?: string; } }) => {
    const [searchQuery, setSearchQuery] = useState(filters?.search || '');
    const [statusFilter, setStatusFilter] = useState<string>(filters?.status || '');
    const [dateRange, setDateRange] = useState<DateRange | undefined>({
        from: filters?.start_date ? new Date(filters.start_date) : moment().startOf('month').toDate(),
        to: filters?.end_date ? new Date(filters.end_date) : moment().toDate()
    });
    const [adSets, setAdSets] = useState<AdSet[]>([]);
    const [pagination, setPagination] = useState<PaginatedAdSets>({
        data: [],
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 0,
        links: [],
        from: 0,
        to: 0,
    });

    const dateRangeStr = useMemo(() => ({
        to: moment(dateRange?.to).format('YYYY-MM-DD'),
        from: moment(dateRange?.from).format('YYYY-MM-DD'),
    }), [dateRange]);

    const fetchAdSets = async (page: number = 1) => {
        try {
            const params: any = {
                search: searchQuery,
                status: statusFilter || undefined,
                start_date: dateRange?.from?.toISOString().split('T')[0],
                end_date: dateRange?.to?.toISOString().split('T')[0],
                page,
            };

            const response = await axios.get(`/workspaces/${workspace.slug}/api/ad-sets`, { params });
            setAdSets(response.data.data);
            setPagination(response.data);
        } catch (error) {
            console.error('Failed to fetch ad sets:', error);
        }
    };

    // Fetch ad sets when filters change
    useEffect(() => {
        fetchAdSets(1);
    }, [searchQuery, statusFilter, dateRange]);

    const clearFilters = () => {
        setSearchQuery('');
        setStatusFilter('');
        setDateRange({
            from: moment().startOf('month').toDate(),
            to: moment().toDate()
        });
    };

    const columns: ColumnDef<AdSet>[] = [
        {
            accessorKey: 'name',
            header: ({ column }) => <SortableHeader column={column} title="Ad Set Name" />,
            cell: ({ row }) => <div className="font-medium">{row.original.name}</div>,
        },
        {
            accessorKey: 'campaign',
            header: ({ column }) => <SortableHeader column={column} title="Campaign" />,
            cell: ({ row }) => <div className="font-medium">{row.original.campaign?.name || 'N/A'}</div>,
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
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Ad Sets`} />
            <AdsManagerLayout workspace={workspace} activeTab="adSets">
                <div className="space-y-5 sm:space-y-6">
                    <ComponentCard desc="Manage your ad sets">
                        <div>
                            <div className="flex flex-col gap-3 rounded-t-xl border border-b-0 border-gray-100 px-3 py-3 sm:px-4 sm:py-4 lg:flex-row lg:items-center lg:justify-between dark:border-white/5">
                                <input
                                    className="w-full lg:max-w-sm border rounded-lg appearance-none px-3 py-2 sm:px-4 sm:py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:outline-hidden focus:ring-3 dark:bg-gray-900 dark:placeholder:text-white/30 bg-transparent text-gray-800 border-gray-300 focus:border-brand-300 focus:ring-brand-500/20 dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800"
                                    placeholder="Search ad sets..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                />
                                <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 relative z-50">
                                    <select
                                        value={statusFilter}
                                        onChange={(e) => setStatusFilter(e.target.value)}
                                        className="h-9 rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm text-gray-800 dark:text-white/90 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:border-brand-300 dark:focus:border-brand-800"
                                    >
                                        <option value="">All Status</option>
                                        <option value="ACTIVE">Active</option>
                                        <option value="PAUSED">Paused</option>
                                        <option value="ARCHIVED">Archived</option>
                                    </select>
                                    <SimpleDateRangePicker
                                        value={dateRange}
                                        onChange={setDateRange}
                                    />
                                    {(searchQuery || statusFilter || dateRangeStr.from !== moment().startOf('month').format('YYYY-MM-DD') || dateRangeStr.to !== moment().format('YYYY-MM-DD')) && (
                                        <Button
                                            variant="outline"
                                            onClick={clearFilters}
                                        >
                                            Clear Filters
                                        </Button>
                                    )}
                                    <div className="hidden sm:flex items-center gap-2">
                                        <Button variant="outline" size="icon">
                                            <Grid3x3 className="h-4 w-4" />
                                        </Button>
                                        <Button variant="outline" size="icon">
                                            <List className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <DataTable
                                columns={columns}
                                data={adSets}
                                enableInternalPagination={false}
                                meta={{
                                    current_page: pagination.current_page,
                                    last_page: pagination.last_page,
                                    per_page: pagination.per_page,
                                    total: pagination.total,
                                    from: pagination.from,
                                    to: pagination.to,
                                    links: pagination.links,
                                }}
                                onFetch={(params) => {
                                    if (params?.page) {
                                        fetchAdSets(params.page);
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

export default AdSetsPage;
