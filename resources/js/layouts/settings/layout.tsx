import Heading from '@/components/heading';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import { edit as editPassword } from '@/routes/password';
import { edit } from '@/routes/profile';
import { Workspace } from '@/types/models/Workspace';
import { Link, type InertiaLinkProps } from '@inertiajs/react';
import {
    Bell,
    CalendarClock,
    KeyRound,
    Server,
    User,
    type LucideIcon,
} from 'lucide-react';
import { type PropsWithChildren } from 'react';

type SettingsNavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon: LucideIcon;
};

type SettingsNavGroup = {
    label: string;
    items: SettingsNavItem[];
};

const hrefToUrl = (href: SettingsNavItem['href']) =>
    typeof href === 'string' ? href : href.url;

export default function SettingsLayout({
    children,
    workspace,
}: PropsWithChildren<{ workspace?: Workspace | null }>) {
    const canManageDiscordNotifications = usePermission(
        PERMISSIONS.ManageDiscordNotifications,
    );
    const canManageRmoSettings = usePermission(PERMISSIONS.ManageRmoSettings);

    const groups: SettingsNavGroup[] = [
        {
            label: 'Account',
            items: [
                {
                    title: 'Profile',
                    href: workspace
                        ? edit({ workspace: workspace.slug })
                        : '/settings/profile',
                    icon: User,
                },
                {
                    title: 'Password',
                    href: workspace
                        ? editPassword({ workspace: workspace.slug })
                        : '/settings/password',
                    icon: KeyRound,
                },
            ],
        },
    ];

    // Automation Configuration is workspace-scoped (each workspace integrates
    // with its own external ERP), so only surface it inside a workspace.
    if (workspace) {
        groups.push({
            label: 'Automation Configuration',
            items: [
                {
                    title: 'ERP Credentials',
                    href: `/workspaces/${workspace.slug}/settings/erp-credentials`,
                    icon: Server,
                },
            ],
        });

        if (canManageDiscordNotifications) {
            groups.push({
                label: 'Notifications',
                items: [
                    {
                        title: 'Discord Notifications',
                        href: `/workspaces/${workspace.slug}/settings/notifications`,
                        icon: Bell,
                    },
                ],
            });
        }

        if (canManageRmoSettings) {
            groups.push({
                label: 'RTS',
                items: [
                    {
                        title: 'RMO Management',
                        href: `/workspaces/${workspace.slug}/settings/rmo`,
                        icon: CalendarClock,
                    },
                ],
            });
        }
    }

    // When server-side rendering, we only render the layout on the client...
    if (typeof window === 'undefined') {
        return null;
    }

    const currentPath = window.location.pathname;

    return (
        <div className="px-4 py-6">
            <Heading
                title="Settings"
                description="Manage your account preferences and workspace integrations"
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full lg:w-60 lg:shrink-0">
                    <nav className="flex flex-col gap-5">
                        {groups.map((group) => (
                            <div
                                key={group.label}
                                className="flex flex-col gap-1"
                            >
                                <p className="px-3 pb-1 font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                    {group.label}
                                </p>
                                {group.items.map((item) => {
                                    const active =
                                        currentPath === hrefToUrl(item.href);
                                    const Icon = item.icon;
                                    return (
                                        <Link
                                            key={hrefToUrl(item.href)}
                                            href={item.href}
                                            className={cn(
                                                'flex items-center gap-2.5 rounded-[10px] px-3 py-2 text-[13px] font-medium transition-colors',
                                                active
                                                    ? 'bg-emerald-500/[0.08] text-emerald-700 dark:text-emerald-400'
                                                    : 'text-gray-600 hover:bg-black/[0.04] hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/[0.04] dark:hover:text-gray-100',
                                            )}
                                        >
                                            <Icon
                                                className={cn(
                                                    'h-4 w-4 shrink-0',
                                                    active
                                                        ? 'text-emerald-600 dark:text-emerald-400'
                                                        : 'text-gray-400 dark:text-gray-500',
                                                )}
                                            />
                                            {item.title}
                                        </Link>
                                    );
                                })}
                            </div>
                        ))}
                    </nav>
                </aside>

                <div className="mt-8 flex-1 lg:mt-0 lg:max-w-2xl">
                    <section className="max-w-xl space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
