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
import { dashboard, logout } from '@/routes';
import workspace from '@/routes/workspace';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    LayoutDashboard,
    Package,
    Store,
    TrendingUp,
    Users,
    BookOpenIcon,
    LogOut,
    Settings,
    User,
} from 'lucide-react';
import AppLogo from './app-logo';
import { NavFooter } from '@/components/nav-footer';

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
            href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/products`,
            icon: Package,
        },
        {
            title: 'Teams',
            href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/teams`,
            icon: Users,
        },
        // {
        //     title: 'RTS Management',
        //     icon: TrendingUp,
        //     items: [
        //         {
        //             title: 'Analytics',
        //             href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/rts/analytics`,
        //         },
        //         {
        //             title: 'Delivery',
        //             href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/rts/for-delivery-today`,
        //         },
        //         {
        //             title: 'Parcel Updates',
        //             href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/rts/parcel-update-notification`,
        //         },
        //         {
        //             title: 'Parcel Updates Templates',
        //             href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/rts/parcel-journey-notification-templates`,
        //         },
        //     ],
        // },
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

    const footerNavItems: NavItem[] = [

        {
            title: 'Logout',
            href: logout(),
            icon: LogOut,
        },
    ];




    return (
        <Sidebar
            className="bg-white"
            collapsible="icon"
            variant="sidebar"
        >
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="p-3">
                <NavMain items={mainNavItems} group_label="Main" />
                <NavMain items={accountNavItems} group_label="Account" />
            </SidebarContent>

                <SidebarFooter>
                    <NavFooter items={footerNavItems} className="mt-auto" />
                </SidebarFooter>
        </Sidebar>
    );
}
