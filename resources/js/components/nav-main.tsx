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
                <SidebarGroupLabel className="text-xs tracking-wide text-gray-400 uppercase">
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
                                                'relative h-11 justify-between rounded-md',
                                                'text-gray-500 hover:bg-emerald-100 hover:text-emerald-500',
                                                'border-l-4 border-transparent',
                                                childActive
                                                    ? ' border-emerald-600 bg-emerald-50 font-medium text-emerald-600 transition-all'
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
                                                                'h-9 rounded-md text-gray-500 hover:bg-emerald-100 hover:text-emerald-500',
                                                                subActive
                                                                    ? 'bg-emerald-50 font-medium text-emerald-600'
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
                                    'relative h-11 justify-start rounded-md',
                                    'text-gray-500 hover:bg-emerald-100 hover:text-emerald-500',
                                    'border-l-4 border-transparent',
                                    active
                                        ? 'ml-1 border-emerald-600 bg-emerald-50 font-medium text-emerald-600 transition-all'
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