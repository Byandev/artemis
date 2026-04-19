import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AdminSidebar } from '@/components/admin-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { type PropsWithChildren } from 'react';

export default function AdminSidebarLayout({ children }: PropsWithChildren) {
    return (
        <AppShell variant="sidebar">
            {/* Pass a custom prop to your Sidebar if it supports it, 
                or create a dedicated <AdminSidebar /> 
            */}
            <AdminSidebar />

            <AppContent variant="sidebar" className="overflow-x-hidden bg-stone-50 dark:bg-[#0F0F11]">
                <AppSidebarHeader />
                {children}
            </AppContent>
        </AppShell>
    );
}