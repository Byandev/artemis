import { useCampaignSelectionStore } from '@/stores/useCampaignSelectionStore';
import { Workspace } from '@/types/models/Workspace';
import { router } from '@inertiajs/react';

type TabType = 'campaigns' | 'adSets' | 'ads' | 'optimizationRules' | 'optimizationLogs';

interface AdsManagerTabsProps {
    workspace: Workspace;
    activeTab: TabType;
}

const AdsManagerTabs = ({ workspace, activeTab }: AdsManagerTabsProps) => {
    const { selections } = useCampaignSelectionStore();
    const campaignSelections = selections['campaigns'] || {};
    const selectedCount = Object.values(campaignSelections).filter(Boolean).length;

    const handleTabClick = (tab: TabType) => {
        const routes: Record<TabType, string> = {
            campaigns: `/workspaces/${workspace.slug}/ads-manager/campaigns`,
            adSets: `/workspaces/${workspace.slug}/ads-manager/ad-sets`,
            ads: `/workspaces/${workspace.slug}/ads-manager/ads`,
            optimizationRules: `/workspaces/${workspace.slug}/ads-manager/optimization-rules`,
            optimizationLogs: `/workspaces/${workspace.slug}/ads-manager/optimization-logs`,
        };

        router.get(routes[tab]);
    };

    const getTabClassName = (tab: TabType) => {
        return `pb-3 px-2 sm:px-3 text-xs sm:text-sm font-medium border-b-2 transition-colors whitespace-nowrap ${activeTab === tab
            ? 'border-brand-500 text-brand-500 dark:border-brand-400 dark:text-brand-400'
            : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
            }`;
    };

    return (
        <div className="border-b border-gray-200 dark:border-white/5 overflow-x-auto">
            <div className="flex gap-3 sm:gap-4 md:gap-6 min-w-max">
                <button
                    onClick={() => handleTabClick('campaigns')}
                    className={getTabClassName('campaigns')}
                >
                    <span className="hidden sm:inline">Campaigns</span>
                    <span className="sm:hidden">Campaigns</span>
                    {selectedCount > 0 && (
                        <span className="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-brand-100 text-brand-800 dark:bg-brand-900/30 dark:text-brand-300">
                            {selectedCount}
                        </span>
                    )}
                </button>
                <button
                    onClick={() => handleTabClick('adSets')}
                    className={getTabClassName('adSets')}
                >
                    <span className="hidden sm:inline">Ad Sets</span>
                    <span className="sm:hidden">Sets</span>
                </button>
                <button
                    onClick={() => handleTabClick('ads')}
                    className={getTabClassName('ads')}
                >
                    Ads
                </button>
                <button
                    onClick={() => handleTabClick('optimizationRules')}
                    className={getTabClassName('optimizationRules')}
                >
                    <span className="hidden md:inline">Optimization Rules</span>
                    <span className="md:hidden">Rules</span>
                </button>
                <button
                    onClick={() => handleTabClick('optimizationLogs')}
                    className={getTabClassName('optimizationLogs')}
                >
                    <span className="hidden md:inline">Optimization Logs</span>
                    <span className="md:hidden">Logs</span>
                </button>
            </div>
        </div>
    );
};

export default AdsManagerTabs;
