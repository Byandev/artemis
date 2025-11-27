import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { type BreadcrumbItem } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { type ReactNode } from 'react';

interface AppLayoutProps {
    children: ReactNode;
    workspaces?: Workspace[];
    currentWorkspace?: Workspace;
}

export default ({ children, workspaces, currentWorkspace, ...props }: AppLayoutProps) => (
    <AppLayoutTemplate workspaces={workspaces} currentWorkspace={currentWorkspace} {...props}>
        {children}
    </AppLayoutTemplate>
);
