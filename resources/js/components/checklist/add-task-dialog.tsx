import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { AddTaskForm } from './types';

type AddTaskDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: 'add' | 'edit';
    form: AddTaskForm;
    setForm: React.Dispatch<React.SetStateAction<AddTaskForm>>;
    onSubmit: () => void;
    onCancel: () => void;
};

export function AddTaskDialog({
    open,
    onOpenChange,
    mode,
    form,
    setForm,
    onSubmit,
    onCancel,
}: AddTaskDialogProps) {
    const isFormValid = form.title.trim().length > 0 && form.target !== '';
    const isEdit = mode === 'edit';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md gap-0 rounded-xl border border-black/8 p-0 dark:border-white/8">
                <DialogHeader className="space-y-0 border-b border-black/6 px-5 py-3 text-left dark:border-white/8">
                    <DialogTitle className="font-mono text-[16px] leading-none uppercase tracking-wide text-gray-800 dark:text-gray-100">
                        {isEdit ? 'Edit Checklist' : 'Create Checklist'}
                    </DialogTitle>
                    <DialogDescription className="-mt-0.5 text-[11px] leading-none text-gray-500 dark:text-gray-300">
                        {isEdit
                            ? 'Update the details below to edit checklist'
                            : 'Fill in the details below to create checklist'}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-3.5 px-5 py-3.5">
                    <div className="space-y-1.5">
                        <Label className="font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                            Title
                        </Label>
                        <Input
                            value={form.title}
                            onChange={(e) => {
                                setForm((prev) => ({ ...prev, title: e.target.value }));
                            }}
                            placeholder="Example Title"
                            className="h-9 rounded-lg border-black/6 bg-stone-100 font-mono! text-[12px]! placeholder:text-gray-400 focus-visible:border-emerald-500 focus-visible:ring-2 focus-visible:ring-emerald-500/20 dark:border-white/8 dark:bg-zinc-800 dark:placeholder:text-gray-500 dark:focus-visible:border-emerald-400 dark:focus-visible:ring-emerald-400/20"
                        />
                    </div>

                    <div className="space-y-1.5">
                        <Label className="font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                            Target
                        </Label>
                        <Select
                            value={form.target}
                            onValueChange={(value: 'Shop' | 'Page') => {
                                setForm((prev) => ({ ...prev, target: value }));
                            }}
                        >
                            <SelectTrigger className="h-9 rounded-lg border-black/6 bg-stone-100 font-mono! text-[12px]! data-placeholder:text-gray-400 focus:ring-2 focus:ring-emerald-500/20 dark:border-white/8 dark:bg-zinc-800 dark:data-placeholder:text-gray-500 dark:focus:ring-emerald-400/20">
                                <SelectValue placeholder="Select" />
                            </SelectTrigger>
                            <SelectContent className="font-mono! text-[12px]!">
                                <SelectItem value="Shop">Shop</SelectItem>
                                <SelectItem value="Page">Page</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="flex items-center gap-2 pt-1">
                        <Switch
                            className="data-[state=checked]:bg-emerald-600 dark:data-[state=checked]:bg-emerald-500"
                            checked={form.required}
                            onCheckedChange={(checked) => {
                                setForm((prev) => ({ ...prev, required: Boolean(checked) }));
                            }}
                        />
                        <Label className="font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                            Required
                        </Label>
                    </div>
                </div>

                <DialogFooter className="border-t border-black/6 px-5 py-3 sm:justify-end dark:border-white/8">
                    <button
                        type="button"
                        className="flex h-8 items-center rounded-lg border border-black/8 bg-stone-100 px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-200 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                        onClick={onCancel}
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        className="flex h-8 items-center rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                        onClick={onSubmit}
                        disabled={!isFormValid}
                    >
                        {isEdit ? 'Update' : 'Save'}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
