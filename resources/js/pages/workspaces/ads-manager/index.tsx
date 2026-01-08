import ComponentCard from '@/components/common/ComponentCard';
import { Button } from '@/components/ui/button';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';
import { addDays } from 'date-fns';
import { Grid3x3, List } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import AdSetsTab from './AdSetsTab';
import AdsTab from './AdsTab';
import CampaignsTab from './CampaignsTab';
import AdsManagerLayout from './partials/Layout';

type TabType = 'campaigns' | 'adSets' | 'ads' | 'optimizationRules' | 'optimizationLogs';

interface PageProps {
  workspace: Workspace;
}

const AdsManager = ({ workspace }: PageProps) => {
  const [activeTab, setActiveTab] = useState<TabType>('campaigns');
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('');
  const [dateRange, setDateRange] = useState<{ from: Date; to: Date }>({
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
      <AdsManagerLayout
        workspace={workspace}
        activeTab={activeTab}
      >
        <div className="space-y-5 sm:space-y-6">
          <ComponentCard desc="Manage your advertising campaigns and ad sets">
            <div>
              {activeTab !== 'optimizationRules' && (
                <div className="flex flex-col gap-2 rounded-t-xl border border-b-0 border-gray-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/5">
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
              )}

              {renderTabContent()}
            </div>
          </ComponentCard>
        </div>
      </AdsManagerLayout>
    </AppLayout>
  );
};

export default AdsManager;
