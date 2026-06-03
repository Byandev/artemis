import PageHeader from '@/components/common/PageHeader';
import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import * as rolesRoute from '@/routes/roles';
import { PaginatedData } from '@/types';
import { Role } from '@/types/models/Role';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import { AlertTriangle, ArrowLeft, RefreshCcw, Search, ShieldCheck } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast, Toaster } from 'sonner';

interface Props {
    roles: PaginatedData<Role>;
    workspace: Workspace;
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: { search?: string };
    };
}

export default function Archived({ roles, workspace, query }: Props) {
    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [selectedRole, setSelectedRole] = useState<Role | undefined>(undefined);
    const [isRestoreModalOpen, setIsRestoreModalOpen] = useState(false);

    const canRestore = usePermission(PERMISSIONS.RestoreRoles);

    const handleConfirmRestore = () => {
        if (!selectedRole) return;
        router.post(
            rolesRoute.restore({ workspace, role: selectedRole.id }).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`${selectedRole.name} has been restored.`);
                    setIsRestoreModalOpen(false);
                    setSelectedRole(undefined);
                },
            },
        );
    };

    const columns: ColumnDef<Role>[] = [
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Name" />,
            cell: ({ row }) => (
                <span className="text-[12px] font-medium text-gray-700 dark:text-gray-300">
                    {row.original.name}
                </span>
            ),
        },
        {
            accessorKey: 'description',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Description" />,
            cell: ({ row }) => (
                <div className="max-w-[400px]">
                    <p className="text-[12px] text-gray-500 dark:text-gray-400">
                        {row.original.description || (
                            <span className="text-gray-300 italic dark:text-gray-600">
                                No description
                            </span>
                        )}
                    </p>
                </div>
            ),
        },
        {
            accessorKey: 'deleted_at',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Archived At" />,
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                    {row.original.deleted_at
                        ? new Date(row.original.deleted_at).toLocaleDateString()
                        : '—'}
                </span>
            ),
        },
        ...(canRestore
            ? [
                  {
                      id: 'actions',
                      cell: ({ row }) => (
                          <div className="flex justify-end">
                              <button
                                  onClick={() => {
                                      setSelectedRole(row.original);
                                      setIsRestoreModalOpen(true);
                                  }}
                                  className="flex h-7 items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 font-mono! text-[11px]! font-medium text-emerald-700 transition-all hover:bg-emerald-100 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:hover:bg-emerald-500/20"
                              >
                                  <RefreshCcw className="h-3 w-3" />
                                  Restore
                              </button>
                          </div>
                      ),
                  } as ColumnDef<Role>,
              ]
            : []),
    ];

    return (
        <AppLayout>
            <Head title="Archived Roles" />
            <Toaster position="top-right" richColors />

            <div className="w-full space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Archived Roles"
                    description="Roles that have been archived and are no longer active"
                >
                    <button
                        onClick={() => router.get(`/workspaces/${workspace.slug}/roles`)}
                        className="flex h-8 items-center gap-1.5 rounded-lg border border-black/6 bg-stone-100 px-3.5 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-200 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-400 dark:hover:bg-zinc-700"
                    >
                        <ArrowLeft className="h-3.5 w-3.5" />
                        Back to Roles
                    </button>
                </PageHeader>

                <div className="mb-3 flex items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            type="text"
                            placeholder="Search archived roles..."
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                        />
                    </div>
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={roles.data || []}
                        enableInternalPagination={false}
                        initialSorting={initialSorting}
                        meta={{ ...omit(roles, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                `/workspaces/${workspace.slug}/roles/archived`,
                                {
                                    sort: params?.sort,
                                    'filter[search]': searchValue || undefined,
                                    page: params?.page ?? 1,
                                    per_page: params?.per_page ?? query?.perPage ?? roles.per_page,
                                },
                                { preserveState: true, replace: true, preserveScroll: true },
                            );
                        }}
                    />
                </div>
            </div>

            {canRestore && isRestoreModalOpen && (
                <div className="fixed inset-0 z-100 flex items-center justify-center p-4">
                    <div
                        className="absolute inset-0 bg-slate-900/30 backdrop-blur-[2px]"
                        onClick={() => setIsRestoreModalOpen(false)}
                    />
                    <div className="animate-in fade-in zoom-in relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl duration-150 dark:bg-zinc-900">
                        <div className="flex flex-col items-center p-6 text-center">
                            <div className="mb-4 rounded-xl bg-emerald-50 p-3 dark:bg-emerald-500/10">
                                <RefreshCcw className="h-6 w-6 text-emerald-500" />
                            </div>
                            <h3 className="mb-2 text-xl font-bold text-slate-900 dark:text-slate-100">
                                Restore Role
                            </h3>
                            <p className="mb-5 text-sm text-slate-500 dark:text-slate-400">
                                Restore{' '}
                                <span className="font-semibold text-slate-900 dark:text-slate-100">
                                    "{selectedRole?.name}"
                                </span>{' '}
                                to active status?
                            </p>
                            <div className="mb-6 w-full rounded-xl border border-emerald-100 bg-emerald-50/60 p-3 dark:border-emerald-500/20 dark:bg-emerald-500/10">
                                <p className="flex items-center justify-center gap-2 text-xs font-medium text-emerald-800 dark:text-emerald-400">
                                    <ShieldCheck className="h-4 w-4" />
                                    This role will be visible and usable in the workspace again.
                                </p>
                            </div>
                            <div className="flex w-full items-center gap-3">
                                <Button
                                    variant="outline"
                                    onClick={() => setIsRestoreModalOpen(false)}
                                    className="h-10 flex-1 rounded-lg"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    onClick={handleConfirmRestore}
                                    className="h-10 flex-1 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700"
                                >
                                    Confirm Restore
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
