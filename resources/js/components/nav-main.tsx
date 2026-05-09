import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import {
    hasAnyPermission,
    hasPermission,
    useUserPermissions,
} from '@/hooks/use-permission';
import type { NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';

function isAllowed(item: NavItem, perms: string[]): boolean {
    if (item.permission && !hasPermission(perms, item.permission)) return false;
    if (item.anyOf && !hasAnyPermission(perms, item.anyOf)) return false;
    return true;
}

function filterNav(items: NavItem[], perms: string[]): NavItem[] {
    return items
        .filter((item) => isAllowed(item, perms))
        .map((item) => {
            if (!item.items?.length) return item;
            const visibleChildren = filterNav(item.items, perms);
            if (visibleChildren.length === 0 && !item.href) return null;
            return { ...item, items: visibleChildren };
        })
        .filter((item): item is NavItem => item !== null);
}

type NavMainProps = {
    items?: NavItem[];
    group_label?: string;
};

export function NavMain({ items = [], group_label = '' }: NavMainProps) {
    const { isCurrentUrl } = useCurrentUrl();
    const perms = useUserPermissions();
    const visibleItems = filterNav(items, perms);

    if (visibleItems.length === 0) return null;

    return (
        <SidebarGroup>
            {group_label && (
                <SidebarGroupLabel className="mb-2 px-3.5 font-mono text-[10px] font-medium tracking-[0.08em] text-gray-300 uppercase dark:text-gray-600">
                    {group_label}
                </SidebarGroupLabel>
            )}

            <SidebarMenu className="mt-2">
                {visibleItems.map((item) => {
                    const hasChildren = !!item.items?.length;
                    const active = item.href ? isCurrentUrl(item.href) : false;
                    const childActive = item.items?.some((sub) =>
                        sub.href ? isCurrentUrl(sub.href) : false,
                    );

                    if (hasChildren) {
                        return (
                            <SidebarMenuItem key={item.title}>
                                <Collapsible defaultOpen={childActive}>
                                    <CollapsibleTrigger asChild>
                                        <SidebarMenuButton
                                            isActive={childActive}
                                            tooltip={{ children: item.title }}
                                            className={[
                                                'relative h-9 justify-between rounded-[10px] !text-[13px]',
                                                'text-gray-400 dark:text-gray-500',
                                                'hover:bg-black/[0.02] hover:text-gray-600 dark:hover:bg-white/[0.02] dark:hover:text-gray-400',
                                                'transition-colors',
                                                childActive
                                                    ? '!bg-emerald-500/[0.08] font-medium !text-emerald-600 dark:!bg-emerald-500/[0.10] dark:!text-emerald-400'
                                                    : '',
                                            ].join(' ')}
                                        >
                                            <div className="flex items-center gap-3">
                                                {item.icon && (
                                                    <item.icon className="h-4 w-4" />
                                                )}
                                                <span>{item.title}</span>
                                            </div>
                                            <ChevronDown className="h-4 w-4 shrink-0" />
                                        </SidebarMenuButton>
                                    </CollapsibleTrigger>

                                    <CollapsibleContent>
                                        <SidebarMenuSub className="mt-1">
                                            {item.items?.map((sub) => {
                                                const subActive = sub.href
                                                    ? isCurrentUrl(sub.href)
                                                    : false;

                                                return (
                                                    <SidebarMenuSubItem
                                                        key={sub.title}
                                                    >
                                                        <SidebarMenuSubButton
                                                            asChild
                                                            isActive={subActive}
                                                            className={[
                                                                'h-8 rounded-[10px] text-[12px] text-gray-400 dark:text-gray-500',
                                                                'hover:bg-black/[0.02] hover:text-gray-600 dark:hover:bg-white/[0.02] dark:hover:text-gray-400',
                                                                'transition-colors',
                                                                subActive
                                                                    ? '!bg-emerald-500/[0.08] font-medium !text-emerald-600 dark:!bg-emerald-500/[0.10] dark:!text-emerald-400'
                                                                    : '',
                                                            ].join(' ')}
                                                        >
                                                            <Link
                                                                href={sub.href!}
                                                                prefetch
                                                                className="flex items-center gap-2"
                                                            >
                                                                <span>
                                                                    {sub.title}
                                                                </span>
                                                            </Link>
                                                        </SidebarMenuSubButton>
                                                    </SidebarMenuSubItem>
                                                );
                                            })}
                                        </SidebarMenuSub>
                                    </CollapsibleContent>
                                </Collapsible>
                            </SidebarMenuItem>
                        );
                    }

                    return (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={active}
                                tooltip={{ children: item.title }}
                                className={[
                                    'relative h-9 justify-start rounded-[10px] !text-[13px]',
                                    'text-gray-400 dark:text-gray-500',
                                    'hover:bg-black/[0.02] hover:text-gray-600 dark:hover:bg-white/[0.02] dark:hover:text-gray-400',
                                    'transition-colors',
                                    active
                                        ? '!bg-emerald-500/[0.08] font-medium !text-emerald-600 dark:!bg-emerald-500/[0.10] dark:!text-emerald-400'
                                        : '',
                                ].join(' ')}
                            >
                                <Link
                                    href={item.href!}
                                    prefetch
                                    className="flex items-center gap-3"
                                >
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
