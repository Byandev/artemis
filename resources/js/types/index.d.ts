import type { PermissionName } from '@/constants/permissions';
import { InertiaLinkProps } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href?: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    items?: NavItem[];
    permission?: PermissionName | PermissionName[];
    anyOf?: PermissionName | PermissionName[];
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    is_super_admin?: boolean;
    is_workspace_owner?: boolean;
    is_workspace_admin?: boolean;
    is_csr?: boolean;
    permissions?: (PermissionName | '*')[];
    can?: {
        viewAnySupportTickets?: boolean;
        viewActivityLogs?: boolean;
        [key: string]: boolean | undefined;
    };
    [key: string]: unknown; // This allows for additional properties...
    pivot?: {
        role_id: number | null;
        role: string | null;
        department_id: number | null;
        department: string | null;
        created_at: string;
    };
}

export interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLinks[];
    from: number;
    to: number;
}

export interface RequestParams {
    [key: string]: string | number | null;
}
