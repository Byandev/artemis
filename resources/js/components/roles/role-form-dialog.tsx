import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Role } from '@/types/models/Role';
import { Workspace } from '@/types/models/Workspace';
import { useForm } from '@inertiajs/react';
import React, { useEffect, useMemo } from 'react';
import { toast } from 'sonner';

interface Props {
    workspace: Workspace;
    role?: Role;
    open: boolean;
    onOpenChange: (value: boolean) => void;
}

const RoleFormDialog = ({ workspace, open, onOpenChange, role }: Props) => {
    const isEditing = useMemo(() => !!role, [role]);

    const {
        data,
        setData,
        post,
        patch,
        processing,
        errors,
        reset,
        clearErrors,
    } = useForm({
        name: '',
        description: '',
    });

    useEffect(() => {
        if (open) {
            if (role) {
                setData({
                    name: role.name,
                    description: role.description ?? '',
                });
            } else {
                reset();
                clearErrors();
            }
        }
    }, [open, role]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const url = isEditing
            ? `/workspaces/${workspace.slug}/roles/${role?.id}`
            : `/workspaces/${workspace.slug}/roles`;

        const request = isEditing ? patch : post;

        request(url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    isEditing
                        ? 'Role updated successfully'
                        : 'Role created successfully',
                );

                reset();
                clearErrors();
                onOpenChange(false);
            },
            onError: () =>
                toast.error('Failed to save role. Please check the form.'),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-0 overflow-hidden border-none p-0 shadow-2xl sm:max-w-md dark:bg-zinc-900">
                <div className="border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            {isEditing ? 'Edit Role Record' : 'Add Role Record'}
                        </DialogTitle>
                        <DialogDescription className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                            {isEditing
                                ? 'Edit the permissions and details for this role.'
                                : 'Define a new access level for your workspace.'}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="space-y-5 px-5 py-4">
                        {/* Role Name */}
                        <div className="space-y-1.5">
                            <label className="block font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                Role Name{' '}
                                <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="text"
                                placeholder="e.g. Sales Agent"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 transition-all outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100"
                            />
                            {errors.name && (
                                <p className="mt-1 font-mono text-[11px] text-red-500">
                                    {errors.name}
                                </p>
                            )}
                        </div>

                        {/* Description */}
                        <div className="space-y-1.5">
                            <label className="block font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                Description
                            </label>
                            <textarea
                                placeholder="Briefly describe the responsibilities..."
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                                className="min-h-[100px] w-full resize-none rounded-[10px] border border-black/8 bg-stone-50 p-3 font-mono! text-[13px]! text-gray-800 transition-all outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100"
                            />
                            {errors.description && (
                                <p className="mt-1 font-mono text-[11px] text-red-500">
                                    {errors.description}
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Footer Actions */}
                    <div className="flex items-center justify-end gap-2 border-t border-black/6 bg-stone-50/50 px-5 py-3 dark:border-white/6 dark:bg-white/2">
                        <button
                            type="button"
                            onClick={() => onOpenChange(false)}
                            className="flex h-9 items-center rounded-lg border border-black/8 bg-white px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="flex h-9 items-center rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                        >
                            {processing
                                ? isEditing
                                    ? 'Saving…'
                                    : 'Creating…'
                                : isEditing
                                  ? 'Save Changes'
                                  : 'Create Role'}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
};

export default RoleFormDialog;
