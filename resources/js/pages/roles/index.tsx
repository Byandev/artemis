import AppLayout from '@/layouts/app-layout';
import ComponentCard from '@/components/common/ComponentCard';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import {
    Pencil,
    Archive,
    RefreshCcw,
    Search,
    Users,
    AlertTriangle,
    ShieldCheck
} from 'lucide-react';
import { useState, useMemo, useEffect } from 'react';
import { toast, Toaster } from 'sonner';
import { Workspace } from '@/types/models/Workspace';
import { type SharedData, User } from '@/types';
import { Role } from '@/types/models/Role'; // Added Toaster here


interface Props {
    users: User[];
    roles: Role[];
    workspace: Workspace;
}

export default function Index({ users, roles, workspace }: Props) {
    const { auth } = usePage<SharedData>().props;

    const [searchQuery, setSearchQuery] = useState('');
    const [showArchived, setShowArchived] = useState(false);

    const [isArchiveModalOpen, setIsArchiveModalOpen] = useState(false);
    const [roleToArchive, setRoleToArchive] = useState<Role | null>(null);

    const [isRestoreModalOpen, setIsRestoreModalOpen] = useState(false);
    const [roleToRestore, setRoleToRestore] = useState<Role | null>(null);

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        if (params.get('archived') === 'true') {
            setShowArchived(true);
        }
    }, []);

    const isSuperAdmin = auth.user.role === 'superadmin';
    const isWorkspaceAdmin = auth.user.role === 'admin' || auth.user.role === 'owner';

    const handleConfirmArchive = () => {
        if (!roleToArchive) return;
        router.patch(`/workspaces/${workspace.slug}/roles/${roleToArchive.id}/archive`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`${roleToArchive.display_name} has been archived.`);
                setIsArchiveModalOpen(false);
                setRoleToArchive(null);
                setShowArchived(true);
            },
        });
    };

    const handleConfirmRestore = () => {
        if (!roleToRestore) return;
        router.post(`/workspaces/${workspace.slug}/roles/${roleToRestore.id}/restore`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`${roleToRestore.display_name} has been restored.`);
                setIsRestoreModalOpen(false);
                setRoleToRestore(null);
                setShowArchived(false);
            },
        });
    };

    const filteredRoles = useMemo(() => {
        return roles.filter((role) => {
            const search = searchQuery.toLowerCase();
            const matchesSearch =
                role.display_name.toLowerCase().includes(search) ||
                role.role.toLowerCase().includes(search) ||
                (role.description || '').toLowerCase().includes(search);

            const isArchived = role.deleted_at !== null;
            const matchesStatus = showArchived ? isArchived : !isArchived;

            return matchesSearch && matchesStatus;
        });
    }, [roles, searchQuery, showArchived]);

    const userCounts = useMemo(() => {
        const counts: Record<string, number> = {};
        users.forEach(u => {
            const r = u.role.toLowerCase();
            counts[r] = (counts[r] || 0) + 1;
        });
        return counts;
    }, [users]);

    const columns: ColumnDef<Role>[] = [
        {
            accessorKey: 'display_name',
            header: () => <div className="font-extrabold text-sm text-gray-900 py-2 text-center">Role Name</div>,
            cell: ({ row }) => (
                <div className="flex flex-col items-center">
                    <span className="font-bold text-gray-900">{row.original.display_name}</span>
                </div>
            ),
        },
        {
            accessorKey: 'description',
            header: () => <div className="font-extrabold text-sm text-gray-900 py-2">Description</div>,
            cell: ({ row }) => (
                <div className="max-w-[400px]">
                    <p className="text-sm text-gray-600">
                        {row.original.description || <span className="text-gray-300 italic">No description provided</span>}
                    </p>
                </div>
            ),
        },
        {
            id: 'members',
            header: () => <div className="font-extrabold text-sm text-gray-900 text-center py-2">Members</div>,
            cell: ({ row }) => {
                const count = userCounts[row.original.role.toLowerCase()] || 0;
                return (
                    <div className="flex justify-center">
                        <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200">
                            <Users className="w-3 h-3" />
                            {count}
                        </span>
                    </div>
                );
            }
        },
        {
            id: 'actions',
            header: () => <div className="text-center font-bold text-sm text-gray-900">Actions</div>,
            cell: ({ row }) => (
                <div className="flex items-center justify-center gap-2">
                    {!showArchived ? (
                        <>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-8 px-3 text-xs font-bold text-gray-600 hover:text-gray-900 hover:bg-gray-100 flex items-center gap-2"
                                onClick={() => router.get(`/workspaces/${workspace.slug}/roles/${row.original.id}/edit`)}
                            >
                                <Pencil className="w-3.5 h-3.5" />
                                Edit
                            </Button>
                            <div className="h-4 w-[1px] bg-gray-200 mx-1" />
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-8 px-3 text-xs font-bold text-red-500 hover:text-red-700 hover:bg-red-50 flex items-center gap-2"
                                onClick={() => {
                                    setRoleToArchive(row.original);
                                    setIsArchiveModalOpen(true);
                                }}
                            >
                                <Archive className="w-3.5 h-3.5" />
                                Archive
                            </Button>
                        </>
                    ) : (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8 px-3 text-xs font-bold text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 flex items-center gap-2"
                            onClick={() => {
                                setRoleToRestore(row.original);
                                setIsRestoreModalOpen(true);
                            }}
                        >
                            <RefreshCcw className="w-3.5 h-3.5" />
                            Restore
                        </Button>
                    )}
                </div>
            )
        }
    ];

    return (
        <AppLayout>
            <Head title="Roles Management" />
            {/* Added Toaster component here */}
            <Toaster position="top-right" richColors />

            <div className="p-6 lg:p-8 w-full space-y-6">
                <div className="flex items-center justify-between w-full">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-gray-900">Role Management</h1>
                        <p className="text-sm text-gray-500 mt-1">Define and manage access levels for your workspace</p>
                    </div>
                    {(isSuperAdmin || isWorkspaceAdmin) && (
                        <Button
                            className="bg-[#2dd4bf] hover:bg-[#26b2a1] text-white font-bold"
                            onClick={() => router.get(`/workspaces/${workspace.slug}/roles/add`)}
                        >
                            Add New Role
                        </Button>
                    )}
                </div>

                <ComponentCard desc="Configure the roles available in this workspace.">
                    <div className="rounded-xl border border-gray-100 bg-white w-full overflow-hidden shadow-sm">
                        <div className="flex flex-col gap-4 p-5 md:flex-row md:items-center border-b border-gray-0 bg-white">
                            <div className="relative flex-1 max-w-sm">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                                <input
                                    type="text"
                                    placeholder="Search roles..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#2dd4bf]/20 focus:border-[#2dd4bf] transition-all outline-none"
                                />
                            </div>

                            <div className="hidden md:block flex-1"></div>

                            {(isSuperAdmin || isWorkspaceAdmin) && (
                                <div className="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-slate-50 transition-colors cursor-pointer group border border-transparent hover:border-slate-200">
                                    <input
                                        type="checkbox"
                                        id="archived-toggle"
                                        checked={showArchived}
                                        onChange={(e) => setShowArchived(e.target.checked)}
                                        className="rounded border-gray-300 text-[#2dd4bf] focus:ring-[#2dd4bf] h-4 w-4 cursor-pointer"
                                    />
                                    <label htmlFor="archived-toggle" className="text-xs font-bold text-slate-500 group-hover:text-slate-700 cursor-pointer select-none">
                                        Show Archived Roles
                                    </label>
                                </div>
                            )}
                        </div>

                        <div className="w-full">
                            <DataTable
                                columns={columns}
                                data={filteredRoles}
                                enableInternalPagination={true}
                            />
                        </div>
                    </div>
                </ComponentCard>
            </div>

            {/* Archive Modal */}
            {isArchiveModalOpen && (
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4">
                    <div className="absolute inset-0 bg-slate-900/30 backdrop-blur-[2px]" onClick={() => setIsArchiveModalOpen(false)} />
                    <div className="relative bg-white rounded-2xl shadow-xl max-w-md w-full overflow-hidden animate-in fade-in zoom-in duration-150">
                        <div className="p-6 flex flex-col items-center text-center">
                            <div className="p-3 bg-red-50 rounded-xl mb-4">
                                <AlertTriangle className="w-6 h-6 text-red-500" />
                            </div>
                            <h3 className="text-xl font-bold text-slate-900 mb-2">Archive Role</h3>
                            <p className="text-slate-500 text-sm mb-5">
                                Are you sure you want to archive the <span className="font-semibold text-slate-900">"{roleToArchive?.display_name}"</span> role?
                            </p>
                            <div className="w-full p-3 bg-orange-50/60 rounded-xl border border-orange-100 mb-6">
                                <p className="text-xs text-orange-800 font-medium flex items-center justify-center gap-2">
                                    <ShieldCheck className="w-4 h-4" />
                                    This role will be hidden but can be restored later.
                                </p>
                            </div>
                            <div className="flex items-center gap-3 w-full">
                                <Button variant="outline" onClick={() => setIsArchiveModalOpen(false)} className="flex-1 text-slate-600 font-bold h-10 rounded-lg border-slate-200">Cancel</Button>
                                <Button onClick={handleConfirmArchive} className="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold h-10 rounded-lg">Confirm Archive</Button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Restore Modal */}
            {isRestoreModalOpen && (
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4">
                    <div className="absolute inset-0 bg-slate-900/30 backdrop-blur-[2px]" onClick={() => setIsRestoreModalOpen(false)} />
                    <div className="relative bg-white rounded-2xl shadow-xl max-w-md w-full overflow-hidden animate-in fade-in zoom-in duration-150">
                        <div className="p-6 flex flex-col items-center text-center">
                            <div className="p-3 bg-emerald-50 rounded-xl mb-4">
                                <RefreshCcw className="w-6 h-6 text-emerald-500" />
                            </div>
                            <h3 className="text-xl font-bold text-slate-900 mb-2">Restore Role</h3>
                            <p className="text-slate-500 text-sm mb-5">
                                Do you want to restore the <span className="font-semibold text-slate-900">"{roleToRestore?.display_name}"</span> role to active status?
                            </p>
                            <div className="w-full p-3 bg-emerald-50/60 rounded-xl border border-emerald-100 mb-6">
                                <p className="text-xs text-emerald-800 font-medium flex items-center justify-center gap-2">
                                    <ShieldCheck className="w-4 h-4" />
                                    This role will be visible and usable in the workspace again.
                                </p>
                            </div>
                            <div className="flex items-center gap-3 w-full">
                                <Button variant="outline" onClick={() => setIsRestoreModalOpen(false)} className="flex-1 text-slate-600 font-bold h-10 rounded-lg border-slate-200">Cancel</Button>
                                <Button onClick={handleConfirmRestore} className="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold h-10 rounded-lg">Confirm Restore</Button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
