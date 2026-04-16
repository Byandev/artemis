import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Workspace } from '@/types/models/Workspace';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { User } from '@/types/models/Pancake/User';

interface EmployeeFormDialogProps {
    workspace: Workspace;
    systemUsers: { id: string; name: string }[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    employee?: User | null;
}

export function EmployeeFormDialog({ workspace, systemUsers = [], open, onOpenChange, employee }: EmployeeFormDialogProps) {
    const { data, setData, put, processing, errors, reset, clearErrors } = useForm({
        status: 'ACTIVE',
        user_id: '',
    });

    useEffect(() => {
        if (open && employee) {
            setData({
                status: employee.status || 'ACTIVE',
                user_id: (employee as any).system_user?.id || (employee as any).user_id || '',
            });
        } else if (!open) {
            clearErrors();
            reset();
        }
    }, [employee, open]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        put(`/workspaces/${workspace.slug}/employees/${employee?.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md p-0 gap-0 overflow-hidden border-none shadow-2xl dark:bg-zinc-900 text-left">
                {/* Header Section */}
                <div className="px-5 pt-5 pb-4 border-b border-black/6 dark:border-white/6 text-left">
                    <DialogHeader className="text-left">
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100 text-left">
                            Edit Employee Settings
                        </DialogTitle>
                        <DialogDescription className="text-[12px] text-gray-400 dark:text-gray-500 mt-0.5 text-left">
                            Update settings for <span className="font-medium text-gray-900 dark:text-gray-200">{employee?.name}</span>.
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="space-y-5 px-5 py-4">
                        {/* System User Mapping */}
                        <div className="space-y-1.5">
                            <label className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500 text-left">
                                Assign User
                            </label>
                            <select
                                value={data.user_id ?? ''}
                                onChange={(e) => setData('user_id', e.target.value)}
                                className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 outline-none transition-all focus:border-emerald-500 dark:border-white/8 dark:bg-zinc-800 dark:text-white"
                            >
                                <option value="">No user assigned</option>
                                {systemUsers?.map((user) => (
                                    <option key={user.id} value={user.id}>
                                        {user.name}
                                    </option>
                                ))}
                            </select>
                            {errors.user_id && (
                                <p className="font-mono text-[11px] text-red-500 mt-1 text-left">
                                    {errors.user_id}
                                </p>
                            )}
                        </div>

                        {/* Status Selection */}
                        <div className="space-y-1.5 text-left">
                            <label className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500 text-left">
                                Employee Status
                            </label>
                            <select
                                value={data.user_id ?? ''}
                                onChange={(e) => setData('user_id', e.target.value)}
                                className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 outline-none transition-all focus:border-emerald-500 dark:border-white/8 dark:bg-zinc-800 dark:text-white"
                            >
                                <option value="ACTIVE">ACTIVE</option>
                                <option value="INACTIVE">INACTIVE</option>
                            </select>
                            {errors.status && (
                                <p className="font-mono text-[11px] text-red-500 mt-1 text-left">
                                    {errors.status}
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Footer Actions */}
                    <div className="flex items-center justify-end gap-2 border-t border-black/6 dark:border-white/6 px-5 py-3 bg-stone-50/50 dark:bg-white/2">
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
                            {processing ? 'Saving...' : 'Save Changes'}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
