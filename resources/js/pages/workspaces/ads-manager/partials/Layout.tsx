import PageHeader from '@/components/common/PageHeader';
import { Workspace } from '@/types/models/Workspace';
import { type PropsWithChildren } from 'react';
import AdsManagerTabs from './AdsManagerTabs';

type TabType = 'campaigns' | 'adSets' | 'ads' | 'optimizationRules' | 'optimizationLogs';

interface AdsManagerLayoutProps {
    workspace: Workspace;
    activeTab: TabType;
}

const AdsManagerLayout = ({ workspace, activeTab, children }: PropsWithChildren<AdsManagerLayoutProps>) => {
    return (
        <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
            <PageHeader title="Ads Manager" description="Monitor and manage your ad campaigns, ad sets, and ads" />

            <div className="">
                <AdsManagerTabs
                    workspace={workspace}
                    activeTab={activeTab}
                />

                <div className="pt-4 dark:border-gray-800">
                    {children}
                </div>
            </div>
        </div>
    );
};

export default AdsManagerLayout;
