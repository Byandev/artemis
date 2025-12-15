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
    BookOpen, 
    Folder, 
    LayoutDashboard, 
    Package, 
    Users,
    Store,
    Target,
    UserCircle,
    CreditCard,
    FacebookIcon,
    TrendingUp
} from 'lucide-react';
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
            icon: LayoutDashboard,
        },
          {
            title: 'Ads Manager',
            href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/ads-manager`,
            icon: Target,
        },
        {
            title: 'Shop and Pages',
            href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/pages`,
            icon: Store,
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
            icon: CreditCard,
        },
        {
            title: 'Teams',
            href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/teams`,
            icon: Users,
        },
        {
            title: 'RTS Management',
            href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/rts/analytics`,
            icon: TrendingUp,
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
