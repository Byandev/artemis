import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { type PropsWithChildren } from 'react';

interface DashboardLayoutProps {
    workspace: Workspace;
}

const DashboardLayout = ({ workspace, children }: PropsWithChildren<DashboardLayoutProps>) => {
    return (
        <AppLayout>
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <h2
                        className="text-[22px] font-semibold tracking-tight text-gray-900 dark:text-gray-100"
                        x-text="pageName"
                    >
                        Dashboard
                    </h2>
                </div>

                <div className="">
                    {children}
                </div>
            </div>
        </AppLayout>
    );
};

export default DashboardLayout;
