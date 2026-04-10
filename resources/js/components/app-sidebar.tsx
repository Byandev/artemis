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
    ClipboardList,
    ListChecks,
    Store,
    Users,
    BookOpenIcon,
    Settings,
    User,
    ShieldIcon,
    RotateCcw,
    BarChart2,
    MapPin,

} from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { currentWorkspace } = usePage().props;

    const dashboardUrl = currentWorkspace
        ? workspace.dashboard.url((currentWorkspace as { slug: string }).slug)
        : dashboard().url;

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardUrl,
            icon: LayoutDashboard,
        },
        {
            title: 'Shops',
            href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/shops`,
            icon: Store,
        },
        {
            title: 'Pages',
            href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/pages`,
            icon: BookOpenIcon,
        },
        {
            title: 'Products',
            href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/products/list`,
            icon: Package,
        },
        {
            title: 'Teams',
            href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/teams`,
            icon: Users,
        },
        {
            // Stash marker: checklist nav item touched for local stash grouping.
            title: 'Checklist',
            href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/checklist`,
            icon: ListChecks,
        },
        {
            title: 'Employees',
            href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/employees`,
            icon: User,
        },
        {
            title: 'RTS',
            icon: RotateCcw,
            items: [
                {
                    title: 'Analytics',
                    href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/rts/analytics`,
                    icon: BarChart2,
                },
                {
                    title: 'Parcel Journey',
                    href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/rts/parcel-journeys`,
                    icon: MapPin,
                },
            ],
        },
        {
            title: 'Roles',
            href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/roles`,
            icon: ShieldIcon,
        },
                {
            title: 'Inventory',
            icon: Package,
            items: [
                {
                    title: 'Purchased Orders',
                    href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/inventory/purchased-orders`,
                    icon: ClipboardList,
                },
                {
                    title: 'PPW',
                    href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/inventory/ppws`,
                    icon: BarChart2,
                },
                {
                    title: 'Transaction Logs',
                    href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/inventory/transactions`,
                    icon: ClipboardList,
                },
            ],
        },
    ];

    // const mainNavItems: NavItem[] = [
    //     {
    //         title: 'Dashboard',
    //         href: dashboardUrl,
    //         icon: LayoutDashboard,
    //     },
    //     // {
    //     //     title: 'Ads Manager',
    //     //     href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/ads-manager`,
    //     //     icon: Target,
    //     // },
    //     {
    //         title: 'Shops',
    //         href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/shops`,
    //         icon: Store,
    //     },
    //     {
    //         title: 'Pages',
    //         href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/pages`,
    //         icon: BookOpenIcon,
    //     },
    //     {
    //         title: 'Products',
    //         href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/products`,
    //         icon: Package,
    //     },
    //     // {
    //     //     title: 'Facebook Accounts',
    //     //     href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/facebook-accounts`,
    //     //     icon: FacebookIcon,
    //     // },
    //     // {
    //     //     title: 'Ad Accounts',
    //     //     href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/ad-accounts`,
    //     //     icon: CreditCard,
    //     // },
    //     {
    //         title: 'Teams',
    //         href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/teams`,
    //         icon: Users,
    //     },
    //     {
    //         title: 'RTS Management',
    //         href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/rts`,
    //         icon: TrendingUp,
    //     },
    //     // {
    //     //     title: 'Botcake',
    //     //     href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/botcake`,
    //     //     icon: MousePointerClickIcon,
    //     // },
    // ];

    const accountNavItems: NavItem[] = [
        {
            title: 'Profile',
            href: '/profile',
            icon: User,
        },

        {
            title: 'Settings',
            href: `/settings`,
            icon: Settings,
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
