import { useState, useEffect, forwardRef, useImperativeHandle } from 'react';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { ColumnDef } from '@tanstack/react-table';
import { Workspace } from '@/types/models/Workspace';
import axios from 'axios';

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

interface AdSetsTabProps {
  workspace: Workspace;
  searchQuery: string;
  statusFilter: string;
  dateRange: { from: Date; to: Date };
  loading: boolean;
  setLoading: (loading: boolean) => void;
}

const AdSetsTab = forwardRef(({
  workspace,
  searchQuery,
  statusFilter,
  dateRange,
  loading,
  setLoading,
}: AdSetsTabProps, ref) => {
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

  const fetchAdSets = async () => {
    setLoading(true);
    try {
      const params: any = {
        search: searchQuery,
        status: statusFilter || undefined,
        page: pagination.current_page,
      };

      // Only add date filters if they're explicitly set (not the default 30-day range)
      // For now, we'll skip date filtering for ad sets since they don't have start_time/end_time
      // and created_at would filter based on when we synced them, not the actual ad set dates
      
      const response = await axios.get(`/workspaces/${workspace.slug}/api/ad-sets`, { params });
      setAdSets(response.data.data);
      setPagination(response.data);
    } catch (error) {
      console.error('Failed to fetch ad sets:', error);
    } finally {
      setLoading(false);
    }
  };

  // Expose fetchAdSets to parent component
  useImperativeHandle(ref, () => ({
    fetchAdSets
  }));

  // Fetch ad sets on mount
  useEffect(() => {
    fetchAdSets();
  }, []);

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
          setPagination(prev => ({ ...prev, current_page: params.page || 1 }));
          fetchAdSets();
        }
      }}
    />
  );
});

AdSetsTab.displayName = 'AdSetsTab';

export default AdSetsTab;

// Export types for parent component
export { type AdSet, type PaginatedAdSets };
