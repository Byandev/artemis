import { Workspace } from '@/types/models/Workspace';
import { Role } from '@/types/models/Role';
import {
    Dialog,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@headlessui/react';
import {  useForm } from '@inertiajs/react';
import React, { useEffect, useMemo } from 'react';

interface Props {
    workspace: Workspace
    role?: Role;
    open: boolean;
    onOpenChange: (value: boolean) => void
}

const RoleFormDialog = ({ workspace, open, onOpenChange, role }: Props) => {
    const { data, setData, post, processing, errors, reset, patch } = useForm({
        name: '',
        description: '',
    });

    useEffect(() => {
        if (role) {
            setData('name', role.name);
            setData('description', role.description)
        } else {
            reset()
        }
    }, [reset, role, setData]);

    const isEditing = useMemo(() => !!role, [role])

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isEditing) {
            patch(`/workspaces/${workspace.slug}/roles/${role?.id}`, {
                onSuccess: () => {
                    onOpenChange(false);
                }
            });
        } else {
            post(`/workspaces/${workspace.slug}/roles`, {
                onSuccess: () => {
                    reset();
                    onOpenChange(false);
                },
            });
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogTitle>{isEditing ? 'Update role' : 'Create role'}</DialogTitle>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-1.5">
                        <label className="ml-1 text-xs font-bold text-gray-700">
                            Role Identifier
                        </label>
                        <input
                            type="text"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="e.g. moderator"
                            className={`w-full rounded-lg border px-4 py-2.5 text-sm transition-all outline-none focus:border-[#2dd4bf] focus:ring-4 focus:ring-[#2dd4bf]/10 ${
                                errors.name
                                    ? 'border-red-500'
                                    : 'border-gray-200'
                            }`}
                        />
                        {errors.name && (
                            <p className="text-[10px] font-semibold text-red-500">
                                {errors.name}
                            </p>
                        )}
                    </div>

                    <div className="space-y-1.5">
                        <label className="ml-1 text-xs font-bold text-gray-700">
                            Description
                        </label>
                        <textarea
                            rows={3}
                            value={data.description}
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                            placeholder="Briefly describe the responsibilities..."
                            className="w-full resize-none rounded-lg border border-gray-200 px-4 py-2.5 text-sm transition-all outline-none focus:border-[#2dd4bf] focus:ring-4 focus:ring-[#2dd4bf]/10"
                        />
                    </div>

                    <div className="flex flex-col items-center gap-3 pt-4">
                        <Button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded-lg bg-[#2dd4bf] py-5 font-bold text-white shadow-md shadow-teal-100 transition-transform hover:bg-[#26b2a1] active:scale-95 sm:w-56"
                        >
                            {processing ? 'Submitting...' : 'Submit'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default RoleFormDialog;
