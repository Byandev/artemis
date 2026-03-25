import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { type BreadcrumbItem } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { type PropsWithChildren } from 'react';

export default function AppSidebarLayout({
    children,
}: PropsWithChildren<{}
   >) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden bg-stone-50 dark:bg-[#0F0F11]">
                <AppSidebarHeader />
                {children}
            </AppContent>
        </AppShell>
    );
}
