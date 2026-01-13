import ComponentCard from '@/components/common/ComponentCard';
import { Button } from '@/components/ui/button';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import AppLayout from '@/layouts/app-layout';
import { campaigns as campaignsRoute } from '@/routes/workspaces/ads-manager';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { Grid3x3, List } from 'lucide-react';
import moment from 'moment';
import { useEffect, useMemo, useState } from 'react';
import CampaignsTab from './CampaignsTab';
import AdsManagerLayout from './partials/Layout';

interface PageProps {
    workspace: Workspace;
    filters?: {
        start_date?: string;
        end_date?: string;
        status?: string;
        search?: string;
    }
}

const CampaignsPage = ({ workspace, filters }: PageProps) => {
    const [searchQuery, setSearchQuery] = useState(filters?.search || '');
    const [statusFilter, setStatusFilter] = useState<string>(filters?.status || '');
    const [dateRange, setDateRange] = useState<{ from: Date; to: Date }>({
        from: filters?.start_date ? new Date(filters.start_date) : moment().startOf('month').toDate(),
        to: filters?.end_date ? new Date(filters.end_date) : moment().toDate()
    });
    const [loading, setLoading] = useState(false);

    const dateRangeStr = useMemo(() => ({
        to: moment(dateRange.to).format('YYYY-MM-DD'),
        from: moment(dateRange.from).format('YYYY-MM-DD'),
    }), [dateRange]);

    // Update URL and refetch data when filters change
    useEffect(() => {
        router.get(
            campaignsRoute(workspace.slug),
            {
                search: searchQuery || undefined,
                status: statusFilter || undefined,
                start_date: dateRange?.from ? dateRangeStr.from : undefined,
                end_date: dateRange?.to ? dateRangeStr.to : undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    }, [workspace.slug, searchQuery, statusFilter, dateRange, dateRangeStr]);

    const clearFilters = () => {
        setSearchQuery('');
        setStatusFilter('');
        setDateRange({
            from: moment().startOf('month').toDate(),
            to: moment().toDate()
        });
        router.get(
            campaignsRoute(workspace.slug),
            {},
            { preserveState: true, preserveScroll: true }
        );
    };

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
                                    <DateRangePicker
                                        initialDateFrom={dateRange?.from}
                                        initialDateTo={dateRange?.to}
                                        onUpdate={(values) => {
                                            if (values.range.from && values.range.to) {
                                                setDateRange({
                                                    from: values.range.from,
                                                    to: values.range.to,
                                                });
                                            }
                                        }}
                                        align="end"
                                        showCompare={false}
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

                            <CampaignsTab
                                workspace={workspace}
                                searchQuery={searchQuery}
                                statusFilter={statusFilter}
                                dateRange={dateRange}
                                loading={loading}
                                setLoading={setLoading}
                            />
                        </div>
                    </ComponentCard>
                </div>
            </AdsManagerLayout>
        </AppLayout>
    );
};

export default CampaignsPage;
