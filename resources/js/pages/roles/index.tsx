import AppLayout from '@/layouts/app-layout';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Pencil,
    Archive,
    RefreshCcw,
    Search,
    AlertTriangle,
    ShieldCheck,
    MoreHorizontal,
    KeyRound,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast, Toaster } from 'sonner';
import { Workspace } from '@/types/models/Workspace';
import { Role } from '@/types/models/Role';
import RoleFormDialog from '@/components/roles/role-form-dialog';
import PageHeader from '@/components/common/PageHeader';
import { Button } from '@/components/ui/button';
import { PaginatedData } from '@/types';
import { toFrontendSort } from '@/lib/sort';
import { omit } from 'lodash';
import * as rolesRoute from '@/routes/roles';
import clsx from 'clsx';

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

function StatusBadge({ deletedAt }: { deletedAt?: string | null }) {
    const isActive = !deletedAt;
    return (
        <span
            className={clsx(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-mono text-[11px] font-medium uppercase tracking-wide',
                isActive
                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'
                    : 'bg-red-50 text-red-500 dark:bg-red-500/10 dark:text-red-400',
            )}
        >
            <span className={clsx('h-1.5 w-1.5 rounded-full', isActive ? 'bg-emerald-500' : 'bg-red-400')} />
            {isActive ? 'Active' : 'Archived'}
        </span>
    );
}

