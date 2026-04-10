import { Button } from '@/components/ui/button';
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
    form: AddTaskForm;
    setForm: React.Dispatch<React.SetStateAction<AddTaskForm>>;
    onSubmit: () => void;
    onCancel: () => void;
};

export function AddTaskDialog({
    open,
    onOpenChange,
    form,
    setForm,
    onSubmit,
    onCancel,
}: AddTaskDialogProps) {
    const isFormValid = form.title.trim().length > 0 && form.target !== '';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md gap-0 rounded-xl border border-black/8 p-0 dark:border-white/8">
                <DialogHeader className="border-b border-black/6 px-5 py-4 text-left dark:border-white/8">
                    <DialogTitle className="font-mono text-[16px] uppercase tracking-wide text-gray-800 dark:text-gray-100">
                        Create Checklist
                    </DialogTitle>
                    <DialogDescription className="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                        Fill in the details below to create checklist
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 px-5 py-4">
                    <div className="space-y-1.5">
                        <Label className="font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">
                            Title
                        </Label>
                        <Input
                            value={form.title}
                            onChange={(e) => {
                                setForm((prev) => ({ ...prev, title: e.target.value }));
                            }}
                            placeholder="Example Title"
                            className="h-9 rounded-lg border-black/6 bg-stone-100 text-[12px] placeholder:text-gray-300 dark:border-white/8 dark:bg-zinc-800"
                        />
                    </div>

                    <div className="space-y-1.5">
                        <Label className="font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">
                            Target
                        </Label>
                        <Select
                            value={form.target}
                            onValueChange={(value: 'Shop' | 'Page') => {
                                setForm((prev) => ({ ...prev, target: value }));
                            }}
                        >
                            <SelectTrigger className="h-9 rounded-lg border-black/6 bg-stone-100 text-[12px] dark:border-white/8 dark:bg-zinc-800">
                                <SelectValue placeholder="Select" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="Shop">Shop</SelectItem>
                                <SelectItem value="Page">Page</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="flex items-center gap-2 pt-1">
                        <Switch
                            checked={form.required}
                            onCheckedChange={(checked) => {
                                setForm((prev) => ({ ...prev, required: Boolean(checked) }));
                            }}
                        />
                        <Label className="font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">
                            Required
                        </Label>
                    </div>
                </div>

                <DialogFooter className="border-t border-black/6 px-5 py-3 sm:justify-end dark:border-white/8">
                    <Button
                        type="button"
                        variant="outline"
                        className="h-8 rounded-lg border-black/8 px-4 text-[12px] text-gray-600 dark:border-white/10 dark:text-gray-300"
                        onClick={onCancel}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        className="h-8 rounded-lg bg-emerald-600 px-4 text-[12px] text-white hover:bg-emerald-700 disabled:opacity-50"
                        onClick={onSubmit}
                        disabled={!isFormValid}
                    >
                        Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
