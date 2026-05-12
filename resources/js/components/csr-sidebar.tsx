import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Link, usePage } from '@inertiajs/react';
import { BarChart2, LayoutDashboard, Truck, User } from 'lucide-react';
import AppLogo from './app-logo';

export function CsrSidebar() {
    const { currentWorkspace } = usePage().props as unknown as {
        currentWorkspace: Workspace;
    };

    const slug = currentWorkspace?.slug ?? '';

    const csrNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: `/workspaces/${slug}/csr/dashboard`,
            icon: LayoutDashboard,
        },
        {
            title: 'Management',
            href: `/workspaces/${slug}/csr/management`,
            icon: User,
        },
        {
            title: 'Analytics',
            href: `/workspaces/${slug}/csr/analytics`,
            icon: BarChart2,
        },
        {
            title: 'RMO Management',
            href: `/workspaces/${slug}/csr/rmo-management`,
            icon: Truck,
        },
    ];

    return (
        <Sidebar
            className="bg-white dark:bg-zinc-900"
            collapsible="icon"
            variant="sidebar"
        >
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard().url}>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="p-3">
                <NavMain items={csrNavItems} group_label="CSR" />
            </SidebarContent>
        </Sidebar>
    );
}