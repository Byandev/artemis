import AppLayout from '@/layouts/app-layout';
import PageHeader from '@/components/common/PageHeader';
import type { ReactNode } from 'react';
import { Workspace } from '@/types/models/Workspace';

interface LayoutProps {
    children: ReactNode;
    workspace: Workspace
}

const Layout = ({  children, workspace }: LayoutProps) => {
    return (
        <AppLayout>
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader title="Products" description="Manage your product catalog and track performance" />

                <div>
                    {children}
                </div>
            </div>
        </AppLayout>
    )
}

export default Layout;
