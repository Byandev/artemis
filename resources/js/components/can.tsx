import { ReactNode } from 'react';
import { PermissionInput, useAnyPermission, usePermission } from '@/hooks/use-permission';

interface CanProps {
    permission?: PermissionInput;
    anyOf?: PermissionInput;
    fallback?: ReactNode;
    children: ReactNode;
}

export function Can({ permission, anyOf, fallback = null, children }: CanProps) {
    const allowedAll = usePermission(permission ?? []);
    const allowedAny = useAnyPermission(anyOf ?? []);

    const allowed = (permission ? allowedAll : true) && (anyOf ? allowedAny : true);

    return <>{allowed ? children : fallback}</>;
}
