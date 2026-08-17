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
import { Switch } from '@/components/ui/switch';
import AdminSidebarLayout from '@/layouts/admin/admin-sidebar-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData, SharedData, User } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import axios from 'axios';
import { omit } from 'lodash';
import debounce from 'lodash/debounce';
import { Check, Copy, Search } from 'lucide-react';
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

    const currentUser = usePage<SharedData>().props.auth.user;
    // The row awaiting confirmation, and which way it is about to go.
    const [pendingRole, setPendingRole] = useState<{
        user: AdminUser;
        grant: boolean;
    } | null>(null);
    const [savingRoleFor, setSavingRoleFor] = useState<number | null>(null);

    const applyRoleChange = () => {
        if (!pendingRole) return;

        const { user, grant } = pendingRole;
        setSavingRoleFor(user.id);

        router.patch(
            `/admin/users/${user.id}/super-admin`,
            { is_super_admin: grant },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () =>
                    toast.success(
                        grant
                            ? `${user.name} is now a Super Admin.`
                            : `Super Admin access removed from ${user.name}.`,
                    ),
                // The self-demotion and last-admin guards come back as
                // validation errors, so they land here with their own wording.
                onError: (errors) =>
                    toast.error(
                        errors.is_super_admin ?? 'Could not change that role.',
                    ),
                onFinish: () => {
                    setSavingRoleFor(null);
                    setPendingRole(null);
                },
            },
        );
    };

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
            cell: ({ row }) => {
                const user = row.original;
                const isSelf = user.id === currentUser?.id;

                return (
                    <div className="flex items-center gap-2">
                        <Switch
                            checked={!!user.is_super_admin}
                            // Demoting yourself locks you out of /admin, so the
                            // server refuses it and the switch says so first.
                            disabled={
                                savingRoleFor === user.id ||
                                (isSelf && !!user.is_super_admin)
                            }
                            onCheckedChange={(grant) =>
                                setPendingRole({ user, grant })
                            }
                            aria-label={`Super Admin access for ${user.name}`}
                            title={
                                isSelf && user.is_super_admin
                                    ? 'You cannot remove your own Super Admin access'
                                    : undefined
                            }
                        />
                        {user.is_super_admin ? (
                            <Badge className="border-violet-200/60 bg-violet-50 text-violet-700">
                                Super Admin
                            </Badge>
                        ) : (
                            <Badge variant="outline">User</Badge>
                        )}
                    </div>
                );
            },
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
                    Reset password
                </p>
            ),
            cell: ({ row }) => {
                const user = row.original;
                const copied = copiedUserId === user.id;

                return (
                    <div className="flex justify-end">
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
                open={!!pendingRole}
                onOpenChange={(open) => {
                    if (!open) setPendingRole(null);
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {pendingRole?.grant
                                ? `Make ${pendingRole?.user.name} a Super Admin?`
                                : `Remove Super Admin from ${pendingRole?.user.name}?`}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {pendingRole?.grant
                                ? 'Super Admins can reach every workspace and the whole admin area, including billing, users and activity logs. Only grant this to people who should see all of it.'
                                : 'They keep their workspace memberships, but lose the admin area and their access to workspaces they are not a member of.'}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={(event) => {
                                // Kept open until the request settles, so the
                                // dialog can't close over a failed change.
                                event.preventDefault();
                                applyRoleChange();
                            }}
                            disabled={savingRoleFor !== null}
                        >
                            {pendingRole?.grant
                                ? 'Make Super Admin'
                                : 'Remove access'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AdminSidebarLayout>
    );
}
