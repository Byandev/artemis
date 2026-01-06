import { useState, useEffect, forwardRef, useImperativeHandle } from 'react';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { ColumnDef } from '@tanstack/react-table';
import { Workspace } from '@/types/models/Workspace';
import axios from 'axios';

interface Ad {
  id: number;
  name: string;
  status: string;
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

interface PaginationLinks {
  url: string | null;
  label: string;
  active: boolean;
}

interface PaginatedAds {
  data: Ad[];
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

interface AdsTabProps {
  workspace: Workspace;
  searchQuery: string;
  statusFilter: string;
  dateRange: { from: Date; to: Date };
  loading: boolean;
  setLoading: (loading: boolean) => void;
}

const AdsTab = forwardRef(({
  workspace,
  searchQuery,
  statusFilter,
  dateRange,
  loading,
  setLoading,
}: AdsTabProps, ref) => {
  const [ads, setAds] = useState<Ad[]>([]);
  const [pagination, setPagination] = useState<PaginatedAds>({
    data: [],
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    links: [],
    from: 0,
    to: 0,
  });

  const fetchAds = async () => {
    setLoading(true);
    try {
      const params: any = {
        search: searchQuery,
        status: statusFilter || undefined,
        page: pagination.current_page,
      };
      
      const response = await axios.get(`/workspaces/${workspace.slug}/api/ads`, { params });
      setAds(response.data.data);
      setPagination(response.data);
    } catch (error) {
      console.error('Failed to fetch ads:', error);
    } finally {
      setLoading(false);
    }
  };

  // Expose fetchAds to parent component
  useImperativeHandle(ref, () => ({
    fetchAds
  }));

  // Fetch ads on mount
  useEffect(() => {
    fetchAds();
  }, []);

  const columns: ColumnDef<Ad>[] = [
    {
      accessorKey: 'name',
      header: ({ column }) => <SortableHeader column={column} title="Ad Name" />,
      cell: ({ row }) => <div className="font-medium">{row.original.name}</div>,
    },
    {
      accessorKey: 'campaign',
      header: ({ column }) => <SortableHeader column={column} title="Campaign" />,
      cell: ({ row }) => <div>{row.original.campaign?.name || 'N/A'}</div>,
    },
    {
      accessorKey: 'ad_set',
      header: ({ column }) => <SortableHeader column={column} title="Ad Set" />,
      cell: ({ row }) => <div>{row.original.ad_set?.name || 'N/A'}</div>,
    },
    {
      accessorKey: 'status',
      header: ({ column }) => <SortableHeader column={column} title="Status" />,
      cell: ({ row }) => <StatusBadge status={row.original.status} />,
    },
  ];

  return (
    <DataTable
      columns={columns}
      data={ads}
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
          fetchAds();
        }
      }}
    />
  );
});

AdsTab.displayName = 'AdsTab';

export default AdsTab;

// Export types for parent component
export { type Ad, type PaginatedAds };
