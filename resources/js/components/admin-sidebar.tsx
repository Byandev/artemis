import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
<<<<<<< HEAD
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { CreditCard, Layers } from 'lucide-react';
import AppLogo from './app-logo';

export function AdminSidebar() {
    const adminNavItems: NavItem[] = [
=======
import { Link } from '@inertiajs/react';
import { LayoutDashboard, Layers, FileText } from 'lucide-react';
import AppLogo from './app-logo';

export function AdminSidebar() {
    const adminNavItems = [
        {
            title: 'Dashboard',
            href: '/admin/dashboard',
            icon: LayoutDashboard,
        },
>>>>>>> 0535f1b9 (frontend blogpost)
        {
            title: 'Workspaces',
            href: '/admin/workspaces',
            icon: Layers,
        },
<<<<<<< HEAD

        {
            title: 'Subscription Plans',
            href: '/admin/subscription-plans',
            icon: CreditCard,
=======
        {
            title: 'Blog Posts',
            href: '/admin/posts', // Saktong match sa iyong Route::get('/posts')
            icon: FileText,
>>>>>>> 0535f1b9 (frontend blogpost)
        },
    ];

    return (
<<<<<<< HEAD
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
=======
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/admin/dashboard">
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
>>>>>>> 0535f1b9 (frontend blogpost)
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
