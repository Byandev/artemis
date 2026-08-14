import PageHeader from '@/components/common/PageHeader';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import AdminSidebarLayout from '@/layouts/admin/admin-sidebar-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData, SharedData, User } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import axios from 'axios';
import { omit } from 'lodash';
import debounce from 'lodash/debounce';
import { Check, Copy, Search, ShieldCheck, ShieldOff } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast, Toaster } from 'sonner';

interface WorkspaceRef {
    id: number;
    name: string;
    slug: string;
    is_owner: boolean;
}

type AdminUser = User & { workspaces?: WorkspaceRef[] };

interface Props {
    users: PaginatedData<AdminUser>;
    filters?: {
        search?: string | null;
    };
    query?: {
        sort?: string | null;
        per_page?: number | string;
        page?: number | string;
    };
}

export default function AdminUsersIndex({ users, filters, query }: Props) {
    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );
    const [search, setSearch] = useState(filters?.search ?? '');
    const [copiedUserId, setCopiedUserId] = useState<number | null>(null);
    const [sendingUserId, setSendingUserId] = useState<number | null>(null);

    // Held until the dialog is confirmed — nothing is sent on the click itself.
    const [pendingUser, setPendingUser] = useState<AdminUser | null>(null);
    const [saving, setSaving] = useState(false);

    const currentUserId = usePage<SharedData>().props.auth?.user?.id;

    const fetchUsers = useCallback(
        (overrides: Record<string, string | number | undefined> = {}) => {
            router.get(
                '/admin/users',
                {
                    sort: query?.sort ?? undefined,
                    page: 1,
                    per_page: query?.per_page,
                    'filter[search]': search || undefined,
                    ...overrides,
                },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        },
        [query?.per_page, query?.sort, search],
    );

    const debouncedSearch = useMemo(
        () => debounce(() => fetchUsers(), 400),
        [fetchUsers],
    );

    useEffect(() => {
        if (search !== (filters?.search ?? '')) {
            debouncedSearch();
        }

        return () => debouncedSearch.cancel();
    }, [debouncedSearch, filters?.search, search]);

    const copyResetPasswordUrl = async (user: AdminUser) => {
        setSendingUserId(user.id);
        try {
            const res = await axios.post(
                `/admin/users/${user.id}/reset-password`,
            );
            await navigator.clipboard.writeText(res.data.url);
            setCopiedUserId(user.id);
            toast.success(`Reset link copied for ${user.name}.`);
            setTimeout(() => setCopiedUserId(null), 2000);
        } catch (error) {
            console.error('Failed to generate reset link:', error);
            toast.error('Unable to generate reset link.');
        } finally {
            setSendingUserId(null);
        }
    };

    const confirmSuperAdmin = () => {
        if (!pendingUser) return;

        const granting = !pendingUser.is_super_admin;
        const name = pendingUser.name;

        setSaving(true);
        router.patch(
            `/admin/users/${pendingUser.id}/super-admin`,
            { is_super_admin: granting },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(
                        granting
                            ? `${name} is now a super admin.`
                            : `Super admin access revoked from ${name}.`,
                    );
                    setPendingUser(null);
                },
                onError: (errors) =>
                    toast.error(
                        errors.is_super_admin ??
                            'Unable to change super admin access.',
                    ),
                onFinish: () => setSaving(false),
            },
        );
    };

    const columns: ColumnDef<AdminUser>[] = [
        {
            accessorKey: 'name',
            header: ({ column }) => (
                <SortableHeader column={column} title="Name" />
            ),
            cell: ({ row }) => (
                <span className="font-medium text-gray-800 dark:text-gray-100">
                    {row.original.name}
                </span>
            ),
        },
        {
            accessorKey: 'email',
            header: ({ column }) => (
                <SortableHeader column={column} title="Email" />
            ),
            cell: ({ row }) => (
                <span className="text-gray-600 dark:text-gray-300">
                    {row.original.email}
                </span>
            ),
        },
        {
            id: 'workspaces',
            header: () => (
                <p className="font-mono text-[10px] font-medium tracking-wider text-gray-300 uppercase dark:text-gray-600">
                    Workspaces
                </p>
            ),
            cell: ({ row }) => {
                const workspaces = row.original.workspaces ?? [];

                if (workspaces.length === 0) {
                    return <span className="text-[12px] text-gray-400">—</span>;
                }

                return (
                    <div className="flex flex-wrap gap-1">
                        {workspaces.map((ws) => (
                            <Badge
                                key={ws.id}
                                variant="outline"
                                className="font-normal"
                                title={`/${ws.slug}`}
                            >
                                {ws.name}
                                {ws.is_owner && (
                                    <span className="ml-1 text-[10px] text-emerald-600">
                                        owner
                                    </span>
                                )}
                            </Badge>
                        ))}
                    </div>
                );
            },
        },
        {
            id: 'role',
            header: () => (
                <p className="font-mono text-[10px] font-medium tracking-wider text-gray-300 uppercase dark:text-gray-600">
                    Role
                </p>
            ),
            cell: ({ row }) =>
                row.original.is_super_admin ? (
                    <Badge className="border-violet-200/60 bg-violet-50 text-violet-700">
                        Super Admin
                    </Badge>
                ) : (
                    <Badge variant="outline">User</Badge>
                ),
        },
        {
            accessorKey: 'created_at',
            header: ({ column }) => (
                <SortableHeader column={column} title="Joined" />
            ),
            cell: ({ row }) =>
                new Date(row.original.created_at).toLocaleDateString(),
        },
        {
            id: 'actions',
            header: () => (
                <p className="text-right font-mono text-[10px] font-medium tracking-wider text-gray-300 uppercase dark:text-gray-600">
                    Actions
                </p>
            ),
            cell: ({ row }) => {
                const user = row.original;
                const copied = copiedUserId === user.id;
                const isSelf = user.id === currentUserId;

                return (
                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-8"
                            // Revoking your own access would lock you out of
                            // this page, so it isn't offered.
                            disabled={isSelf && user.is_super_admin}
                            title={
                                isSelf && user.is_super_admin
                                    ? 'You cannot revoke your own super admin access'
                                    : undefined
                            }
                            onClick={() => setPendingUser(user)}
                        >
                            {user.is_super_admin ? (
                                <>
                                    <ShieldOff className="mr-1.5 h-3.5 w-3.5" />
                                    Revoke super admin
                                </>
                            ) : (
                                <>
                                    <ShieldCheck className="mr-1.5 h-3.5 w-3.5 text-violet-600" />
                                    Make super admin
                                </>
                            )}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-8"
                            disabled={sendingUserId === user.id}
                            onClick={() => copyResetPasswordUrl(user)}
                        >
                            {copied ? (
                                <>
                                    <Check className="mr-1.5 h-3.5 w-3.5 text-emerald-600" />
                                    Copied
                                </>
                            ) : (
                                <>
                                    <Copy className="mr-1.5 h-3.5 w-3.5" />
                                    {sendingUserId === user.id
                                        ? 'Generating...'
                                        : 'Copy reset link'}
                                </>
                            )}
                        </Button>
                    </div>
                );
            },
        },
    ];

    return (
        <AdminSidebarLayout>
            <Head title="Admin | Users" />
            <Toaster position="top-right" richColors closeButton />

            <div className="p-4 md:p-6">
                <PageHeader
                    title="Users"
                    description="Manage users across all workspaces and generate password reset links."
                >
                    <div className="relative w-full sm:w-72">
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                        <input
                            type="text"
                            placeholder="Search users..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full rounded-md border border-zinc-200 bg-white py-2 pr-4 pl-10 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-800 dark:bg-zinc-950"
                        />
                    </div>
                </PageHeader>

                <div className="mt-6 rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={users.data || []}
                        enableInternalPagination={false}
                        initialSorting={initialSorting}
                        meta={{ ...omit(users, ['data']) }}
                        onFetch={(params) => {
                            const sortStr =
                                params?.sort && params.sort !== null
                                    ? String(params.sort)
                                    : null;

                            fetchUsers({
                                sort: sortStr ?? undefined,
                                page: Number(params?.page ?? 1),
                                per_page: Number(
                                    params?.per_page ??
                                        query?.per_page ??
                                        users.per_page,
                                ),
                            });
                        }}
                    />
                </div>
            </div>

            <AlertDialog
                open={!!pendingUser}
                onOpenChange={(open) => !open && setPendingUser(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {pendingUser?.is_super_admin
                                ? 'Revoke super admin access?'
                                : 'Grant super admin access?'}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {pendingUser?.is_super_admin ? (
                                <>
                                    <span className="font-medium">
                                        {pendingUser?.name}
                                    </span>{' '}
                                    ({pendingUser?.email}) will lose the admin
                                    panel and their access to every workspace.
                                    They keep the workspaces they are a member
                                    of.
                                </>
                            ) : (
                                <>
                                    <span className="font-medium">
                                        {pendingUser?.name}
                                    </span>{' '}
                                    ({pendingUser?.email}) will get full access
                                    to every workspace, all client billing, and
                                    this admin panel — including the ability to
                                    make other super admins.
                                    {!pendingUser?.email_verified_at && (
                                        <>
                                            {' '}
                                            Their email will also be marked
                                            verified, since the admin panel is
                                            unreachable without it.
                                        </>
                                    )}
                                </>
                            )}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={saving}>
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction
                            onClick={(e) => {
                                e.preventDefault();
                                confirmSuperAdmin();
                            }}
                            disabled={saving}
                        >
                            {saving
                                ? 'Saving...'
                                : pendingUser?.is_super_admin
                                  ? 'Revoke access'
                                  : 'Make super admin'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AdminSidebarLayout>
    );
}
