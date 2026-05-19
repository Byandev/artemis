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
import { getWorkspaceMainNavItems } from '@/lib/workspace-navigation';
import { type NavItem, User as UserType } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Link, usePage } from '@inertiajs/react';
import {
    Check,
    Copy,
    ExternalLink,
    LifeBuoy,
    Trophy,
    Truck,
} from 'lucide-react';
import { useState } from 'react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { currentWorkspace, auth } = usePage().props as unknown as {
        currentWorkspace: Workspace;
        auth?: { user: UserType };
    };

    const slug = currentWorkspace?.slug ?? '';
    const mainNavItems = getWorkspaceMainNavItems(currentWorkspace);

    const adminNavItems: NavItem[] = auth?.user?.can?.viewAnySupportTickets
        ? [
              {
                  title: 'Support Tickets',
                  href: `/workspaces/${slug}/admin/support-tickets`,
                  icon: LifeBuoy,
              },
          ]
        : [];

    const supportNavItems: NavItem[] = auth?.user?.can?.viewAnySupportTickets
        ? adminNavItems
        : [
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
                            <Link
                                href={
                                    slug
                                        ? `/workspaces/${slug}/dashboard`
                                        : '/dashboard'
                                }
                            >
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="p-3">
                <NavMain items={mainNavItems} group_label="Main" />
                <NavMain items={adminNavItems} group_label="Admin" />
                {/*<NavMain items={accountNavItems} group_label="Account" />*/}
                <PublicLinks
                    workspaceSlug={currentWorkspace.slug}
                    rmoEnabled={currentWorkspace.rmo_module_enabled}
                    leaderboardEnabled={
                        currentWorkspace.leaderboard_module_enabled
                    }
                />
                <div className="mt-auto">
                    <NavMain items={supportNavItems} group_label="Support" />
                </div>
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
