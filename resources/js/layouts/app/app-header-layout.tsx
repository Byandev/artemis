import { AppContent } from '@/components/app-content';
import { AppHeader } from '@/components/app-header';
import { AppShell } from '@/components/app-shell';
import { ContactSupportSideTab } from '@/components/contact-support-modal';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
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
            {showSupport && <ContactSupportSideTab />}
        </AppShell>
    );
}
