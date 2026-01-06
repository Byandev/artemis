import { useState, useEffect, useMemo } from 'react';
import { Head } from '@inertiajs/react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { ColumnDef } from '@tanstack/react-table';
import { Grid3x3, List, RefreshCw } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import ComponentCard from '@/components/common/ComponentCard';
import { Workspace } from '@/types/models/Workspace';
import axios from 'axios';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { addDays, format } from 'date-fns';
import clsx from 'clsx';

type TabType = 'campaigns' | 'adSets' | 'ads' | 'optimizationRules' | 'optimizationLogs';

interface Campaign {
  id: number;
  name: string;
  status: string;
  daily_budget: number | null;
  start_time: string;
  end_time: string | null;
  ad_account: {
    id: number;
    name: string;
  };
  created_at: string;
  updated_at: string;
}

interface AdSet {
  id: number;
  name: string;
  status: string;
  daily_budget: number | null;
  campaign: {
    id: number;
    name: string;
  };
  created_at: string;
  updated_at: string;
}

interface PaginationLinks {
  url: string | null;
  label: string;
  active: boolean;
}

interface PaginatedCampaigns {
  data: Campaign[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  links: PaginationLinks[];
  from: number;
  to: number;
}

interface PaginatedAdSets {
  data: AdSet[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  links: PaginationLinks[];
  from: number;
  to: number;
}

interface PageProps {
  workspace: Workspace;
}

const StatusBadge = ({ status }: { status: string }) => {
  return (
    <span
      className={clsx(
        "inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-bold tracking-wide ring-1 ring-inset",
        status === 'ACTIVE'
          ? "bg-emerald-50 text-emerald-700 ring-emerald-200"
          : status === 'PAUSED'
          ? "bg-yellow-50 text-yellow-700 ring-yellow-200"
          : "bg-slate-50 text-slate-700 ring-slate-200"
      )}
    >
      {status}
    </span>
  );
};

const AdsManager = ({ workspace }: PageProps) => {
  const [activeTab, setActiveTab] = useState<TabType>('campaigns');
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('');
  const [dateRange, setDateRange] = useState<{ from: Date; to: Date }>({
    from: addDays(new Date(), -30),
    to: new Date(),
  });
  const [campaigns, setCampaigns] = useState<Campaign[]>([]);
  const [adSets, setAdSets] = useState<AdSet[]>([]);
  const [loading, setLoading] = useState(false);
  const [pagination, setPagination] = useState<PaginatedCampaigns>({
    data: [],
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    links: [],
    from: 0,
    to: 0,
  });
  const [adSetsPagination, setAdSetsPagination] = useState<PaginatedAdSets>({
    data: [],
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    links: [],
    from: 0,
    to: 0,
  });

  useEffect(() => {
    const timeoutId = setTimeout(() => {
      if (activeTab === 'campaigns') {
        fetchCampaigns(1);
      } else if (activeTab === 'adSets') {
        fetchAdSets();
      }
    }, 300);
    
    return () => clearTimeout(timeoutId);
  }, [activeTab, searchQuery, statusFilter, dateRange]);

  const fetchCampaigns = async (page: number = 1) => {
    setLoading(true);
    try {
      const response = await axios.get(`/workspaces/${workspace.slug}/api/campaigns`, {
        params: { 
          search: searchQuery, 
          status: statusFilter || undefined,
          start_date: dateRange.from.toISOString().split('T')[0],
          end_date: dateRange.to.toISOString().split('T')[0],
          page 
        }
      });
      setCampaigns(response.data.data);
      setPagination(response.data);
    } catch (error) {
      console.error('Failed to fetch campaigns:', error);
    } finally {
      setLoading(false);
    }
  };

  const fetchAdSets = async () => {
    setLoading(true);
    try {
      const response = await axios.get(`/workspaces/${workspace.slug}/api/ad-sets`, {
        params: {
          search: searchQuery,
          status: statusFilter || undefined,
          start_date: dateRange.from.toISOString().split('T')[0],
          end_date: dateRange.to.toISOString().split('T')[0],
          page: adSetsPagination.current_page,
        }
      });
      setAdSets(response.data.data);
      setAdSetsPagination(response.data);
    } catch (error) {
      console.error('Failed to fetch ad sets:', error);
    } finally {
      setLoading(false);
    }
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

  const adSetsColumns: ColumnDef<AdSet>[] = [
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
      <Head title={`${workspace.name} - Ads Manager`} />
      <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
          <h2 className="text-xl font-semibold text-gray-800 dark:text-white/90">
            Ads Manager
          </h2>
        </div>

        {/* Tabs */}
        <div className="border-b border-gray-200 dark:border-white/[0.05]">
          <div className="flex gap-6">
            <button
              onClick={() => setActiveTab('campaigns')}
              className={`pb-3 px-1 text-sm font-medium border-b-2 transition-colors ${
                activeTab === 'campaigns'
                  ? 'border-brand-500 text-brand-500 dark:border-brand-400 dark:text-brand-400'
                  : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
              }`}
            >
              Campaigns
            </button>
            <button
              onClick={() => setActiveTab('adSets')}
              className={`pb-3 px-1 text-sm font-medium border-b-2 transition-colors ${
                activeTab === 'adSets'
                  ? 'border-brand-500 text-brand-500 dark:border-brand-400 dark:text-brand-400'
                  : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
              }`}
            >
              Ad Sets
            </button>
            <button
              onClick={() => setActiveTab('ads')}
              className={`pb-3 px-1 text-sm font-medium border-b-2 transition-colors ${
                activeTab === 'ads'
                  ? 'border-brand-500 text-brand-500 dark:border-brand-400 dark:text-brand-400'
                  : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
              }`}
            >
              Ads
            </button>
            <button
              onClick={() => setActiveTab('optimizationRules')}
              className={`pb-3 px-1 text-sm font-medium border-b-2 transition-colors ${
                activeTab === 'optimizationRules'
                  ? 'border-brand-500 text-brand-500 dark:border-brand-400 dark:text-brand-400'
                  : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
              }`}
            >
              Optimization Rules
            </button>
            <button
              onClick={() => setActiveTab('optimizationLogs')}
              className={`pb-3 px-1 text-sm font-medium border-b-2 transition-colors ${
                activeTab === 'optimizationLogs'
                  ? 'border-brand-500 text-brand-500 dark:border-brand-400 dark:text-brand-400'
                  : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
              }`}
            >
              Optimization Logs
            </button>
          </div>
        </div>

        <div className="space-y-5 sm:space-y-6">
          <ComponentCard desc="Manage your advertising campaigns and ad sets">
            <div>
              <div className="flex flex-col gap-2 rounded-t-xl border border-b-0 border-gray-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/[0.05]">
                <input
                  className="max-w-sm border w-full rounded-lg appearance-none px-4 py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:outline-hidden focus:ring-3 dark:bg-gray-900 dark:placeholder:text-white/30 bg-transparent text-gray-800 border-gray-300 focus:border-brand-300 focus:ring-brand-500/20 dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800"
                  placeholder={`Search ${activeTab === 'campaigns' ? 'campaigns' : 'ad sets'}...`}
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                />
                <div className="flex items-center gap-2 relative z-50">
                  <Button 
                    variant="outline" 
                    size="sm"
                    onClick={() => {
                      if (activeTab === 'campaigns') {
                        fetchCampaigns(pagination.current_page);
                      } else if (activeTab === 'adSets') {
                        fetchAdSets();
                      }
                    }}
                    disabled={loading}
                  >
                    <RefreshCw className={`h-4 w-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
                    Refresh
                  </Button>
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
                    initialDateFrom={dateRange.from}
                    initialDateTo={dateRange.to}
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
                  <Button variant="outline" size="icon">
                    <Grid3x3 className="h-4 w-4" />
                  </Button>
                  <Button variant="outline" size="icon">
                    <List className="h-4 w-4" />
                  </Button>
                </div>
              </div>

              {activeTab === 'campaigns' && (
                <DataTable
                  columns={columns}
                  data={campaigns}
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
                      fetchCampaigns(params.page);
                    }
                  }}
                />
              )}
              
              {activeTab === 'adSets' && (
                <DataTable
                  columns={adSetsColumns}
                  data={adSets}
                  enableInternalPagination={false}
                  meta={{
                    current_page: adSetsPagination.current_page,
                    last_page: adSetsPagination.last_page,
                    per_page: adSetsPagination.per_page,
                    total: adSetsPagination.total,
                    from: adSetsPagination.from,
                    to: adSetsPagination.to,
                    links: adSetsPagination.links,
                  }}
                  onFetch={(params) => {
                    if (params?.page) {
                      setAdSetsPagination(prev => ({ ...prev, current_page: params.page || 1 }));
                      fetchAdSets();
                    }
                  }}
                />
              )}
            </div>
          </ComponentCard>
        </div>
      </div>
    </AppLayout>
  );
};

export default AdsManager;
