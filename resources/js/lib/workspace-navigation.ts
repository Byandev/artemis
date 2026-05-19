import { PERMISSIONS } from '@/constants/permissions';
import { hasAnyPermission, hasPermission } from '@/hooks/use-permission';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import {
    ArrowLeftRight,
    BarChart2,
    BookOpenIcon,
    Box,
    ClipboardList,
    Landmark,
    Layers,
    LayoutDashboard,
    ListChecks,
    MapPin,
    MessageSquare,
    Package,
    PieChart,
    RotateCcw,
    Send,
    Shield,
    ShoppingCart,
    Store,
    User,
    Users,
    Wallet,
} from 'lucide-react';

export type SupportDestination = {
    label: string;
    value: string;
    group: string;
};

function isAllowed(item: NavItem, perms: string[]): boolean {
    if (item.permission && !hasPermission(perms, item.permission)) return false;
    if (item.anyOf && !hasAnyPermission(perms, item.anyOf)) return false;

    return true;
}

export function filterNavItems(items: NavItem[], perms: string[]): NavItem[] {
    return items
        .filter((item) => isAllowed(item, perms))
        .map((item) => {
            if (!item.items?.length) return item;

            const visibleChildren = filterNavItems(item.items, perms);
            if (visibleChildren.length === 0 && !item.href) return null;

            return { ...item, items: visibleChildren };
        })
        .filter((item): item is NavItem => item !== null);
}

export function getWorkspaceMainNavItems(workspace?: Workspace): NavItem[] {
    const slug = workspace?.slug ?? '';
    const dashboardUrl = workspace
        ? `/workspaces/${slug}/dashboard`
        : dashboard().url;

    return [
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
        {
            title: 'Products',
            href: `/workspaces/${slug}/products/list`,
            icon: Package,
            permission: PERMISSIONS.ViewProducts,
        },
        ...(workspace?.teams_module_enabled
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
        ...(workspace?.checklist_module_enabled
            ? [
                  {
                      title: 'Checklist',
                      href: `/workspaces/${slug}/checklist`,
                      icon: ListChecks,
                      permission: PERMISSIONS.ViewChecklist,
                  },
              ]
            : []),
        ...(workspace?.botcake_module_enabled
            ? [
                  {
                      title: 'Botcake',
                      icon: MessageSquare,
                      items: [
                          {
                              title: 'Sequences',
                              href: `/workspaces/${slug}/botcake/sequences`,
                              icon: MessageSquare,
                          },
                          {
                              title: 'Flows',
                              href: `/workspaces/${slug}/botcake/flows`,
                              icon: ClipboardList,
                          },
                      ],
                  },
              ]
            : []),
        ...(workspace?.csr_module_enabled
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
        ...(workspace?.inventory_module_enabled
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
        ...(workspace?.finance_module_enabled
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
                              href: `/workspaces/${slug}/finance/dashboard`,
                              icon: LayoutDashboard,
                              permission: PERMISSIONS.ViewFinanceDashboard,
                          },
                          {
                              title: 'Dashboard',
                              href: `/workspaces/${slug}/finance/expenses`,
                              icon: PieChart,
                              permission: PERMISSIONS.ViewFinanceDashboard,
                          },
                          {
                              title: 'Accounts',
                              href: `/workspaces/${slug}/finance/accounts`,
                              icon: Landmark,
                              permission: PERMISSIONS.ViewFinanceAccounts,
                          },
                          {
                              title: 'Transactions',
                              href: `/workspaces/${slug}/finance/transactions`,
                              icon: ArrowLeftRight,
                              permission: PERMISSIONS.ViewFinanceTransactions,
                          },
                          {
                              title: 'Remittances',
                              href: `/workspaces/${slug}/finance/remittances`,
                              icon: Send,
                              permission: PERMISSIONS.ViewFinanceRemittances,
                          },
                      ],
                  },
              ]
            : []),
    ];
}

export function getSupportDestinations(
    workspace: Workspace,
    perms: string[],
    canViewAnySupportTickets = false,
): SupportDestination[] {
    const destinations: SupportDestination[] = [
        {
            label: 'In General',
            value: 'general',
            group: 'General',
        },
    ];

    const addItem = (item: NavItem, group: string) => {
        if (item.href) {
            destinations.push({
                label: item.title,
                value: String(item.href),
                group,
            });
        }

        item.items?.forEach((child) => addItem(child, item.title));
    };

    filterNavItems(getWorkspaceMainNavItems(workspace), perms).forEach((item) =>
        addItem(item, 'Main'),
    );

    destinations.push(
        {
            label: 'Profile',
            value: `/workspaces/${workspace.slug}/settings/profile`,
            group: 'Settings',
        },
        {
            label: 'Password',
            value: `/workspaces/${workspace.slug}/settings/password`,
            group: 'Settings',
        },
        {
            label: 'Appearance',
            value: `/workspaces/${workspace.slug}/settings/appearance`,
            group: 'Settings',
        },
        {
            label: 'Two-factor Authentication',
            value: `/workspaces/${workspace.slug}/settings/two-factor`,
            group: 'Settings',
        },
    );

    if (hasPermission(perms, PERMISSIONS.ManageApiKeys)) {
        destinations.push({
            label: 'API Keys',
            value: `/workspaces/${workspace.slug}/api-keys`,
            group: 'Settings',
        });
    }

    if (canViewAnySupportTickets) {
        destinations.push({
            label: 'Support Tickets',
            value: `/workspaces/${workspace.slug}/admin/support-tickets`,
            group: 'Admin',
        });
    } else {
        destinations.push({
            label: 'Customer Support',
            value: `/workspaces/${workspace.slug}/support`,
            group: 'Support',
        });
    }

    if (workspace.rmo_module_enabled) {
        destinations.push({
            label: 'RMO Management',
            value: `/public/workspaces/${workspace.slug}/rts/rmo-management`,
            group: 'Public Links',
        });
    }

    if (workspace.leaderboard_module_enabled) {
        destinations.push({
            label: 'Leaderboards',
            value: '/leaderboards',
            group: 'Public Links',
        });
    }

    return destinations;
}
