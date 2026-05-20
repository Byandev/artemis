import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Link, usePage } from '@inertiajs/react';
import { LayoutDashboard, Truck } from 'lucide-react';
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
            title: 'RMO Management',
            href: `/workspaces/${slug}/csr/rmo-management`,
            icon: Truck,
        },
    ];

    return (
        <Sidebar
            collapsible="icon"
            variant="sidebar"
            style={
                {
                    '--sidebar': '#022c22',
                    '--sidebar-foreground': '#a7f3d0',
                    '--sidebar-accent': 'rgba(16, 211, 161, 0.10)',
                    '--sidebar-accent-foreground': '#ffffff',
                    '--sidebar-border': 'rgba(167, 243, 208, 0.08)',
                } as React.CSSProperties
            }
            className="dark:[--sidebar:#022c22] dark:[--sidebar-foreground:#a7f3d0] dark:[--sidebar-accent:rgba(16,211,161,0.10)] dark:[--sidebar-accent-foreground:#ffffff] dark:[--sidebar-border:rgba(167,243,208,0.08)]"
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

            <SidebarFooter className="px-4 pb-4">
                <div className="rounded-lg border border-emerald-500/10 bg-emerald-500/5 px-3 py-2.5">
                    <div className="flex items-center gap-2">
                        <span className="relative flex h-2 w-2">
                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                            <span className="relative inline-flex h-2 w-2 rounded-full bg-emerald-400" />
                        </span>
                        <span className="text-[12px] font-medium text-emerald-200">
                            Online
                        </span>
                    </div>
                </div>
            </SidebarFooter>
        </Sidebar>
    );
}
