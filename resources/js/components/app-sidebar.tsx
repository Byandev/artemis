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
import workspace from '@/routes/workspace';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    LayoutDashboard,
    Package,
    Store,
    Users,
    BookOpenIcon,
    User,
    ShieldIcon,
    RotateCcw,
    BarChart2,
    MapPin,
    ClipboardList,
    Box,
    Layers,

} from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { currentWorkspace, appEnv } = usePage().props as unknown as { currentWorkspace: { slug: string }; appEnv: string };

    const dashboardUrl = currentWorkspace
        ? workspace.dashboard.url(currentWorkspace.slug)
        : dashboard().url;

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardUrl,
            icon: LayoutDashboard,
        },
        {
            title: 'Shops',
            href: `/workspaces/${currentWorkspace.slug}/shops`,
            icon: Store,
        },
        {
            title: 'Pages',
            href: `/workspaces/${currentWorkspace.slug}/pages`,
            icon: BookOpenIcon,
        },
        {
            title: 'Products',
            href: `/workspaces/${currentWorkspace.slug}/products/list`,
            icon: Package,
        },
        {
            title: 'Teams',
            href: `/workspaces/${currentWorkspace.slug}/teams`,
            icon: Users,
        },

        {
            title: 'CSR',
            icon: User,
            items: [
                {
                    title: 'Management',
                    href: `/workspaces/${currentWorkspace.slug}/csr/management`,
                    icon: User,
                },
                {
                    title: 'CSR Analytics',
                    href: `/workspaces/${currentWorkspace.slug}/csr/analytics`,
                    icon: BarChart2,
                },
            ],
        },
        ...(appEnv !== 'production' ? [{
            title: 'Inventory',
            icon: Box,
            items: [
                {
                    title: 'PPW',
                    href: `/workspaces/${currentWorkspace.slug}/inventory/ppws`,
                    icon: BarChart2,
                },
                {
                    title: 'Transaction Logs',
                    href: `/workspaces/${currentWorkspace.slug}/inventory/transactions`,
                    icon: ClipboardList,
                },
                {
                    title: 'Inventory Items',
                    href: `/workspaces/${currentWorkspace.slug}/inventory/items`,
                    icon: Layers,
                },
            ],
        }] : []),
        {
            title: 'RTS',
            icon: RotateCcw,
            items: [
                {
                    title: 'Analytics',
                    href: `/workspaces/${currentWorkspace.slug}/rts/analytics`,
                    icon: BarChart2,
                },
                {
                    title: 'Parcel Journey',
                    href: `/workspaces/${currentWorkspace.slug}/rts/parcel-journeys`,
                    icon: MapPin,
                },
            ],
        },
        {
            title: 'Roles',
            href: `/workspaces/${currentWorkspace.slug}/roles`,
            icon: ShieldIcon,
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
                <NavMain items={mainNavItems} group_label="Main" />
                {/*<NavMain items={accountNavItems} group_label="Account" />*/}
            </SidebarContent>

            {/*<SidebarFooter>*/}
            {/*    <NavFooter items={footerNavItems} className="mt-auto" />*/}
            {/*</SidebarFooter>*/}
        </Sidebar>
    );
}
