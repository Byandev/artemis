import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { CreditCard, Layers } from 'lucide-react';
import AppLogo from './app-logo';

export function AdminSidebar() {
    const adminNavItems: NavItem[] = [
        {
            title: 'Workspaces',
            href: '/admin/workspaces',
            icon: Layers,
        },

        {
            title: 'Subscription Plans',
            href: '/admin/subscription-plans',
            icon: CreditCard,
        },
    ];

    return (
        <Sidebar
            className="border-r border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900"
            collapsible="icon"
            variant="sidebar"
        >
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <div className="flex flex-col p-2">
                            <SidebarMenuButton size="lg" asChild>
                                <Link href="/admin/workspaces">
                                    <AppLogo />
                                </Link>
                            </SidebarMenuButton>
                        </div>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="p-3">
                <NavMain
                    items={adminNavItems}
                    group_label="Platform Management"
                />
            </SidebarContent>
        </Sidebar>
    );
}
