import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { ChecklistItem } from './types';

type DeleteChecklistDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    item: ChecklistItem | null;
    onConfirm: () => void;
};

export function DeleteChecklistDialog({
    open,
    onOpenChange,
    item,
    onConfirm,
}: DeleteChecklistDialogProps) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent className="rounded-xl border border-black/8 dark:border-white/8">
                <AlertDialogHeader>
                    <AlertDialogTitle className="font-mono text-[14px] uppercase tracking-wide">
                        Delete Checklist Item
                    </AlertDialogTitle>
                    <AlertDialogDescription className="text-[12px] text-gray-500 dark:text-gray-400">
                        {item
                            ? `Are you sure you want to delete "${item.title}"? This action cannot be undone.`
                            : 'This checklist item is no longer available.'}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel className="h-8 rounded-lg text-[12px]">Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        className="h-8 rounded-lg bg-red-600 text-[12px] text-white hover:bg-red-700"
                        onClick={onConfirm}
                    >
                        Delete
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
