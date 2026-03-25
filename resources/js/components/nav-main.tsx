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
import type { NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';

type NavMainProps = {
    items?: NavItem[];
    group_label?: string;
};

export function NavMain({ items = [], group_label = '' }: NavMainProps) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <SidebarGroup>
            {group_label && (
                <SidebarGroupLabel className="text-[10px] font-mono font-medium uppercase tracking-[0.08em] text-gray-300 dark:text-gray-600 px-3.5 mb-2">
                    {group_label}
                </SidebarGroupLabel>
            )}

            <SidebarMenu className="mt-2">
                {items.map((item) => {
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
                                            size="lg"
                                            isActive={childActive}
                                            tooltip={{ children: item.title }}
                                            className={[
                                                'relative h-9 justify-between rounded-[10px] text-[13px]',
                                                'text-gray-400 dark:text-gray-500',
                                                'hover:text-gray-600 dark:hover:text-gray-400 hover:bg-black/[0.02] dark:hover:bg-white/[0.02]',
                                                'transition-colors',
                                                childActive
                                                    ? 'bg-emerald-500/[0.08] text-emerald-600 dark:text-emerald-400 font-medium hover:bg-emerald-500/[0.08]'
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
                                                                'hover:text-gray-600 dark:hover:text-gray-400 hover:bg-black/[0.02] dark:hover:bg-white/[0.02]',
                                                                'transition-colors',
                                                                subActive
                                                                    ? 'bg-emerald-500/[0.08] text-emerald-600 dark:text-emerald-400 font-medium hover:bg-emerald-500/[0.08]'
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
                                size="lg"
                                isActive={active}
                                tooltip={{ children: item.title }}
                                className={[
                                    'relative h-9 justify-start rounded-[10px] text-[13px]',
                                    'text-gray-400 dark:text-gray-500',
                                    'hover:text-gray-600 dark:hover:text-gray-400 hover:bg-black/[0.02] dark:hover:bg-white/[0.02]',
                                    'transition-colors',
                                    active
                                        ? 'bg-emerald-500/[0.08] text-emerald-600 dark:text-emerald-400 font-medium hover:bg-emerald-500/[0.08]'
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