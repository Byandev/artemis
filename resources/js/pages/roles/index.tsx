import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import {
    Pencil,
    Archive,
    RefreshCcw,
    Search,
    AlertTriangle,
    ShieldCheck
} from 'lucide-react';
import { useState } from 'react';
import { toast, Toaster } from 'sonner';
import { Workspace } from '@/types/models/Workspace';
import { Role } from '@/types/models/Role';
import RoleFormDialog from '@/components/roles/role-form-dialog'; // Added Toaster here
import PageHeader from '@/components/common/PageHeader';


interface Props {
    roles: Role[];
    workspace: Workspace;
}

export default function Index({ roles, workspace }: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedRole, setSelectedRole] = useState<Role | undefined>(
        undefined,
    );

    const [isArchiveModalOpen, setIsArchiveModalOpen] = useState(false);
    const [isRestoreModalOpen, setIsRestoreModalOpen] = useState(false);
    const [openFormModal, setOpenFormModal] = useState(false);


    const handleConfirmArchive = () => {
        if (!selectedRole) return;
        router.patch(`/workspaces/${workspace.slug}/roles/${selectedRole.id}/archive`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`${selectedRole.name} has been archived.`);
                setIsArchiveModalOpen(false);
                setSelectedRole(null);
            },
        });
    };

    const handleConfirmRestore = () => {
        if (!selectedRole) return;
        router.post(`/workspaces/${workspace.slug}/roles/${selectedRole.id}/restore`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`${selectedRole.name} has been restored.`);
                setIsRestoreModalOpen(false);
                setSelectedRole(undefined);
            },
        });
    };

    // const userCounts = useMemo(() => {
    //     const counts: Record<string, number> = {};
    //     users.forEach(u => {
    //         const r = u.role.toLowerCase();
    //         counts[r] = (counts[r] || 0) + 1;
    //     });
    //     return counts;
    // }, [users]);

    const columns: ColumnDef<Role>[] = [
        {
            accessorKey: 'name',
            header: () => <div className="font-mono font-medium text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Role Name</div>,
            cell: ({ row }) => (
                <span className="text-[13px] font-medium text-gray-700 dark:text-gray-300">{row.original.name}</span>
            ),
        },
        {
            accessorKey: 'description',
            header: () => <div className="font-mono font-medium text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Description</div>,
            cell: ({ row }) => (
                <div className="max-w-[400px]">
                    <p className="text-[13px] text-gray-500 dark:text-gray-400">
                        {row.original.description || <span className="text-gray-300 dark:text-gray-600 italic">No description provided</span>}
                    </p>
                </div>
            ),
        },
        // {
        //     id: 'members',
        //     header: () => <div className="font-extrabold text-sm text-gray-900 text-center py-2">Members</div>,
        //     cell: ({ row }) => {
        //         const count = userCounts[row.original.role.toLowerCase()] || 0;
        //         return (
        //             <div className="flex justify-center">
        //                 <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200">
        //                     <Users className="w-3 h-3" />
        //                     {count}
        //                 </span>
        //             </div>
        //         );
        //     }
        // },
        {
            id: 'actions',
            header: () => <div className="font-mono font-medium text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600 text-center">Actions</div>,
            cell: ({ row }) => (
                <div className="flex items-center justify-center gap-2">
                    {!row.original.deleted_at ? (
                        <>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-8 px-3 text-xs font-bold text-gray-600 hover:text-gray-900 hover:bg-gray-100 flex items-center gap-2"
                                onClick={() => {
                                    setSelectedRole(row.original)
                                    setOpenFormModal(true)
                                }}
                            >
                                <Pencil className="w-3.5 h-3.5" />
                                Edit
                            </Button>
                            <div className="h-4 w-px bg-gray-200 mx-1" />
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-8 px-3 text-xs font-bold text-red-500 hover:text-red-700 hover:bg-red-50 flex items-center gap-2"
                                onClick={() => {
                                    setSelectedRole(row.original);
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
                                setSelectedRole(row.original);
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

            <RoleFormDialog workspace={workspace} open={openFormModal} onOpenChange={setOpenFormModal} role={selectedRole} />

            <div className="w-full space-y-6 p-4 md:p-6">
                <PageHeader title="Role Management" description="Define and manage access levels for your workspace">
                    <Button
                        className="bg-emerald-600 dark:bg-emerald-500 hover:bg-emerald-700 dark:hover:bg-emerald-400 text-white px-4 py-2 rounded-lg text-xs font-medium transition-colors"
                        onClick={() => setOpenFormModal(true)}
                    >
                        Add New Role
                    </Button>
                </PageHeader>

                <div className="mb-3 flex items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            type="text"
                            placeholder="Search roles..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="h-9 w-full rounded-[10px] border border-black/6 dark:border-white/6 bg-stone-100 dark:bg-zinc-800 pl-8 pr-3 font-mono text-[12px] text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-600 outline-none transition-all focus:border-emerald-500 dark:focus:border-emerald-400 focus:ring-2 focus:ring-emerald-500/15"
                        />
                    </div>
                </div>

                <div className="rounded-[14px] border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={roles}
                        enableInternalPagination={true}
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
                    <div className="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl duration-150 animate-in fade-in zoom-in">
                        <div className="flex flex-col items-center p-6 text-center">
                            <div className="mb-4 rounded-xl bg-red-50 p-3">
                                <AlertTriangle className="h-6 w-6 text-red-500" />
                            </div>
                            <h3 className="mb-2 text-xl font-bold text-slate-900">
                                Archive Role
                            </h3>
                            <p className="mb-5 text-sm text-slate-500">
                                Are you sure you want to archive the{' '}
                                <span className="font-semibold text-slate-900">
                                    "{selectedRole?.name}"
                                </span>{' '}
                                role?
                            </p>
                            <div className="mb-6 w-full rounded-xl border border-orange-100 bg-orange-50/60 p-3">
                                <p className="flex items-center justify-center gap-2 text-xs font-medium text-orange-800">
                                    <ShieldCheck className="h-4 w-4" />
                                    This role will be hidden but can be restored
                                    later.
                                </p>
                            </div>
                            <div className="flex w-full items-center gap-3">
                                <Button
                                    variant="outline"
                                    onClick={() => setIsArchiveModalOpen(false)}
                                    className="h-10 flex-1 rounded-lg border-slate-200 font-bold text-slate-600"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    onClick={handleConfirmArchive}
                                    className="h-10 flex-1 rounded-lg bg-red-600 font-bold text-white hover:bg-red-700"
                                >
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
                    <div className="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl duration-150 animate-in fade-in zoom-in">
                        <div className="flex flex-col items-center p-6 text-center">
                            <div className="mb-4 rounded-xl bg-emerald-50 p-3">
                                <RefreshCcw className="h-6 w-6 text-emerald-500" />
                            </div>
                            <h3 className="mb-2 text-xl font-bold text-slate-900">
                                Restore Role
                            </h3>
                            <p className="mb-5 text-sm text-slate-500">
                                Do you want to restore the{' '}
                                <span className="font-semibold text-slate-900">
                                    "{selectedRole?.name}"
                                </span>{' '}
                                role to active status?
                            </p>
                            <div className="mb-6 w-full rounded-xl border border-emerald-100 bg-emerald-50/60 p-3">
                                <p className="flex items-center justify-center gap-2 text-xs font-medium text-emerald-800">
                                    <ShieldCheck className="h-4 w-4" />
                                    This role will be visible and usable in the
                                    workspace again.
                                </p>
                            </div>
                            <div className="flex w-full items-center gap-3">
                                <Button
                                    variant="outline"
                                    onClick={() => setIsRestoreModalOpen(false)}
                                    className="h-10 flex-1 rounded-lg border-slate-200 font-bold text-slate-600"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    onClick={handleConfirmRestore}
                                    className="h-10 flex-1 rounded-lg bg-emerald-600 font-bold text-white hover:bg-emerald-700"
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
