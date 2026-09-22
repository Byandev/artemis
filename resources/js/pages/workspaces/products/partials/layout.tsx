import PageHeader from '@/components/common/PageHeader';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import type { ReactNode } from 'react';

interface LayoutProps {
    children: ReactNode;
    workspace: Workspace;
    headerActions?: ReactNode;
    title?: string;
    description?: string;
}

const Layout = ({
    children,
    headerActions,
    title = 'Products',
    description = 'Manage your product catalog and track performance',
}: LayoutProps) => {
    return (
        <AppLayout>
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader title={title} description={description}>
                    {headerActions}
                </PageHeader>

                <div>{children}</div>
            </div>
        </AppLayout>
    );
};

export default Layout;
