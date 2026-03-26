import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { type BreadcrumbItem } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { type ReactNode } from 'react';
import { Toaster } from 'sonner'; // 1. Import the Toaster

interface AppLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[]; // Standard for Laravel Starter kits
}

export default ({ children, ...props }: AppLayoutProps) => (
    <AppLayoutTemplate {...props}>
        {children}
        {/* 2. Add the Toaster here. 'richColors' makes success green and error red */}
        <Toaster position="top-right" richColors closeButton />
    </AppLayoutTemplate>
);