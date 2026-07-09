import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Switch } from '@/components/ui/switch';
import workspaces from '@/routes/workspaces';
import { Workspace } from '@/types/models/Workspace';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

export interface Department {
    id: number;
    name: string;
    code?: string | null;
    description?: string | null;
    is_active: boolean;
}

interface DepartmentFormDialogProps {
    workspace: Workspace;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    department?: Department | null;
}

const inputClass =
    'h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 transition-all outline-none placeholder:text-gray-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400';

const labelClass =
    'block font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';

export function DepartmentFormDialog({
    workspace,
    open,
    onOpenChange,
    department,
}: DepartmentFormDialogProps) {
    const isEditing = !!department;

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',
        code: '',
        description: '',
        is_active: true,
    });

    useEffect(() => {
        if (department) {
            setData({
                name: department.name,
                code: department.code ?? '',
                description: department.description ?? '',
                is_active: department.is_active,
            });
        } else {
            reset();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [department, open]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    isEditing
                        ? 'Department updated successfully!'
                        : 'Department created successfully!',
                );
                reset();
                onOpenChange(false);
            },
        };

        if (isEditing && department) {
            put(
                workspaces.departments.update.url({
                    workspace,
                    department: department.id,
                }),
                options,
            );
        } else {
            post(workspaces.departments.store.url({ workspace }), options);
        }
    };

    const handleOpenChange = (next: boolean) => {
        onOpenChange(next);
        if (!next) reset();
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-md">
                <div className="border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            {isEditing
                                ? 'Edit Department'
                                : 'Create Department'}
                        </DialogTitle>
                        <DialogDescription className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                            {isEditing
                                ? 'Update department details'
                                : 'Create a new department for this workspace'}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="space-y-5 px-5 py-4">
                        {/* Name */}
                        <div className="space-y-1.5">
                            <label className={labelClass}>
                                Name <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="text"
                                autoFocus
                                placeholder="e.g. Customer Support"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                className={inputClass}
                            />
                            {errors.name && (
                                <p className="font-mono text-[11px] text-red-500">
                                    {errors.name}
                                </p>
                            )}
                        </div>

                        {/* Code */}
                        <div className="space-y-1.5">
                            <label className={labelClass}>Code</label>
                            <input
                                type="text"
                                placeholder="e.g. CS"
                                value={data.code}
                                onChange={(e) =>
                                    setData('code', e.target.value)
                                }
                                className={inputClass}
                            />
                            {errors.code && (
                                <p className="font-mono text-[11px] text-red-500">
                                    {errors.code}
                                </p>
                            )}
                        </div>

                        {/* Description */}
                        <div className="space-y-1.5">
                            <label className={labelClass}>Description</label>
                            <textarea
                                rows={3}
                                placeholder="What does this department do?"
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                                className={`${inputClass} h-auto resize-none py-2`}
                            />
                            {errors.description && (
                                <p className="font-mono text-[11px] text-red-500">
                                    {errors.description}
                                </p>
                            )}
                        </div>

                        {/* Active toggle */}
                        <div className="flex items-center justify-between rounded-[10px] border border-black/8 bg-stone-50 px-3 py-2.5 dark:border-white/8 dark:bg-zinc-800">
                            <div>
                                <p className="font-mono text-[12px] font-medium text-gray-700 dark:text-gray-200">
                                    Active
                                </p>
                                <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                    Inactive departments stay on record but are
                                    hidden from pickers.
                                </p>
                            </div>
                            <Switch
                                checked={data.is_active}
                                onCheckedChange={(checked) =>
                                    setData('is_active', checked)
                                }
                            />
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-2 border-t border-black/6 px-5 py-3 dark:border-white/6">
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
                                ? isEditing
                                    ? 'Saving…'
                                    : 'Creating…'
                                : isEditing
                                  ? 'Save Changes'
                                  : 'Create Department'}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
