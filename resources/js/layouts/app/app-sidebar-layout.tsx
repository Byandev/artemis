import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { ContactSupportSideTab } from '@/components/contact-support-modal';
import SubscriptionExpiredModal from '@/components/subscription-expired-modal';
import SyncingDataModal from '@/components/syncing-data-modal';
import { usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

export default function AppSidebarLayout({ children }: PropsWithChildren<{}>) {
    const { url } = usePage();
    const showSupport = !url.startsWith('/public/');

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent
                variant="sidebar"
                className="overflow-x-hidden bg-stone-50 dark:bg-[#0F0F11]"
            >
                <AppSidebarHeader />
                {children}
            </AppContent>
            {showSupport && <ContactSupportSideTab />}
            <SubscriptionExpiredModal />
            <SyncingDataModal />
        </AppShell>
    );
}
