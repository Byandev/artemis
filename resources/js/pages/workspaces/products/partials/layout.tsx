import AppLayout from '@/layouts/app-layout';
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
                <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <h2
                        className="text-[22px] font-semibold tracking-tight text-gray-900 dark:text-gray-100"
                        x-text="pageName"
                    >
                        Products
                    </h2>
                </div>

                <div>
                    {children}
                </div>
            </div>
        </AppLayout>
    )
}

export default Layout;
