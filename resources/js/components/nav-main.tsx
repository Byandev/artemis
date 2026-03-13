import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

type NavMainProps = {
    items?: NavItem[];
    group_label?: string;
};

export function NavMain({ items = [], group_label = '' }: NavMainProps) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className=" ">
            {group_label && (
                <SidebarGroupLabel className="text-xs tracking-wide  text-gray-400 uppercase">
                    {group_label}
                </SidebarGroupLabel>
            )}

            <SidebarMenu className="mt-2">
                {items.map((item) => {
                    const active = isCurrentUrl(item.href);

                    return (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                size="lg"
                                isActive={active}
                                tooltip={{ children: item.title }}
                                className={[
                                    'relative h-11 justify-start rounded-md ',
                                    'text-gray-500 hover:bg-emerald-100 hover:text-emerald-500ra',
                                    'border-l-4 border-transparent',
                                    active
                                        ? 'ml-1 border-emerald-600 bg-emerald-50 font-medium text-emerald-500 transition-all'
                                        : '',
                                ].join(' ')}
                            >
                                <Link
                                    href={item.href}
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
