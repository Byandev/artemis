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
import { Link } from '@inertiajs/react';
import {
    LayoutDashboard,
    Layers,
    Users,
    Settings,
} from 'lucide-react';
import AppLogo from './app-logo';

export function AdminSidebar() {
    const adminNavItems: NavItem[] = [
        {
            title: 'Admin Dashboard',
            href: '/admin',
            icon: LayoutDashboard,
        },
        {
            title: 'Workspaces',
            href: '/admin/workspaces',
            icon: Layers,
        },
        {
            title: 'User Management',
            href: '/admin/users',
            icon: Users,
        },
        {
            title: 'System Settings',
            href: '/admin/settings',
            icon: Settings,
        },
    ];

    return (
        <Sidebar
            className="bg-white dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-800"
            collapsible="icon"
            variant="sidebar"
        >
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <div className="flex flex-col p-2">
                            <SidebarMenuButton size="lg" asChild>
                                <Link href={dashboard().url}>
                                    <AppLogo />
                                </Link>
                            </SidebarMenuButton>
                            {/* Visual indicator that this is the Admin Panel */}
                            <span className="mt-1 px-2 text-[10px] font-bold uppercase tracking-widest text-red-500">
                                Admin Portal
                            </span>
                        </div>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="p-3">
                <NavMain items={adminNavItems} group_label="Platform Management" />
            </SidebarContent>
        </Sidebar>
    );
}