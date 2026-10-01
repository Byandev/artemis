import {
    ChecklistProgressItem,
    formatFileSize,
} from '@/components/checklist/types';
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
import { FileText } from 'lucide-react';

type UncheckProofDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    item: ChecklistProgressItem | null;
    onConfirm: () => void;
};

/**
 * Unchecking an item deletes its proof from storage, so it asks first — the
 * file cannot be recovered by ticking the item again.
 */
export function UncheckProofDialog({
    open,
    onOpenChange,
    item,
    onConfirm,
}: UncheckProofDialogProps) {
    const proof = item?.proof;

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent className="rounded-xl border border-black/8 dark:border-white/8">
                <AlertDialogHeader>
                    <AlertDialogTitle className="font-mono text-[14px] tracking-wide uppercase">
                        Uncheck Item
                    </AlertDialogTitle>
                    <AlertDialogDescription className="text-[12px] text-gray-500 dark:text-gray-400">
                        {item
                            ? `"${item.title}" goes back to pending and its proof of completion is deleted from storage. This cannot be undone.`
                            : 'This checklist item is no longer available.'}
                    </AlertDialogDescription>
                </AlertDialogHeader>

                {proof && (
                    <div className="flex items-center gap-2.5 rounded-lg border border-black/8 bg-stone-50 p-2.5 dark:border-white/8 dark:bg-zinc-800/40">
                        <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-black/8 bg-white text-gray-400 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-500">
                            <FileText className="h-4 w-4" />
                        </span>
                        <div className="min-w-0">
                            <p className="truncate font-mono text-[11px] text-gray-700 dark:text-gray-200">
                                {proof.file_name}
                            </p>
                            <p className="font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                {formatFileSize(proof.size)} · will be deleted
                            </p>
                        </div>
                    </div>
                )}

                <AlertDialogFooter>
                    <AlertDialogCancel className="h-8 rounded-lg text-[12px]">
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        className="h-8 rounded-lg bg-red-600 text-[12px] text-white hover:bg-red-700"
                        onClick={onConfirm}
                    >
                        Uncheck and delete
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
