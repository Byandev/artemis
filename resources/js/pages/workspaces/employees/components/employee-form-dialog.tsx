import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { User } from '@/types/models/Pancake/User';
import { Workspace } from '@/types/models/Workspace';
import { useForm } from '@inertiajs/react';
import { Check, ChevronsUpDown, Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';

export interface SystemUserOption {
    id: string;
    name: string;
    email?: string | null;
}

interface EmployeeFormDialogProps {
    workspace: Workspace;
    systemUsers: SystemUserOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    employee?: User | null;
    onSuccess?: () => void;
}

const UNASSIGNED: SystemUserOption = { id: '', name: 'No user assigned' };

/**
 * The system user a pancake employee maps to, picked by searching the roster.
 *
 * Every member of the workspace is an option here, so on a workspace with a
 * full CSR floor the plain <select> this replaces meant scrolling the whole
 * roster to reach one name. The list is already on the page, so the filtering
 * happens here rather than over the wire.
 *
 * The list floats over the dialog rather than being portalled out of it: a
 * Popover would leave the dialog's focus trap and the search box with it. The
 * dialog it sits in has to allow overflow for that, which is why its content
 * carries a rounded footer instead of clipping its own corners.
 */
function AssignUserSelect({
    users,
    value,
    onChange,
}: {
    users: SystemUserOption[];
    value: string;
    onChange: (value: string) => void;
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [highlight, setHighlight] = useState(0);
    const containerRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const listRef = useRef<HTMLDivElement>(null);

    // ids arrive from the API as numbers; the form field holds a string.
    const selected = users.find((user) => String(user.id) === String(value));

    const options = useMemo(() => {
        const term = search.trim().toLowerCase();

        if (!term) {
            // Unassigning is an option like any other so it stays reachable by
            // keyboard, but it has no name for a search to match against.
            return [UNASSIGNED, ...users];
        }

        return users.filter((user) =>
            `${user.name ?? ''} ${user.email ?? ''}`
                .toLowerCase()
                .includes(term),
        );
    }, [users, search]);

    useEffect(() => {
        if (!open) return;

        const handleClickOutside = (event: MouseEvent) => {
            if (!containerRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () =>
            document.removeEventListener('mousedown', handleClickOutside);
    }, [open]);

    useEffect(() => {
        if (!open) return;

        setSearch('');

        // Open on whoever is assigned rather than on the top of the list, so
        // an Enter straight after opening cannot silently unassign them.
        const assigned = users.findIndex(
            (user) => String(user.id) === String(value),
        );
        setHighlight(assigned < 0 ? 0 : assigned + 1);

        inputRef.current?.focus();
    }, [open]);

    useEffect(() => {
        if (!open) return;

        const node = listRef.current?.children[highlight] as
            | HTMLElement
            | undefined;
        node?.scrollIntoView({ block: 'nearest' });
    }, [highlight, open]);

    const select = (id: string) => {
        onChange(id);
        setOpen(false);
    };

    const handleKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setHighlight((index) => Math.min(index + 1, options.length - 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setHighlight((index) => Math.max(index - 1, 0));
        } else if (event.key === 'Enter') {
            // Without this the form underneath would submit on the first Enter.
            event.preventDefault();
            const option = options[highlight];
            if (option) select(String(option.id));
        } else if (event.key === 'Escape') {
            // The dialog closes on Escape too; while the list is open it is the
            // list that the key belongs to.
            event.preventDefault();
            event.stopPropagation();
            setOpen(false);
        }
    };

    return (
        <div ref={containerRef} className="relative">
            <button
                type="button"
                onClick={() => setOpen((isOpen) => !isOpen)}
                className="flex h-10 w-full items-center justify-between gap-2 rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 transition-all outline-none hover:border-black/14 focus:border-emerald-500 dark:border-white/8 dark:bg-zinc-800 dark:text-white dark:hover:border-white/14"
            >
                <span
                    className={`flex-1 truncate text-left ${selected ? '' : 'text-gray-400 dark:text-gray-500'}`}
                >
                    {selected ? selected.name : UNASSIGNED.name}
                </span>
                <ChevronsUpDown className="h-3.5 w-3.5 shrink-0 opacity-50" />
            </button>

            {open && (
                <div className="absolute top-full right-0 left-0 z-20 mt-1 overflow-hidden rounded-[10px] border border-black/8 bg-white shadow-lg dark:border-white/8 dark:bg-zinc-800">
                    <div className="flex items-center gap-2 border-b border-black/6 px-3 dark:border-white/6">
                        <Search className="h-3.5 w-3.5 shrink-0 text-gray-400 dark:text-gray-500" />
                        <input
                            ref={inputRef}
                            value={search}
                            onChange={(e) => {
                                setSearch(e.target.value);
                                setHighlight(0);
                            }}
                            onKeyDown={handleKeyDown}
                            placeholder="Search users…"
                            className="h-9 w-full bg-transparent font-mono! text-[12px]! text-gray-800 outline-none placeholder:text-gray-400 dark:text-gray-100 dark:placeholder:text-gray-600"
                        />
                    </div>

                    <div ref={listRef} className="max-h-56 overflow-y-auto p-1">
                        {options.length === 0 ? (
                            <p className="px-2 py-3 text-center font-mono text-[11px] text-gray-400 dark:text-gray-600">
                                No users found.
                            </p>
                        ) : (
                            options.map((user, index) => {
                                const id = String(user.id);
                                const isSelected = id === String(value ?? '');

                                return (
                                    <button
                                        key={id || 'unassigned'}
                                        type="button"
                                        onMouseEnter={() => setHighlight(index)}
                                        onClick={() => select(id)}
                                        className={`flex w-full items-center justify-between gap-2 rounded-md px-2 py-1.5 text-left font-mono! text-[12px]! transition-colors ${
                                            index === highlight
                                                ? 'bg-stone-100 dark:bg-zinc-700'
                                                : ''
                                        } ${
                                            id
                                                ? 'text-gray-700 dark:text-gray-200'
                                                : 'text-gray-500 dark:text-gray-400'
                                        }`}
                                    >
                                        <span className="truncate">
                                            {user.name}
                                            {user.email && (
                                                <span className="ml-2 text-gray-400 dark:text-gray-500">
                                                    {user.email}
                                                </span>
                                            )}
                                        </span>
                                        {isSelected && (
                                            <Check className="h-3.5 w-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                        )}
                                    </button>
                                );
                            })
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

export function EmployeeFormDialog({
    workspace,
    systemUsers = [],
    open,
    onOpenChange,
    employee,
    onSuccess,
}: EmployeeFormDialogProps) {
    const { data, setData, put, post, processing, errors, reset, clearErrors } =
        useForm({
            status: 'ACTIVE',
            user_id: '',
        });

    useEffect(() => {
        if (open && employee) {
            setData({
                // Older rows were written with a lowercase status, which matches
                // neither option and leaves the select blank. Fold it so the
                // dialog opens on the value the roster is showing.
                status: (employee.status || 'ACTIVE').toUpperCase(),
                user_id:
                    (employee as any).system_user?.id ||
                    (employee as any).user_id ||
                    '',
            });
        } else if (!open) {
            clearErrors();
            reset();
        }
    }, [employee, open]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const isEditing = !!employee;

        const url = isEditing
            ? `/workspaces/${workspace.slug}/employees/${employee.id}`
            : `/workspaces/${workspace.slug}/employees`;

        const request = isEditing ? put : post;

        request(url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    isEditing
                        ? 'Employee updated successfully'
                        : 'Employee created successfully',
                );

                if (!isEditing) reset();
                onOpenChange(false);
                onSuccess?.();
            },
            onError: () => {
                toast.error('Failed to save employee. Please check the form.');
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            {/* No overflow-hidden: the assign list has to be able to hang past
                the dialog's own box. The bands below round their own corners. */}
            <DialogContent className="gap-0 border-none p-0 text-left shadow-2xl sm:max-w-md dark:bg-zinc-900">
                {/* Header Section */}
                <div className="border-b border-black/6 px-5 pt-5 pb-4 text-left dark:border-white/6">
                    <DialogHeader className="text-left">
                        <DialogTitle className="text-left text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            Edit Employee Settings
                        </DialogTitle>
                        <DialogDescription className="mt-0.5 text-left text-[12px] text-gray-400 dark:text-gray-500">
                            Update settings for{' '}
                            <span className="font-medium text-gray-900 dark:text-gray-200">
                                {employee?.name}
                            </span>
                            .
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="space-y-5 px-5 py-4">
                        {/* System User Mapping */}
                        <div className="space-y-1.5">
                            <label className="block text-left font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                Assign User
                            </label>
                            <AssignUserSelect
                                users={systemUsers}
                                value={data.user_id ?? ''}
                                onChange={(userId) =>
                                    setData('user_id', userId)
                                }
                            />
                            {errors.user_id && (
                                <p className="mt-1 text-left font-mono text-[11px] text-red-500">
                                    {errors.user_id}
                                </p>
                            )}
                        </div>

                        {/* Status Selection */}
                        <div className="space-y-1.5 text-left">
                            <label className="block text-left font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                Employee Status
                            </label>
                            <select
                                value={data.status ?? 'ACTIVE'}
                                onChange={(e) =>
                                    setData('status', e.target.value)
                                }
                                className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 transition-all outline-none focus:border-emerald-500 dark:border-white/8 dark:bg-zinc-800 dark:text-white"
                            >
                                <option value="ACTIVE">ACTIVE</option>
                                <option value="INACTIVE">INACTIVE</option>
                            </select>
                            {errors.status && (
                                <p className="mt-1 text-left font-mono text-[11px] text-red-500">
                                    {errors.status}
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Footer Actions */}
                    <div className="flex items-center justify-end gap-2 rounded-b-xl border-t border-black/6 bg-stone-50/50 px-5 py-3 dark:border-white/6 dark:bg-white/2">
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
