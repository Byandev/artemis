import { AppContent } from '@/components/app-content';
import { AppHeader } from '@/components/app-header';
import { AppShell } from '@/components/app-shell';
import { ContactSupportModal } from '@/components/contact-support-modal';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { LifeBuoy } from 'lucide-react';
import type { PropsWithChildren } from 'react';

export default function AppHeaderLayout({
    children,
    breadcrumbs,
}: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[] }>) {
    const { url } = usePage();
    const showSupport = !url.startsWith('/public/');

    return (
        <AppShell>
            <AppHeader breadcrumbs={breadcrumbs} />
            <AppContent>{children}</AppContent>
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
        </AppShell>
    );
}
