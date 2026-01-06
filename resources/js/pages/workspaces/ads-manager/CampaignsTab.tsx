import { useState, useEffect, forwardRef, useImperativeHandle } from 'react';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { ColumnDef } from '@tanstack/react-table';
import { Workspace } from '@/types/models/Workspace';
import axios from 'axios';

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

interface StatusBadgeProps {
  status: string;
}

const StatusBadge = ({ status }: StatusBadgeProps) => {
  const getStatusColor = (status: string) => {
    switch (status.toUpperCase()) {
      case 'ACTIVE':
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
      case 'PAUSED':
        return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400';
      case 'ARCHIVED':
        return 'bg-slate-100 text-slate-700 dark:bg-slate-900/30 dark:text-slate-400';
      default:
        return 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400';
    }
  };

  return (
    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${getStatusColor(status)}`}>
      {status}
    </span>
  );
};

interface CampaignsTabProps {
  workspace: Workspace;
  searchQuery: string;
  statusFilter: string;
  dateRange: { from: Date; to: Date };
  loading: boolean;
  setLoading: (loading: boolean) => void;
}

const CampaignsTab = forwardRef(({
  workspace,
  searchQuery,
  statusFilter,
  dateRange,
  loading,
  setLoading,
}: CampaignsTabProps, ref) => {
  const [campaigns, setCampaigns] = useState<Campaign[]>([]);
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

  const fetchCampaigns = async (page: number = 1) => {
    setLoading(true);
    try {
      const response = await axios.get(`/workspaces/${workspace.slug}/api/campaigns`, {
        params: { 
          search: searchQuery, 
          status: statusFilter || undefined,
          start_date: dateRange.from.toISOString().split('T')[0],
          end_date: dateRange.to.toISOString().split('T')[0],
          page,
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

  // Expose fetchCampaigns to parent component
  useImperativeHandle(ref, () => ({
    fetchCampaigns
  }));

  // Fetch campaigns on mount
  useEffect(() => {
    fetchCampaigns(1);
  }, []);

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

  return (
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
  );
});

CampaignsTab.displayName = 'CampaignsTab';

export default CampaignsTab;

// Export types for parent component
export { type Campaign, type PaginatedCampaigns };
