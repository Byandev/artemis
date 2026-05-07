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
    User,
    RotateCcw,
    BarChart2,
    MapPin,
    Box,
    Layers,
    ShoppingCart,
    Truck,
    Trophy,
    Copy,
    Check,
    ExternalLink,
    Wallet,
    Landmark,
    ArrowLeftRight,
    Send,
    PieChart,
    Shield,
    MessageSquare,
} from 'lucide-react';
import { useState } from 'react';
import AppLogo from './app-logo';
import { PERMISSIONS } from '@/constants/permissions';
import { dashboard as workspaceDashboard } from '@/actions/App/Http/Controllers/Workspaces/WorkspaceController';

export function AppSidebar() {
    const { currentWorkspace } = usePage().props as unknown as {
        currentWorkspace: {
            slug: string;
            inventory_module_enabled: boolean;
            finance_module_enabled: boolean;
            products_module_enabled: boolean;
            teams_module_enabled: boolean;
            checklist_module_enabled: boolean;
            csr_module_enabled: boolean;
            rmo_module_enabled: boolean;
            leaderboard_module_enabled: boolean;
            botcake_module_enabled: boolean;
        };
    };

    const dashboardUrl = currentWorkspace
        ? workspaceDashboard(currentWorkspace.slug).url
        : dashboard().url;

    const slug = currentWorkspace?.slug ?? '';

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
            permission: PERMISSIONS.ViewShops,
        },
        {
            title: 'Pages',
            href: `/workspaces/${slug}/pages`,
            icon: BookOpenIcon,
            permission: PERMISSIONS.ViewPages,
        },
        ...(currentWorkspace.products_module_enabled
            ? [
                  {
                      title: 'Products',
                      href: `/workspaces/${slug}/products/list`,
                      icon: Package,
                      permission: PERMISSIONS.ViewProducts,
                  },
              ]
            : []),
        ...(currentWorkspace.teams_module_enabled
            ? [
                  {
                      title: 'Teams',
                      href: `/workspaces/${slug}/teams`,
                      icon: Users,
                      permission: PERMISSIONS.ViewTeams,
                  },
              ]
            : []),
        {
            title: 'Roles',
            href: `/workspaces/${slug}/roles`,
            icon: Shield,
            permission: PERMISSIONS.ViewRoles,
        },
        ...(currentWorkspace.checklist_module_enabled
            ? [
                  {
                      title: 'Checklist',
                      href: `/workspaces/${(currentWorkspace as { slug: string }).slug}/checklist`,
                      icon: ListChecks,
                  },
              ]
            : []),
        ...(currentWorkspace.botcake_module_enabled
            ? [
                  {
                      title: 'Botcake',
                      icon: MessageSquare,
                      items: [
                          {
                              title: 'Sequences',
                              href: `/workspaces/${currentWorkspace.slug}/botcake/sequences`,
                              icon: MessageSquare,
                          },
                          {
                              title: 'Flows',
                              href: `/workspaces/${currentWorkspace.slug}/botcake/flows`,
                              icon: ClipboardList,
                          },
                      ],
                  },
              ]
            : []),
        ...(currentWorkspace.csr_module_enabled
            ? [
                  {
                      title: 'CSR',
                      icon: User,
                      anyOf: [
                          PERMISSIONS.ViewCsrManagement,
                          PERMISSIONS.ViewCsrAnalytics,
                      ],
                      items: [
                          {
                              title: 'Management',
                              href: `/workspaces/${slug}/csr/management`,
                              icon: User,
                              permission: PERMISSIONS.ViewCsrManagement,
                          },
                          {
                              title: 'Analytics',
                              href: `/workspaces/${slug}/csr/analytics`,
                              icon: BarChart2,
                              permission: PERMISSIONS.ViewCsrAnalytics,
                          },
                      ],
                  },
              ]
            : []),
        {
            title: 'RTS',
            icon: RotateCcw,
            anyOf: [
                PERMISSIONS.ViewRtsAnalytics,
                PERMISSIONS.ManageParcelJourneyTemplates,
            ],
            items: [
                {
                    title: 'Analytics',
                    href: `/workspaces/${slug}/rts/analytics`,
                    icon: BarChart2,
                    permission: PERMISSIONS.ViewRtsAnalytics,
                },
                {
                    title: 'Parcel Journey',
                    href: `/workspaces/${slug}/rts/parcel-journeys`,
                    icon: MapPin,
                    permission: PERMISSIONS.ManageParcelJourneyTemplates,
                },
            ],
        },
        ...(currentWorkspace.inventory_module_enabled
            ? [
                  {
                      title: 'Inventory',
                      icon: Box,
                      anyOf: [
                          PERMISSIONS.ViewInventoryItems,
                          PERMISSIONS.ViewTransactionLogs,
                          PERMISSIONS.ViewPurchasedOrders,
                      ],
                      items: [
                          {
                              title: 'Inventory Items',
                              href: `/workspaces/${slug}/inventory/items`,
                              icon: Layers,
                              permission: PERMISSIONS.ViewInventoryItems,
                          },
                          {
                              title: 'Transaction Logs',
                              href: `/workspaces/${slug}/inventory/transactions`,
                              icon: ClipboardList,
                              permission: PERMISSIONS.ViewTransactionLogs,
                          },
                          {
                              title: 'Purchased Orders',
                              href: `/workspaces/${slug}/inventory/purchased-orders`,
                              icon: ShoppingCart,
                              permission: PERMISSIONS.ViewPurchasedOrders,
                          },
                      ],
                  },
              ]
            : []),
        ...(currentWorkspace.finance_module_enabled
            ? [
                  {
                      title: 'Finance',
                      icon: Wallet,
                      anyOf: [
                          PERMISSIONS.ViewFinanceDashboard,
                          PERMISSIONS.ViewFinanceAccounts,
                          PERMISSIONS.ViewFinanceTransactions,
                          PERMISSIONS.ViewFinanceRemittances,
                      ],
                      items: [
                          {
                              title: 'Live Cashflow',
                              href: `/workspaces/${currentWorkspace.slug}/finance/dashboard`,
                              icon: LayoutDashboard,
                              permission: PERMISSIONS.ViewFinanceDashboard,
                          },
                          {
                              title: 'Dashboard',
                              href: `/workspaces/${currentWorkspace.slug}/finance/expenses`,
                              icon: PieChart,
                              permission: PERMISSIONS.ViewFinanceDashboard,
                          },
                          {
                              title: 'Accounts',
                              href: `/workspaces/${currentWorkspace.slug}/finance/accounts`,
                              icon: Landmark,
                              permission: PERMISSIONS.ViewFinanceAccounts,
                          },
                          {
                              title: 'Transactions',
                              href: `/workspaces/${currentWorkspace.slug}/finance/transactions`,
                              icon: ArrowLeftRight,
                              permission: PERMISSIONS.ViewFinanceTransactions,
                          },
                          {
                              title: 'Remittances',
                              href: `/workspaces/${currentWorkspace.slug}/finance/remittances`,
                              icon: Send,
                              permission: PERMISSIONS.ViewFinanceRemittances,
                          },
                      ],
                  },
              ]
            : []),
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
                <PublicLinks
                    workspaceSlug={currentWorkspace.slug}
                    rmoEnabled={currentWorkspace.rmo_module_enabled}
                    leaderboardEnabled={currentWorkspace.leaderboard_module_enabled}
                />
            </SidebarContent>

            {/*<SidebarFooter>*/}
            {/*    <NavFooter items={footerNavItems} className="mt-auto" />*/}
            {/*</SidebarFooter>*/}
        </Sidebar>
    );
}

function PublicLinks({
    workspaceSlug,
    rmoEnabled,
    leaderboardEnabled,
}: {
    workspaceSlug: string;
    rmoEnabled: boolean;
    leaderboardEnabled: boolean;
}) {
    const links = [
        ...(rmoEnabled
            ? [
                {
                    title: 'RMO Management',
                    href: `/public/workspaces/${workspaceSlug}/rts/rmo-management`,
                    icon: Truck,
                },
            ]
            : []),
        ...(leaderboardEnabled
            ? [{ title: 'Leaderboards', href: '/leaderboards', icon: Trophy }]
            : []),
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
                    'group/public relative h-9 justify-between rounded-[10px] text-[13px]!',
                    'text-gray-400 dark:text-gray-500',
                    'hover:text-gray-600 dark:hover:text-gray-400 hover:bg-black/2 dark:hover:bg-white/2',
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
