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
import { PERMISSIONS } from '@/constants/permissions';
import { useAnyPermission } from '@/hooks/use-permission';
import { dashboard } from '@/routes';
import { type NavItem, User as UserType } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowLeftRight,
    BarChart2,
    BookOpenIcon,
    Box,
    Check,
    Clapperboard,
    ClipboardList,
    Copy,
    Database,
    ExternalLink,
    Facebook,
    History,
    Landmark,
    Layers,
    LayoutDashboard,
    LifeBuoy,
    ListChecks,
    MapPin,
    Megaphone,
    MessageSquare,
    Package,
    PieChart,
    RotateCcw,
    Send,
    Shield,
    ShoppingCart,
    SlidersHorizontal,
    Sparkles,
    Store,
    Trophy,
    Truck,
    User,
    Users,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { currentWorkspace, auth } = usePage().props as unknown as {
        currentWorkspace: Workspace;
        auth?: { user: UserType };
    };

    const slug = currentWorkspace?.slug ?? '';

    const dashboardUrl = currentWorkspace
        ? `/workspaces/${slug}/dashboard`
        : dashboard().url;

    const mainNavItems: NavItem[] = [
        {
            title: 'Main Dashboard',
            href: dashboardUrl,
            icon: LayoutDashboard,
            permission: PERMISSIONS.ViewMainDashboard,
        },
        ...(currentWorkspace.sales_marketing_dashboard_module_enabled
            ? [
                  {
                      title: 'S&M Dashboard',
                      href: `/workspaces/${slug}/sales-marketing/dashboard`,
                      icon: Megaphone,
                      permission: PERMISSIONS.ViewSalesMarketingDashboard,
                  },
              ]
            : []),
        ...(currentWorkspace.csr_dashboard_module_enabled
            ? [
                  {
                      title: 'CSR Dashboard',
                      href: `/workspaces/${slug}/csr/dashboard`,
                      icon: User,
                      permission: PERMISSIONS.ViewCsrDashboard,
                  },
              ]
            : []),
        ...(currentWorkspace.video_editor_dashboard_module_enabled
            ? [
                  {
                      title: 'Video Editor Dashboard',
                      href: `/workspaces/${slug}/video-editor/dashboard`,
                      icon: Clapperboard,
                      permission: PERMISSIONS.ViewVideoEditorDashboard,
                  },
              ]
            : []),
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
                      permission: PERMISSIONS.ViewChecklist,
                  },
              ]
            : []),
        ...(currentWorkspace.botcake_module_enabled
            ? [
                  {
                      title: 'Botcake',
                      icon: MessageSquare,
                      anyOf: [
                          PERMISSIONS.ViewBotcakeSequences,
                          PERMISSIONS.ViewBotcakeSequenceMessages,
                          PERMISSIONS.ViewBotcakeFlows,
                      ],
                      items: [
                          {
                              title: 'Sequences',
                              href: `/workspaces/${currentWorkspace.slug}/botcake/sequences`,
                              icon: MessageSquare,
                              permission: PERMISSIONS.ViewBotcakeSequences,
                          },
                          {
                              title: 'Sequence Messages',
                              href: `/workspaces/${currentWorkspace.slug}/botcake/sequence-messages`,
                              icon: Send,
                              permission:
                                  PERMISSIONS.ViewBotcakeSequenceMessages,
                          },
                          {
                              title: 'Flows',
                              href: `/workspaces/${currentWorkspace.slug}/botcake/flows`,
                              icon: ClipboardList,
                              permission: PERMISSIONS.ViewBotcakeFlows,
                          },
                      ],
                  },
              ]
            : []),
        ...(currentWorkspace.meta_ads_module_enabled
            ? [
                  {
                      title: 'Meta Ads',
                      icon: Megaphone,
                      anyOf: [
                          PERMISSIONS.ViewMetaAds,
                          PERMISSIONS.ViewOptimizationRules,
                          PERMISSIONS.ApproveOptimizationRules,
                      ],
                      items: [
                          {
                              title: 'FB Account',
                              href: `/workspaces/${slug}/integrations/meta`,
                              icon: Facebook,
                              permission: PERMISSIONS.ViewMetaAds,
                          },
                          {
                              title: 'Ad Accounts',
                              href: `/workspaces/${slug}/integrations/meta/ad-accounts`,
                              icon: Database,
                              permission: PERMISSIONS.ViewMetaAds,
                          },
                          {
                              title: 'Ads Manager',
                              href: `/workspaces/${slug}/integrations/meta/ads-manager`,
                              icon: BarChart2,
                              permission: PERMISSIONS.ViewMetaAds,
                          },
                          {
                              title: 'Ad Spent Tracker',
                              href: `/workspaces/${slug}/integrations/meta/budget-tracker`,
                              icon: Wallet,
                              permission: PERMISSIONS.ViewMetaAds,
                          },
                          {
                              title: 'Optimization Rules',
                              href: `/workspaces/${slug}/integrations/meta/optimization-rules`,
                              icon: SlidersHorizontal,
                              permission: PERMISSIONS.ViewOptimizationRules,
                          },
                          {
                              title: 'Rule Approvals',
                              href: `/workspaces/${slug}/integrations/meta/optimization-rules/approvals`,
                              icon: ListChecks,
                              permission: PERMISSIONS.ApproveOptimizationRules,
                          },
                          {
                              title: 'Optimization Logs',
                              href: `/workspaces/${slug}/integrations/meta/optimization-rules/logs`,
                              icon: History,
                              permission: PERMISSIONS.ViewOptimizationRules,
                          },
                          {
                              title: 'Sync Health',
                              href: `/workspaces/${slug}/integrations/meta/health`,
                              icon: Activity,
                              permission: PERMISSIONS.ViewMetaAds,
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
                PERMISSIONS.ViewParcelJourneyTemplates,
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
                    anyOf: [
                        PERMISSIONS.ViewParcelJourneyTemplates,
                        PERMISSIONS.ManageParcelJourneyTemplates,
                    ],
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
        ...(currentWorkspace.creatives_module_enabled
            ? [
                  {
                      title: 'Creatives',
                      href: `/workspaces/${slug}/creatives`,
                      icon: Sparkles,
                      permission: PERMISSIONS.ViewCreatives,
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

    const adminNavItems: NavItem[] = auth?.user?.can?.viewAnySupportTickets
        ? [
              {
                  title: 'Support Tickets',
                  href: `/workspaces/${slug}/admin/support-tickets`,
                  icon: LifeBuoy,
              },
          ]
        : [];

    const supportNavItems: NavItem[] = [
        {
            title: 'Customer Support',
            href: `/workspaces/${slug}/support`,
            icon: LifeBuoy,
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
                <PublicLinks
                    workspaceSlug={currentWorkspace.slug}
                    rmoEnabled={currentWorkspace.rmo_module_enabled}
                    leaderboardEnabled={
                        currentWorkspace.leaderboard_module_enabled
                    }
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
    const canViewRmoLink = useAnyPermission([
        PERMISSIONS.ViewRtsAnalytics,
        PERMISSIONS.ViewCsrManagement,
    ]);
    const canViewLeaderboardLink = useAnyPermission(
        PERMISSIONS.ViewCsrAnalytics,
    );

    const links = [
        ...(rmoEnabled && canViewRmoLink
            ? [
                  {
                      title: 'RMO Management',
                      href: `/public/workspaces/${workspaceSlug}/rts/rmo-management`,
                      icon: Truck,
                  },
              ]
            : []),
        ...(leaderboardEnabled && canViewLeaderboardLink
            ? [
                  {
                      title: 'Leaderboards',
                      href: `/public/workspaces/${workspaceSlug}/leaderboards`,
                      icon: Trophy,
                  },
              ]
            : []),
    ];

    if (links.length === 0) return null;

    return (
        <SidebarGroup>
            <SidebarGroupLabel className="mb-2 px-3.5 font-mono text-[10px] font-medium tracking-[0.08em] text-gray-300 uppercase dark:text-gray-600">
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
            typeof window !== 'undefined'
                ? window.location.origin + href
                : href;
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
                    'hover:bg-black/2 hover:text-gray-600 dark:hover:bg-white/2 dark:hover:text-gray-400',
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
                                if (e.key === 'Enter' || e.key === ' ')
                                    copy(e as unknown as React.MouseEvent);
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
