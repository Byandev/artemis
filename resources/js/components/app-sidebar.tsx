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
import { BookOpen, FacebookIcon, Folder, LayoutGrid, Package, Users } from 'lucide-react';
import AppLogo from './app-logo';

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { currentWorkspace } = usePage().props;

    const dashboardUrl = currentWorkspace
        ? workspace.dashboard.url((currentWorkspace as { slug: string }).slug)
        : dashboard().url;

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardUrl,
            icon: LayoutGrid,
        },
        {
            title: 'Shop and Pages',
            href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/pages`,
            icon: LayoutGrid,
        },
        {
            title: 'Products',
            href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/products`,
            icon: Package,
        },
        {
            title: 'Facebook Accounts',
            href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/facebook-accounts`,
            icon: FacebookIcon,
        },
        {
            title: 'Ad Accounts',
            href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/ad-accounts`,
            icon: FacebookIcon,
        },
        {
            title: 'Teams',
            href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/teams`,
            icon: Users,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            {/* <SidebarFooter>
            </SidebarFooter> */}
        </Sidebar>
    );
}
