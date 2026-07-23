import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import workspaces from '@/routes/workspaces';
import { User } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

export interface DepartmentOption {
    id: number;
    name: string;
}

interface AssignDepartmentDialogProps {
    workspace: Workspace;
    departments: DepartmentOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Single-member mode. */
    member?: User | null;
    /** Bulk mode — takes precedence over `member` when non-empty. */
    memberIds?: number[];
    onSuccess?: () => void;
}

const NONE = 'none';

export function AssignDepartmentDialog({
    workspace,
    departments,
    open,
    onOpenChange,
    member,
    memberIds,
    onSuccess,
}: AssignDepartmentDialogProps) {
    const isBulk = !!memberIds && memberIds.length > 0;

    const { data, setData, put, processing, errors, reset } = useForm<{
        department_id: number | null;
        user_ids: number[];
    }>({
        department_id: null,
        user_ids: [],
    });

    useEffect(() => {
        if (!open) return;
        setData({
            department_id: isBulk
                ? null
                : (member?.pivot?.department_id ?? null),
            user_ids: isBulk ? (memberIds ?? []) : [],
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, member, memberIds]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    data.department_id
                        ? 'Department assigned successfully.'
                        : 'Department cleared successfully.',
                );
                reset();
                onOpenChange(false);
                onSuccess?.();
            },
        };

        if (isBulk) {
            put(workspaces.members.department.bulk.url({ workspace }), options);
        } else if (member) {
            put(
                workspaces.members.department.assign.url({
                    workspace,
                    user: member.id,
                }),
                options,
            );
        }
    };

    const count = memberIds?.length ?? 0;

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                onOpenChange(next);
                if (!next) reset();
            }}
        >
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-md">
                <div className="border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            Assign Department
                        </DialogTitle>
                        <DialogDescription className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                            {isBulk
                                ? `Set the department for ${count} selected member${count === 1 ? '' : 's'}.`
                                : `Set or clear the department for ${member?.name ?? 'this member'}.`}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="space-y-4 px-5 py-4">
                        {!isBulk && member && (
                            <div className="flex items-center gap-3 rounded-[10px] border border-black/6 bg-stone-50 px-3 py-2.5 dark:border-white/6 dark:bg-zinc-800">
                                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 font-mono text-[12px] font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400">
                                    {member.name?.charAt(0).toUpperCase()}
                                </div>
                                <div className="min-w-0">
                                    <p className="truncate text-[13px] font-medium text-gray-800 dark:text-gray-100">
                                        {member.name}
                                    </p>
                                    <p className="truncate font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                        {member.email}
                                    </p>
                                </div>
                                {member.pivot?.department && (
                                    <span className="ml-auto shrink-0 rounded-full bg-gray-100 px-2 py-0.5 font-mono text-[10px] font-medium text-gray-500 dark:bg-zinc-700 dark:text-gray-400">
                                        {member.pivot.department}
                                    </span>
                                )}
                            </div>
                        )}

                        <div className="space-y-1.5">
                            <label className="block font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                Department
                            </label>
                            <Select
                                value={
                                    data.department_id
                                        ? data.department_id.toString()
                                        : NONE
                                }
                                onValueChange={(value) =>
                                    setData(
                                        'department_id',
                                        value === NONE ? null : Number(value),
                                    )
                                }
                            >
                                <SelectTrigger className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 transition-all outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100">
                                    <SelectValue placeholder="Select a department…" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        value={NONE}
                                        className="font-mono text-[12px]"
                                    >
                                        No department
                                    </SelectItem>
                                    {departments.map((department) => (
                                        <SelectItem
                                            key={department.id}
                                            value={department.id.toString()}
                                            className="font-mono text-[12px]"
                                        >
                                            {department.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.department_id && (
                                <p className="font-mono text-[11px] text-red-500">
                                    {errors.department_id}
                                </p>
                            )}
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
                            {processing ? 'Saving…' : 'Save'}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
