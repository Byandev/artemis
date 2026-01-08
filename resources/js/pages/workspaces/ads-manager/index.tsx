import { useState, useEffect, useRef } from 'react';
import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Grid3x3, List, RefreshCw } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import ComponentCard from '@/components/common/ComponentCard';
import { Workspace } from '@/types/models/Workspace';
import { SimpleDateRangePicker } from '@/components/ui/simple-date-range-picker';
import { addDays } from 'date-fns';
import { DateRange } from 'react-day-picker';
import CampaignsTab from './CampaignsTab';
import AdSetsTab from './AdSetsTab';
import AdsTab from './AdsTab';

type TabType = 'campaigns' | 'adSets' | 'ads' | 'optimizationRules' | 'optimizationLogs';

interface PageProps {
  workspace: Workspace;
}

const AdsManager = ({ workspace }: PageProps) => {
  const [activeTab, setActiveTab] = useState<TabType>('campaigns');
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('');
  const [dateRange, setDateRange] = useState<DateRange | undefined>({
    from: addDays(new Date(), -30),
    to: new Date(),
  });
  const [loading, setLoading] = useState(false);

  // Refs to trigger fetch from child components
  const campaignsTabRef = useRef<any>(null);
  const adSetsTabRef = useRef<any>(null);
  const adsTabRef = useRef<any>(null);

  useEffect(() => {
    const timeoutId = setTimeout(() => {
      handleRefresh();
    }, 300);
    
    return () => clearTimeout(timeoutId);
  }, [activeTab, searchQuery, statusFilter, dateRange]);

  const handleRefresh = () => {
    if (activeTab === 'campaigns' && campaignsTabRef.current) {
      campaignsTabRef.current.fetchCampaigns(1);
    } else if (activeTab === 'adSets' && adSetsTabRef.current) {
      adSetsTabRef.current.fetchAdSets();
    } else if (activeTab === 'ads' && adsTabRef.current) {
      adsTabRef.current.fetchAds();
    }
  };

  const renderTabContent = () => {
    switch (activeTab) {
      case 'campaigns':
        return (
          <CampaignsTab
            ref={campaignsTabRef}
            workspace={workspace}
            searchQuery={searchQuery}
            statusFilter={statusFilter}
            dateRange={dateRange}
            loading={loading}
            setLoading={setLoading}
          />
        );
      case 'adSets':
        return (
          <AdSetsTab
            ref={adSetsTabRef}
            workspace={workspace}
            searchQuery={searchQuery}
            statusFilter={statusFilter}
            dateRange={dateRange}
            loading={loading}
            setLoading={setLoading}
          />
        );
      case 'ads':
        return (
          <AdsTab
            ref={adsTabRef}
            workspace={workspace}
            searchQuery={searchQuery}
            statusFilter={statusFilter}
            dateRange={dateRange}
            loading={loading}
            setLoading={setLoading}
          />
        );
      case 'optimizationRules':
        return (
          <div className="p-8 text-center text-gray-500 dark:text-gray-400">
            Optimization Rules feature coming soon...
          </div>
        );
      case 'optimizationLogs':
        return (
          <div className="p-8 text-center text-gray-500 dark:text-gray-400">
            Optimization Logs feature coming soon...
          </div>
        );
      default:
        return null;
    }
  };

  const getTabLabel = () => {
    switch (activeTab) {
      case 'campaigns':
        return 'campaigns';
      case 'adSets':
        return 'ad sets';
      case 'ads':
        return 'ads';
      case 'optimizationRules':
        return 'rules';
      case 'optimizationLogs':
        return 'logs';
      default:
        return 'items';
    }
  };

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
                  placeholder={`Search ${getTabLabel()}...`}
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                />
                <div className="flex items-center gap-2 relative z-50">
                  {/* <Button 
                    variant="outline" 
                    size="sm"
                    onClick={handleRefresh}
                    disabled={loading}
                  >
                    <RefreshCw className={`h-4 w-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
                    Refresh
                  </Button> */}
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
                    placeholder="Select date range"
                  />
                  <Button variant="outline" size="icon">
                    <Grid3x3 className="h-4 w-4" />
                  </Button>
                  <Button variant="outline" size="icon">
                    <List className="h-4 w-4" />
                  </Button>
                </div>
              </div>

              {renderTabContent()}
            </div>
          </ComponentCard>
        </div>
      </div>
    </AppLayout>
  );
};

export default AdsManager;
