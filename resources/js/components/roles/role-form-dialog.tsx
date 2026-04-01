import { Workspace } from '@/types/models/Workspace';
import { Role } from '@/types/models/Role';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { useForm } from '@inertiajs/react';
import React, { useEffect, useMemo } from 'react';
import { toast } from 'sonner';

interface Props {
    workspace: Workspace;
    role?: Role | null;
    open: boolean;
    onOpenChange: (value: boolean) => void;
}

const RoleFormDialog = ({ workspace, open, onOpenChange, role }: Props) => {
    const { data, setData, post, processing, errors, reset, patch, clearErrors } = useForm({
        name: '',
        description: '',
    });

    
    useEffect(() => {
        if (open) {
            clearErrors();
            if (role) {
                setData({
                    name: role.name || '',
                    description: role.description || '',
                });
            } else {
                reset();
            }
        }
    }, [open, role]);

    const isEditing = useMemo(() => !!role && !!role.id, [role]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

    
        if (!data.description || data.description.trim() === "") {
            toast.error("Please provide a role description.");
            return;
        }
        
        if (isEditing) {
            patch(`/workspaces/${workspace.slug}/roles/${role?.id}`, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        } else {
            post(`/workspaces/${workspace.slug}/roles`, {
                preserveScroll: true,
                onSuccess: () => {
                    reset();
                    onOpenChange(false);
                },
            });
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md p-0 gap-0 overflow-hidden">
                <div className="px-5 pt-5 pb-4 border-b border-black/6 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            {isEditing ? 'Edit Role' : 'Create Role'}
                        </DialogTitle>
                        <DialogDescription className="text-[12px] text-gray-400 dark:text-gray-500 mt-0.5">
                            {isEditing ? 'Update the role name and description' : 'Define a new access level for your workspace'}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="space-y-5 px-5 py-4">
                        {/* ROLE NAME FIELD */}
                        <div className="space-y-1.5">
                            <label className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                Role Name <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="text"
                                autoFocus
                                required
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="e.g. Sales Agent"
                                className={`h-10 w-full rounded-[10px] border bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:ring-2 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 
                                    ${errors.name 
                                        ? "border-red-500 focus:ring-red-500/15" 
                                        : "border-black/8 focus:border-emerald-500 focus:ring-emerald-500/15 dark:border-white/8 dark:focus:border-emerald-400"
                                    }`}
                            />
                            {errors.name && <p className="font-mono text-[11px] text-red-500 mt-1">{errors.name}</p>}
                        </div>

                        {/* DESCRIPTION FIELD (REQUIRED) */}
                        <div className="space-y-1.5">
                            <label className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                Description <span className="text-red-400">*</span>
                            </label>
                            <textarea
                                rows={3}
                                required
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                placeholder="Briefly describe the responsibilities…"
                                className={`w-full resize-none rounded-[10px] border bg-stone-50 px-3 py-2.5 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:ring-2 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 
                                    ${errors.description 
                                        ? "border-red-500 focus:ring-red-500/15" 
                                        : "border-black/8 focus:border-emerald-500 focus:ring-emerald-500/15 dark:border-white/8 dark:focus:border-emerald-400"
                                    }`}
                            />
                            {errors.description && <p className="font-mono text-[11px] text-red-500 mt-1">{errors.description}</p>}
                        </div>
                    </div>

                    {/* ACTION BUTTONS */}
                    <div className="flex items-center justify-end gap-2 border-t border-black/6 dark:border-white/6 px-5 py-3">
                        <button
                            type="button"
                            onClick={() => onOpenChange(false)}
                            className="flex h-9 items-center rounded-lg border border-black/8 bg-stone-100 px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-200 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="flex h-9 items-center rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                        >
                            {processing 
                                ? (isEditing ? 'Saving…' : 'Creating…') 
                                : (isEditing ? 'Save Changes' : 'Create Role')
                            }
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
};

export default RoleFormDialog;