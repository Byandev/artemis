import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const page = usePage();
    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Platform</SidebarGroupLabel>
            <ul>
                {items.map((item) => (
                    <li key={item.title}>
                        <Link
                            href={item.href} prefetch
                            className={`group menu-item ${page.url.startsWith(
                                typeof item.href === 'string'
                                    ? item.href
                                    : item.href.url,
                            ) ? 'menu-item-active': 'menu-item-inactive'}`}
                        >
                                {item.icon && (
                                    <item.icon
                                        className={'menu-item-icon-size'}
                                    />
                                )}
                                <span className="menu-item-text">
                                    {item.title}
                                </span>
                        </Link>
                    </li>
                ))}
            </ul>
        </SidebarGroup>
    );
}
