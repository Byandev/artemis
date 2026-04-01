// import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
// import { type BreadcrumbItem } from '@/types';
// import { Workspace } from '@/types/models/Workspace';
// import { type ReactNode } from 'react';
// import { Toaster } from 'sonner'; // 1. Import the Toaster

// interface AppLayoutProps {
//     children: ReactNode;
//     breadcrumbs?: BreadcrumbItem[]; // Standard for Laravel Starter kits
// }

// export default ({ children, ...props }: AppLayoutProps) => (
//     <AppLayoutTemplate {...props}>
//         {children}
//         {/* 2. Add the Toaster here. 'richColors' makes success green and error red */}
//         <Toaster position="top-right" richColors closeButton />
//     </AppLayoutTemplate>
// );

import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { type BreadcrumbItem } from '@/types';
import { ReactNode, useEffect } from 'react'; 
import { Toaster, toast } from 'sonner'; 
import { usePage } from '@inertiajs/react'; 

interface AppLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

export default function AppLayout({ children, ...props }: AppLayoutProps) {
    // We pull the flash object from the shared Inertia props
    const { flash } = usePage().props as any;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]); // This runs every time the page finishes a request

    return (
        <AppLayoutTemplate {...props}>
            {children}
            <Toaster position="top-right" richColors closeButton />
        </AppLayoutTemplate>
    );
}
