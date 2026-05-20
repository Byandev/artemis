import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { CsrSidebar } from '@/components/csr-sidebar';
import SubscriptionExpiredModal from '@/components/subscription-expired-modal';
import { type ReactNode } from 'react';
import { Toaster } from 'sonner';

interface CsrLayoutProps {
    children: ReactNode;
}

export default function CsrLayout({ children }: CsrLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <CsrSidebar />
            <AppContent
                variant="sidebar"
                className="overflow-x-hidden bg-stone-50 dark:bg-[#0F0F11]"
            >
                <AppSidebarHeader />
                {children}
            </AppContent>
            <SubscriptionExpiredModal />
            <Toaster position="top-right" richColors closeButton />
        </AppShell>
    );
}
