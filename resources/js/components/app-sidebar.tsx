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
import { type NavItem as BaseNavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    LayoutDashboard,
    Package,
    Store,
    Users,
    BookOpenIcon,
    ShieldCheck,
    TrendingUp,
    ChevronDown
} from 'lucide-react';
import AppLogo from './app-logo';
import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';

interface ExtendedNavItem extends BaseNavItem {
    items?: {
        title: string;
        href: string;
    }[];
}

export function AppSidebar() {
    const { auth, currentWorkspace } = usePage().props as any;
    const [openMenus, setOpenMenus] = useState<string[]>([]);

    const toggleMenu = (title: string) => {
        setOpenMenus(prev =>
            prev.includes(title) ? prev.filter(i => i !== title) : [...prev, title]
        );
    };

    const dashboardUrl = currentWorkspace
        ? workspace.dashboard.url((currentWorkspace as { slug: string }).slug)
        : dashboard().url;

    const slug = (currentWorkspace as { slug: string })?.slug;

    const mainNavItems: ExtendedNavItem[] = [
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

    mainNavItems.push({
        title: 'RTS Management',
        href: `/workspaces/${slug}/rts`,
        icon: TrendingUp,
    });

    if (auth.user.role === 'superadmin' || auth.user.role === 'admin' || true) {
        mainNavItems.push({
            title: 'Role Management',
            href: '#',
            icon: ShieldCheck,
            items: [
                {
                    title: 'Add Roles',
                    href: `/workspaces/${slug}/roles/create`,
                },
                {
                    title: 'View All Roles',
                    href: `/workspaces/${slug}/roles`,
                },
            ],
        });
    }

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
                <div className="px-2 py-2">
                    {mainNavItems.map((item) => {
                        // Assign to capitalized variable so JSX recognizes it
                        const Icon = item.icon;

                        return (
                            <div key={item.title} className="mb-1">
                                {item.items ? (
                                    <>
                                        <button
                                            onClick={() => toggleMenu(item.title)}
                                            className="w-full flex items-center justify-between p-2 rounded-md hover:bg-accent hover:text-accent-foreground transition-colors group text-left"
                                        >
                                            <div className="flex items-center gap-3">
                                                {Icon && <Icon className="w-4 h-4 text-muted-foreground group-hover:text-foreground" />}
                                                <span className="text-sm font-medium">{item.title}</span>
                                            </div>
                                            <ChevronDown
                                                className={`w-4 h-4 text-muted-foreground transition-transform duration-200 ${openMenus.includes(item.title) ? 'rotate-180' : ''
                                                    }`}
                                            />
                                        </button>
                                        <AnimatePresence initial={false}>
                                            {openMenus.includes(item.title) && (
                                                <motion.div
                                                    initial={{ height: 0, opacity: 0 }}
                                                    animate={{ height: 'auto', opacity: 1 }}
                                                    exit={{ height: 0, opacity: 0 }}
                                                    transition={{ duration: 0.2, ease: "easeInOut" }}
                                                    className="overflow-hidden"
                                                >
                                                    <div className="ml-4 pl-3 border-l border-border mt-1 space-y-1">
                                                        {item.items.map((subItem) => (
                                                            <Link
                                                                key={subItem.href}
                                                                href={subItem.href}
                                                                className="block py-2 px-2 text-sm text-muted-foreground hover:text-foreground transition-colors"
                                                            >
                                                                {subItem.title}
                                                            </Link>
                                                        ))}
                                                    </div>
                                                </motion.div>
                                            )}
                                        </AnimatePresence>
                                    </>
                                ) : (
                                    <Link
                                        href={item.href}
                                        className="flex items-center gap-3 p-2 rounded-md hover:bg-accent hover:text-accent-foreground transition-colors group"
                                    >
                                        {Icon && <Icon className="w-4 h-4 text-muted-foreground group-hover:text-foreground" />}
                                        <span className="text-sm font-medium">{item.title}</span>
                                    </Link>
                                )}
                            </div>
                        );
                    })}
                </div>
            </SidebarContent>
        </Sidebar>
    );
}
