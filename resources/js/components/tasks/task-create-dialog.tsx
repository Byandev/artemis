import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { X } from 'lucide-react';
import { useMemo } from 'react';
import Select, { MultiValue, StylesConfig } from 'react-select';
import { TaskFormState, WorkspaceMember } from './types';

interface AssigneeOption {
    label: string;
    value: number;
    email: string;
}

interface TaskCreateDialogProps {
    form: TaskFormState;
    isCreating: boolean;
    mode?: 'create' | 'edit';
    onCancel: () => void;
    onChange: (form: TaskFormState) => void;
    onOpenChange: (open: boolean) => void;
    onSubmit: () => void;
    open: boolean;
    workspaceMembers: WorkspaceMember[];
}

export function TaskCreateDialog({
    form,
    isCreating,
    mode = 'create',
    onCancel,
    onChange,
    onOpenChange,
    onSubmit,
    open,
    workspaceMembers,
}: TaskCreateDialogProps) {
    const isEdit = mode === 'edit';

    const assigneeOptions = useMemo(
        () =>
            workspaceMembers.map((member) => ({
                label: member.name,
                value: member.id,
                email: member.email,
            })),
        [workspaceMembers],
    );

    const selectedAssignees = useMemo(
        () =>
            assigneeOptions.filter((option) =>
                form.assignees.includes(option.value),
            ),
        [assigneeOptions, form.assignees],
    );

    const selectStyles: StylesConfig<AssigneeOption, true> = {
        control: (base, state) => ({
            ...base,
            minHeight: 40,
            borderRadius: 8,
            borderColor: state.isFocused
                ? 'rgb(16 185 129)'
                : 'rgb(0 0 0 / 0.06)',
            backgroundColor: 'rgb(245 245 244)',
            boxShadow: state.isFocused
                ? '0 0 0 2px rgb(16 185 129 / 0.2)'
                : 'none',
            fontFamily: 'monospace',
            fontSize: 12,
            ':hover': {
                borderColor: state.isFocused
                    ? 'rgb(16 185 129)'
                    : 'rgb(0 0 0 / 0.1)',
            },
        }),
        valueContainer: (base) => ({
            ...base,
            gap: 4,
            padding: '4px 8px',
        }),
        multiValue: (base) => ({
            ...base,
            alignItems: 'center',
            borderRadius: 999,
            backgroundColor: 'white',
            overflow: 'hidden',
        }),
        multiValueLabel: (base) => ({
            ...base,
            color: 'rgb(55 65 81)',
            fontSize: 11,
            padding: '3px 6px',
        }),
        multiValueRemove: (base) => ({
            ...base,
            color: 'rgb(107 114 128)',
            ':hover': {
                backgroundColor: 'rgb(254 226 226)',
                color: 'rgb(190 18 60)',
            },
        }),
        menu: (base) => ({
            ...base,
            zIndex: 60,
            borderRadius: 10,
            overflow: 'hidden',
            border: '1px solid rgb(0 0 0 / 0.08)',
            boxShadow: '0 18px 40px rgb(0 0 0 / 0.14)',
        }),
        option: (base, state) => ({
            ...base,
            display: 'flex',
            alignItems: 'center',
            minHeight: 38,
            backgroundColor: state.isSelected
                ? 'rgb(16 185 129)'
                : state.isFocused
                  ? 'rgb(245 245 244)'
                  : 'white',
            color: state.isSelected ? 'white' : 'rgb(55 65 81)',
            fontFamily: 'monospace',
            fontSize: 12,
        }),
        placeholder: (base) => ({
            ...base,
            color: 'rgb(156 163 175)',
            fontSize: 12,
        }),
        input: (base) => ({ ...base, color: 'rgb(55 65 81)' }),
        indicatorSeparator: () => ({ display: 'none' }),
    };

    const updateAssignees = (selected: MultiValue<AssigneeOption>) => {
        onChange({
            ...form,
            assignees: selected.map((option) => option.value),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-lg gap-0 rounded-xl border border-black/8 p-0 dark:border-white/8 [&_[data-default-close=true]]:hidden">
                <DialogHeader className="space-y-0 border-b border-black/6 px-5 py-3 text-left dark:border-white/8">
                    <div className="flex items-start justify-between gap-3">
                        <div className="space-y-0.5">
                            <DialogTitle className="font-mono text-[16px] leading-none tracking-wide text-gray-800 uppercase dark:text-gray-100">
                                {isEdit ? 'Edit Task' : 'Create Task'}
                            </DialogTitle>
                            <DialogDescription className="text-[11px] leading-none text-gray-500 dark:text-gray-300">
                                {isEdit
                                    ? 'Update task details and assigned members.'
                                    : 'Add task details and assign workspace members.'}
                            </DialogDescription>
                        </div>
                        <DialogClose className="z-10 mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-black/5 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-white/6 dark:hover:text-gray-300">
                            <X className="h-4 w-4" />
                            <span className="sr-only">Close</span>
                        </DialogClose>
                    </div>
                </DialogHeader>

                <div className="space-y-3.5 px-5 py-3.5">
                    <div className="space-y-1.5">
                        <Label className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                            Name
                        </Label>
                        <Input
                            value={form.name}
                            onChange={(event) =>
                                onChange({ ...form, name: event.target.value })
                            }
                            placeholder="Task name"
                            className="h-9 rounded-lg border-black/6 bg-stone-100 font-mono! text-[12px]! placeholder:text-gray-400 focus-visible:border-emerald-500 focus-visible:ring-2 focus-visible:ring-emerald-500/20 dark:border-white/8 dark:bg-zinc-800 dark:placeholder:text-gray-500 dark:focus-visible:border-emerald-400 dark:focus-visible:ring-emerald-400/20"
                        />
                    </div>

                    <div className="space-y-1.5">
                        <Label className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                            Description
                        </Label>
                        <Textarea
                            value={form.description}
                            onChange={(event) =>
                                onChange({
                                    ...form,
                                    description: event.target.value,
                                })
                            }
                            placeholder="Add context"
                            className="min-h-24 resize-y rounded-lg border-black/6 bg-stone-100 font-mono! text-[12px]! placeholder:text-gray-400 focus-visible:border-emerald-500 focus-visible:ring-2 focus-visible:ring-emerald-500/20 dark:border-white/8 dark:bg-zinc-800 dark:placeholder:text-gray-500 dark:focus-visible:border-emerald-400 dark:focus-visible:ring-emerald-400/20"
                        />
                    </div>

                    <div className="space-y-1.5">
                        <Label className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                            Due Date
                        </Label>
                        <Input
                            type="date"
                            value={form.dueDate}
                            onClick={(event) => {
                                event.currentTarget.showPicker?.();
                            }}
                            onChange={(event) =>
                                onChange({
                                    ...form,
                                    dueDate: event.target.value,
                                })
                            }
                            className="h-10 cursor-pointer rounded-lg border-black/6 bg-stone-100 font-mono! text-[12px]! [color-scheme:light] focus-visible:border-emerald-500 focus-visible:ring-2 focus-visible:ring-emerald-500/20 dark:border-white/8 dark:bg-zinc-800 dark:[color-scheme:dark] dark:focus-visible:border-emerald-400 dark:focus-visible:ring-emerald-400/20"
                        />
                    </div>

                    <div className="space-y-1.5">
                        <Label className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                            Assignees
                        </Label>
                        <Select
                            isMulti
                            isClearable
                            value={selectedAssignees}
                            options={assigneeOptions}
                            onChange={updateAssignees}
                            isDisabled={workspaceMembers.length === 0}
                            placeholder={
                                workspaceMembers.length === 0
                                    ? 'No members yet'
                                    : 'Select assignees'
                            }
                            closeMenuOnSelect={false}
                            hideSelectedOptions={false}
                            blurInputOnSelect={false}
                            noOptionsMessage={() => 'No members found'}
                            styles={selectStyles}
                            formatOptionLabel={(option) => (
                                <div className="flex min-w-0 items-center gap-2">
                                    <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-stone-200 text-[10px] font-semibold text-gray-600">
                                        {option.label.charAt(0).toUpperCase()}
                                    </span>
                                    <span className="min-w-0">
                                        <span className="block truncate">
                                            {option.label}
                                        </span>
                                        <span className="block truncate text-[10px] opacity-60">
                                            {option.email}
                                        </span>
                                    </span>
                                </div>
                            )}
                        />
                    </div>
                </div>

                <DialogFooter className="flex-row items-center gap-2 border-t border-black/6 px-5 py-3 dark:border-white/8">
                    <button
                        type="button"
                        onClick={onCancel}
                        className="flex h-8 flex-1 items-center justify-center rounded-lg border border-black/8 bg-stone-100 px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-200 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={onSubmit}
                        disabled={isCreating}
                        className="flex h-8 flex-1 items-center justify-center rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                    >
                        {isEdit ? 'Update' : 'Save'}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
