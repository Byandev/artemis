import { Workspace } from '@/types/models/Workspace';
import { type PropsWithChildren } from 'react';
import AdsManagerTabs from '../AdsManagerTabs';

type TabType = 'campaigns' | 'adSets' | 'ads' | 'optimizationRules' | 'optimizationLogs';

interface AdsManagerLayoutProps {
    workspace: Workspace;
    activeTab: TabType;
    onTabChange?: (tab: TabType) => void;
}

const AdsManagerLayout = ({ workspace, activeTab, onTabChange, children }: PropsWithChildren<AdsManagerLayoutProps>) => {
    return (
        <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-xl font-semibold text-gray-800 dark:text-white/90">
                    Ads Manager
                </h2>
            </div>

            <div className="">
                <AdsManagerTabs
                    workspace={workspace}
                    activeTab={activeTab}
                    onTabChange={onTabChange}
                />

                <div className="pt-4 dark:border-gray-800">
                    {children}
                </div>
            </div>
        </div>
    );
};

export default AdsManagerLayout;
