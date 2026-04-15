import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupLabel,
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
    ShoppingCart,
    Truck,
    Trophy,
    FileText,
    Copy,
    Check,
    ExternalLink,
    Wallet,
    Landmark,
    ArrowLeftRight,
    Send,
} from 'lucide-react';
import { useState } from 'react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { currentWorkspace } = usePage().props as unknown as { currentWorkspace: { slug: string; show_inventory: boolean } };

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
        ...(currentWorkspace.show_inventory
            ? [
                  {
                      title: 'Inventory',
                      icon: Box,
                      items: [
                          {
                              title: 'Inventory Items',
                              href: `/workspaces/${currentWorkspace.slug}/inventory/items`,
                              icon: Layers,
                          },
                          {
                              title: 'Transaction Logs',
                              href: `/workspaces/${currentWorkspace.slug}/inventory/transactions`,
                              icon: ClipboardList,
                          },
                          {
                              title: 'Purchased Orders',
                              href: `/workspaces/${currentWorkspace.slug}/inventory/purchased-orders`,
                              icon: ShoppingCart,
                          },
                      ],
                  },
              ]
            : []),
        {
            title: 'Finance',
            icon: Wallet,
            items: [
                {
                    title: 'Dashboard',
                    href: `/workspaces/${currentWorkspace.slug}/finance/dashboard`,
                    icon: LayoutDashboard,
                },
                {
                    title: 'Accounts',
                    href: `/workspaces/${currentWorkspace.slug}/finance/accounts`,
                    icon: Landmark,
                },
                {
                    title: 'Transactions',
                    href: `/workspaces/${currentWorkspace.slug}/finance/transactions`,
                    icon: ArrowLeftRight,
                },
                {
                    title: 'Remittances',
                    href: `/workspaces/${currentWorkspace.slug}/finance/remittances`,
                    icon: Send,
                },
            ],
        },
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
                <PublicLinks workspaceSlug={currentWorkspace.slug} />
            </SidebarContent>

            {/*<SidebarFooter>*/}
            {/*    <NavFooter items={footerNavItems} className="mt-auto" />*/}
            {/*</SidebarFooter>*/}
        </Sidebar>
    );
}

function PublicLinks({ workspaceSlug }: { workspaceSlug: string }) {
    const links = [
        {
            title: 'RMO Management',
            href: `/public/workspaces/${workspaceSlug}/rts/rmo-management`,
            icon: Truck,
        },
        { title: 'Leaderboards', href: '/leaderboards', icon: Trophy },
        { title: 'Changelog', href: '/changelog', icon: FileText },
    ];

    return (
        <SidebarGroup className="mt-auto">
            <SidebarGroupLabel className="text-[10px] font-mono font-medium uppercase tracking-[0.08em] text-gray-300 dark:text-gray-600 px-3.5 mb-2">
                Public Links
            </SidebarGroupLabel>
            <SidebarMenu className="mt-2">
                {links.map((link) => (
                    <PublicLinkItem key={link.title} {...link} />
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}

function PublicLinkItem({
    title,
    href,
    icon: Icon,
}: {
    title: string;
    href: string;
    icon: typeof Truck;
}) {
    const [copied, setCopied] = useState(false);

    const copy = async (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        const url =
            typeof window !== 'undefined' ? window.location.origin + href : href;
        try {
            await navigator.clipboard?.writeText(url);
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        } catch {
            // no-op
        }
    };

    return (
        <SidebarMenuItem>
            <SidebarMenuButton
                asChild
                tooltip={{ children: title }}
                className={[
                    'group/public relative h-9 justify-between rounded-[10px] !text-[13px]',
                    'text-gray-400 dark:text-gray-500',
                    'hover:text-gray-600 dark:hover:text-gray-400 hover:bg-black/[0.02] dark:hover:bg-white/[0.02]',
                    'transition-colors',
                ].join(' ')}
            >
                <a
                    href={href}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="flex w-full items-center justify-between gap-3"
                >
                    <div className="flex items-center gap-3">
                        <Icon className="h-4 w-4" />
                        <span>{title}</span>
                    </div>
                    <div className="flex items-center gap-1 opacity-0 transition-opacity group-hover/public:opacity-100">
                        <span
                            role="button"
                            tabIndex={0}
                            onClick={copy}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' || e.key === ' ') copy(e as unknown as React.MouseEvent);
                            }}
                            aria-label={copied ? 'Copied' : 'Copy link'}
                            className="flex h-5 w-5 cursor-pointer items-center justify-center rounded hover:bg-black/5 dark:hover:bg-white/10"
                        >
                            {copied ? (
                                <Check className="h-3 w-3 text-emerald-500" />
                            ) : (
                                <Copy className="h-3 w-3" />
                            )}
                        </span>
                        <ExternalLink className="h-3 w-3" />
                    </div>
                </a>
            </SidebarMenuButton>
        </SidebarMenuItem>
    );
}
