import { Workspace } from '@/types/models/Workspace';
import { router } from '@inertiajs/react';

type TabType = 'campaigns' | 'adSets' | 'ads' | 'optimizationRules' | 'optimizationLogs';

interface AdsManagerTabsProps {
    workspace: Workspace;
    activeTab: TabType;
    onTabChange?: (tab: TabType) => void;
}

const AdsManagerTabs = ({ workspace, activeTab, onTabChange }: AdsManagerTabsProps) => {
    const handleTabClick = (tab: TabType) => {
        if (tab === 'optimizationRules') {
            router.get(`/workspaces/${workspace.slug}/ads-manager/optimization-rules`);
        } else if (onTabChange) {
            onTabChange(tab);
        }
    };

    const getTabClassName = (tab: TabType) => {
        return `pb-3 px-1 text-sm font-medium border-b-2 transition-colors ${activeTab === tab
                ? 'border-brand-500 text-brand-500 dark:border-brand-400 dark:text-brand-400'
                : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
            }`;
    };

    return (
        <div className="border-b border-gray-200 dark:border-white/5">
            <div className="flex gap-6">
                <button
                    onClick={() => handleTabClick('campaigns')}
                    className={getTabClassName('campaigns')}
                >
                    Campaigns
                </button>
                <button
                    onClick={() => handleTabClick('adSets')}
                    className={getTabClassName('adSets')}
                >
                    Ad Sets
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
                    Optimization Rules
                </button>
                <button
                    onClick={() => handleTabClick('optimizationLogs')}
                    className={getTabClassName('optimizationLogs')}
                >
                    Optimization Logs
                </button>
            </div>
        </div>
    );
};

export default AdsManagerTabs;
