import { useState, useEffect } from 'react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Filter, Grid3x3, List, RefreshCw } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { router } from '@inertiajs/react';
import axios from 'axios';

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

interface PageProps {
  workspace: Workspace;
}

const AdsManager = ({ workspace }: PageProps) => {
  const [activeTab, setActiveTab] = useState<TabType>('campaigns');
  const [searchQuery, setSearchQuery] = useState('');
  const [campaigns, setCampaigns] = useState<Campaign[]>([]);
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

  useEffect(() => {
    if (activeTab === 'campaigns') {
      const timeoutId = setTimeout(() => {
        fetchCampaigns(1);
      }, 300); // Debounce search
      
      return () => clearTimeout(timeoutId);
    }
  }, [activeTab, searchQuery]);

  const fetchCampaigns = async (page: number = 1) => {
    setLoading(true);
    try {
      const response = await axios.get(`/workspaces/${workspace.slug}/api/campaigns`, {
        params: { search: searchQuery, page }
      });
      setCampaigns(response.data.data);
      setPagination(response.data);
    } catch (error) {
      console.error('Failed to fetch campaigns:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <AppLayout>
      <div className="px-4 py-6 space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-3xl font-bold tracking-tight">Ads Manager</h2>
      </div>

      {/* Search and Actions Bar */}
      <div className="flex items-center justify-between gap-4">
        <div className="flex-1 max-w-sm">
          <Input
            type="text"
            placeholder="Search campaigns..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full"
          />
        </div>
        <div className="flex items-center gap-2">
          <Button 
            variant="outline" 
            size="sm"
            onClick={() => fetchCampaigns(pagination.current_page)}
            disabled={loading}
          >
            <RefreshCw className={`h-4 w-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
            Refresh
          </Button>
          <Button variant="outline" size="sm">
            <Filter className="h-4 w-4 mr-2" />
            Filters
          </Button>
          <Button variant="outline" size="icon">
            <Grid3x3 className="h-4 w-4" />
          </Button>
          <Button variant="outline" size="icon">
            <List className="h-4 w-4" />
          </Button>
        </div>
      </div>

      {/* Tabs */}
      <div className="border-b">
        <div className="flex gap-6">
          <button
            onClick={() => setActiveTab('campaigns')}
            className={`pb-3 px-1 text-sm font-medium border-b-2 transition-colors ${
              activeTab === 'campaigns'
                ? 'border-primary text-primary'
                : 'border-transparent text-muted-foreground hover:text-foreground'
            }`}
          >
            Campaigns
          </button>
          <button
            onClick={() => setActiveTab('adSets')}
            className={`pb-3 px-1 text-sm font-medium border-b-2 transition-colors ${
              activeTab === 'adSets'
                ? 'border-primary text-primary'
                : 'border-transparent text-muted-foreground hover:text-foreground'
            }`}
          >
            Ad Sets
          </button>
          <button
            onClick={() => setActiveTab('ads')}
            className={`pb-3 px-1 text-sm font-medium border-b-2 transition-colors ${
              activeTab === 'ads'
                ? 'border-primary text-primary'
                : 'border-transparent text-muted-foreground hover:text-foreground'
            }`}
          >
            Ads
          </button>
          <button
            onClick={() => setActiveTab('optimizationRules')}
            className={`pb-3 px-1 text-sm font-medium border-b-2 transition-colors ${
              activeTab === 'optimizationRules'
                ? 'border-primary text-primary'
                : 'border-transparent text-muted-foreground hover:text-foreground'
            }`}
          >
            Optimization Rules
          </button>
          <button
            onClick={() => setActiveTab('optimizationLogs')}
            className={`pb-3 px-1 text-sm font-medium border-b-2 transition-colors ${
              activeTab === 'optimizationLogs'
                ? 'border-primary text-primary'
                : 'border-transparent text-muted-foreground hover:text-foreground'
            }`}
          >
            Optimization Logs
          </button>
        </div>
      </div>

      {/* Table */}
      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Campaign Name</TableHead>
              <TableHead>Ad Account</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Daily Budget</TableHead>
              <TableHead>Start Time</TableHead>
              <TableHead>End Time</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {loading ? (
              <TableRow>
                <TableCell colSpan={6} className="text-center py-8">
                  Loading campaigns...
                </TableCell>
              </TableRow>
            ) : campaigns.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} className="text-center py-8 text-muted-foreground">
                  No campaigns found
                </TableCell>
              </TableRow>
            ) : (
              campaigns.map((campaign) => (
                <TableRow key={campaign.id}>
                  <TableCell className="font-medium">{campaign.name}</TableCell>
                  <TableCell>{campaign.ad_account?.name || 'N/A'}</TableCell>
                  <TableCell>
                    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                      campaign.status === 'ACTIVE' 
                        ? 'bg-green-100 text-green-800' 
                        : campaign.status === 'PAUSED'
                        ? 'bg-yellow-100 text-yellow-800'
                        : 'bg-gray-100 text-gray-800'
                    }`}>
                      {campaign.status}
                    </span>
                  </TableCell>
                  <TableCell>
                    {campaign.daily_budget ? `$${(campaign.daily_budget / 100).toFixed(2)}` : 'N/A'}
                  </TableCell>
                  <TableCell>
                    {new Date(campaign.start_time).toLocaleDateString()}
                  </TableCell>
                  <TableCell>
                    {campaign.end_time ? new Date(campaign.end_time).toLocaleDateString() : 'Ongoing'}
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      {/* Pagination */}
      <div className="mt-4 flex items-center justify-between">
        <div className="text-sm text-muted-foreground">
          {pagination.total > 0 ? (
            <>Showing {pagination.from} to {pagination.to} of {pagination.total} campaigns</>
          ) : (
            <>No campaigns found</>
          )}
        </div>
        {pagination.last_page > 1 && (
          <div className="flex gap-2">
            {pagination.links.map((link, index) => (
              <Button
                key={index}
                variant={link.active ? 'default' : 'outline'}
                size="sm"
                disabled={!link.url || loading}
                onClick={() => {
                  if (link.url) {
                    const url = new URL(link.url);
                    const page = url.searchParams.get('page');
                    if (page) {
                      fetchCampaigns(parseInt(page));
                    }
                  }
                }}
                dangerouslySetInnerHTML={{ __html: link.label }}
              />
            ))}
          </div>
        )}
      </div>
      </div>
    </AppLayout>
  );
};

export default AdsManager;
