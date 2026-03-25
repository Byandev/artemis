import AppLayout from '@/layouts/app-layout';
import PageHeader from '@/components/common/PageHeader';
import { Workspace } from '@/types/models/Workspace';
import { type PropsWithChildren } from 'react';

interface DashboardLayoutProps {
    workspace: Workspace;
}

const DashboardLayout = ({ workspace, children }: PropsWithChildren<DashboardLayoutProps>) => {
    return (
        <AppLayout>
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader title="Dashboard" description="Overview of your workspace performance and key metrics" />

                <div className="">
                    {children}
                </div>
            </div>
        </AppLayout>
    );
};

export default DashboardLayout;
