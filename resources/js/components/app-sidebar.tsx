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
    CreditCard,
    FacebookIcon,
    LayoutDashboard,
    Package,
    Store,
    MousePointerClickIcon,
    TrendingUp,
    Users,
    BookOpenIcon,
    ShieldCheck
} from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    // 2. Destructure 'auth' from props (as any for easier access to .user.role)
    const { auth, currentWorkspace } = usePage().props as any;

    const dashboardUrl = currentWorkspace
        ? workspace.dashboard.url((currentWorkspace as { slug: string }).slug)
        : dashboard().url;

    const slug = (currentWorkspace as { slug: string })?.slug;

    // 3. Keep your original items
    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardUrl,
            icon: LayoutDashboard,
        },
        {
            title: 'Shops',
            href: `/workspaces/${slug}/shops`,
            icon: Store,
        },
        {
            title: 'Pages',
            href: `/workspaces/${slug}/pages`,
            icon: BookOpenIcon,
        },
        {
            title: 'Products',
            href: `/workspaces/${slug}/products`,
            icon: Package,
        },
        {
            title: 'Teams',
            href: `/workspaces/${slug}/teams`,
            icon: Users,
        },
    ];

    // 4. Add the Role Management item ONLY if the user is authorized
    if (auth.user.role === 'superadmin' || auth.user.role === 'admin') {
        mainNavItems.push({
            title: 'Role Management',
            href: `/workspaces/${slug}/roles`,
            icon: ShieldCheck,
        });
    }

    // 5. Add the remaining item
    mainNavItems.push({
        title: 'RTS Management',
        href: `/workspaces/${slug}/rts`,
        icon: TrendingUp,
    });
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
