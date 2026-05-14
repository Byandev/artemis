import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { ContactSupportModal } from '@/components/contact-support-modal';
import SubscriptionExpiredModal from '@/components/subscription-expired-modal';
import SyncingDataModal from '@/components/syncing-data-modal';
import { Button } from '@/components/ui/button';
import { usePage } from '@inertiajs/react';
import { LifeBuoy } from 'lucide-react';
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
            {showSupport && (
                <ContactSupportModal
                    trigger={
                        <Button
                            type="button"
                            className="fixed right-6 bottom-6 z-50 h-12 w-12 rounded-full bg-brand-500 text-white shadow-lg shadow-brand-500/30 hover:bg-brand-600"
                            aria-label="Customer support"
                        >
                            <LifeBuoy className="h-5 w-5" />
                        </Button>
                    }
                />
            )}
            <SubscriptionExpiredModal />
            <SyncingDataModal />
        </AppShell>
    );
}