export default function Index({ roles, workspace, query }: Props) {
    const initialSorting = useMemo(() => toFrontendSort(query?.sort ?? null), [query?.sort]);

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [selectedRole, setSelectedRole] = useState<Role | undefined>(undefined);
    const [isArchiveModalOpen, setIsArchiveModalOpen] = useState(false);
    const [isRestoreModalOpen, setIsRestoreModalOpen] = useState(false);
    const [openFormModal, setOpenFormModal] = useState(false);

    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(
                rolesRoute.index(workspace).url,
                {
                    sort: query?.sort,
                    'filter[search]': searchValue || undefined,
                    page: searchValue ? 1 : query?.page ?? 1,
                },
                { preserveState: true, replace: true, preserveScroll: true, only: ['roles'] },
            );
        }, 500);
        return () => clearTimeout(timer);
    }, [searchValue]);

    const handleConfirmArchive = () => {
        if (!selectedRole) return;
        router.delete(`/workspaces/${workspace.slug}/roles/${selectedRole.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`${selectedRole.name} has been archived.`);
                setIsArchiveModalOpen(false);
                setSelectedRole(undefined);
            },
        });
    };

    const handleConfirmRestore = () => {
        if (!selectedRole) return;
        router.post(rolesRoute.restore({ workspace, role: selectedRole.id }).url, {}, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`${selectedRole.name} has been restored.`);
                setIsRestoreModalOpen(false);
                setSelectedRole(undefined);
            },
        });
    };

    const columns: ColumnDef<Role>[] = [
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Name" />,
            cell: ({ row }) => (
                <span className="text-[12px] font-medium text-gray-700 dark:text-gray-300">{row.original.name}</span>
            ),
        },
        {
            accessorKey: 'description',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Description" />,
            cell: ({ row }) => (
                <div className="max-w-[400px]">
                    <p className="text-[12px] text-gray-500 dark:text-gray-400">
                        {row.original.description || <span className="text-gray-300 dark:text-gray-600 italic">No description</span>}
                    </p>
                </div>
            ),
        },
        {
            accessorKey: 'deleted_at',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Status" />,
            cell: ({ row }) => <StatusBadge deletedAt={row.original.deleted_at} />,
        },
        {
            id: 'actions',
            cell: ({ row }) => (
                <div className="flex justify-end">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 transition-all hover:border-black/12 hover:bg-stone-100 hover:text-gray-600 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-500 dark:hover:border-white/12 dark:hover:bg-zinc-700 dark:hover:text-gray-300">
                                <MoreHorizontal className="h-3.5 w-3.5" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-48">
                            {!row.original.deleted_at ? (
                                <>
                                    <DropdownMenuItem onClick={() => { setSelectedRole(row.original); setOpenFormModal(true); }}>
                                        <Pencil />
                                        Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={() => router.get(`/workspaces/${workspace.slug}/roles/${row.original.id}/permissions`)}>
                                        <KeyRound />
                                        Manage Permissions
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem variant="destructive" onClick={() => { setSelectedRole(row.original); setIsArchiveModalOpen(true); }}>
                                        <Archive />
                                        Archive
                                    </DropdownMenuItem>
                                </>
                            ) : (
                                <DropdownMenuItem onClick={() => { setSelectedRole(row.original); setIsRestoreModalOpen(true); }}>
                                    <RefreshCcw />
                                    Restore
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title="Roles Management" />
            <Toaster position="top-right" richColors />

            <RoleFormDialog
                workspace={workspace}
                open={openFormModal}
                onOpenChange={(open) => {
                    setOpenFormModal(open);
                    if (!open) setSelectedRole(undefined);
                }}
                role={selectedRole}
            />

            <div className="w-full space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Role Management"
                    description="Define and manage access levels for your workspace"
                >
                    <button
                        onClick={() => { setSelectedRole(undefined); setOpenFormModal(true); }}
                        className="flex h-8 items-center rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                    >
                        Add New Role
                    </button>
                </PageHeader>

                <div className="mb-3 flex items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            type="text"
                            placeholder="Search roles..."
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
                                rolesRoute.index(workspace).url,
                                {
                                    sort: params?.sort,
                                    'filter[search]': searchValue || undefined,
                                    page: params?.page ?? 1,
                                },
                                { preserveState: true, replace: true, preserveScroll: true },
                            );
                        }}
                    />
                </div>
            </div>

            {/* Archive Modal */}
            {isArchiveModalOpen && (
                <div className="fixed inset-0 z-100 flex items-center justify-center p-4">
                    <div
                        className="absolute inset-0 bg-slate-900/30 backdrop-blur-[2px]"
                        onClick={() => setIsArchiveModalOpen(false)}
                    />
                    <div className="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl duration-150 animate-in fade-in zoom-in dark:bg-zinc-900">
                        <div className="flex flex-col items-center p-6 text-center">
                            <div className="mb-4 rounded-xl bg-red-50 p-3">
                                <AlertTriangle className="h-6 w-6 text-red-500" />
                            </div>
                            <h3 className="mb-2 text-xl font-bold text-slate-900 dark:text-slate-100">Archive Role</h3>
                            <p className="mb-5 text-sm text-slate-500">
                                Are you sure you want to archive{' '}
                                <span className="font-semibold text-slate-900 dark:text-slate-100">"{selectedRole?.name}"</span>?
                            </p>
                            <div className="mb-6 w-full rounded-xl border border-orange-100 bg-orange-50/60 p-3">
                                <p className="flex items-center justify-center gap-2 text-xs font-medium text-orange-800">
                                    <ShieldCheck className="h-4 w-4" />
                                    This role will be hidden but can be restored later.
                                </p>
                            </div>
                            <div className="flex w-full items-center gap-3">
                                <Button variant="outline" onClick={() => setIsArchiveModalOpen(false)} className="h-10 flex-1 rounded-lg">
                                    Cancel
                                </Button>
                                <Button onClick={handleConfirmArchive} className="h-10 flex-1 rounded-lg bg-red-600 text-white hover:bg-red-700">
                                    Confirm Archive
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Restore Modal */}
            {isRestoreModalOpen && (
                <div className="fixed inset-0 z-100 flex items-center justify-center p-4">
                    <div
                        className="absolute inset-0 bg-slate-900/30 backdrop-blur-[2px]"
                        onClick={() => setIsRestoreModalOpen(false)}
                    />
                    <div className="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl duration-150 animate-in fade-in zoom-in dark:bg-zinc-900">
                        <div className="flex flex-col items-center p-6 text-center">
                            <div className="mb-4 rounded-xl bg-emerald-50 p-3">
                                <RefreshCcw className="h-6 w-6 text-emerald-500" />
                            </div>
                            <h3 className="mb-2 text-xl font-bold text-slate-900 dark:text-slate-100">Restore Role</h3>
                            <p className="mb-5 text-sm text-slate-500">
                                Restore{' '}
                                <span className="font-semibold text-slate-900 dark:text-slate-100">"{selectedRole?.name}"</span>{' '}
                                to active status?
                            </p>
                            <div className="mb-6 w-full rounded-xl border border-emerald-100 bg-emerald-50/60 p-3">
                                <p className="flex items-center justify-center gap-2 text-xs font-medium text-emerald-800">
                                    <ShieldCheck className="h-4 w-4" />
                                    This role will be visible and usable in the workspace again.
                                </p>
                            </div>
                            <div className="flex w-full items-center gap-3">
                                <Button variant="outline" onClick={() => setIsRestoreModalOpen(false)} className="h-10 flex-1 rounded-lg">
                                    Cancel
                                </Button>
                                <Button onClick={handleConfirmRestore} className="h-10 flex-1 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
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
