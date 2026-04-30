import { usePage } from '@inertiajs/react';
import type { SharedData } from '@/types';
import type { PermissionName } from '@/constants/permissions';

export type PermissionInput = PermissionName | PermissionName[];

function normalize(input: PermissionInput): PermissionName[] {
    return Array.isArray(input) ? input : [input];
}

export function useUserPermissions(): string[] {
    const { auth } = usePage<SharedData>().props;
    return auth?.user?.permissions ?? [];
}

export function hasPermission(perms: string[], required: PermissionInput): boolean {
    if (perms.includes('*')) return true;
    const needed = normalize(required);
    if (needed.length === 0) return true;
    return needed.every((p) => perms.includes(p));
}

export function hasAnyPermission(perms: string[], required: PermissionInput): boolean {
    if (perms.includes('*')) return true;
    const needed = normalize(required);
    if (needed.length === 0) return true;
    return needed.some((p) => perms.includes(p));
}

export function usePermission(required: PermissionInput): boolean {
    return hasPermission(useUserPermissions(), required);
}

export function useAnyPermission(required: PermissionInput): boolean {
    return hasAnyPermission(useUserPermissions(), required);
}
