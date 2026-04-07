import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { Trash2 } from 'lucide-react';

interface PurchasedOrderLike {
    id: number;
    delivery_no: string | null;
    control_no: string | null;
}

interface DeleteItemDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    rowToDelete: PurchasedOrderLike | null;
    deleteSubmitting: boolean;
    onConfirmDelete: () => void;
    onCancel: () => void;
    sansFont: string;
}

export function DeleteItemDialog({
    open,
    onOpenChange,
    rowToDelete,
    deleteSubmitting,
    onConfirmDelete,
    onCancel,
    sansFont,
}: DeleteItemDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                hideClose
                className="max-w-[400px] rounded-2xl border border-black/8 bg-white p-0 shadow-[0_16px_40px_rgba(0,0,0,0.1)] dark:border-white/10 dark:bg-zinc-900"
                style={{ fontFamily: sansFont }}
            >
                <DialogTitle className="sr-only">Delete Item</DialogTitle>
                <DialogDescription className="sr-only">Confirm deleting the selected purchase order record.</DialogDescription>
                <div className="px-6 py-5 text-center">
                    <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-50 text-red-500 dark:bg-red-500/10">
                        <Trash2 className="h-7 w-7" />
                    </div>
                    <h3 className="mt-3 text-[20px] font-semibold tracking-tight text-gray-900 dark:text-gray-100">Delete Item</h3>
                    <p className="mt-1 text-[12px] text-gray-500 dark:text-gray-400">Are you sure you want to delete this purchase order?</p>
                    <p className="mt-1 text-[13px] font-medium text-gray-900 dark:text-gray-100">{rowToDelete?.control_no || rowToDelete?.delivery_no || 'N/A'}</p>
                    <p className="mt-1 text-[12px] text-gray-400 dark:text-gray-500">This action cannot be undone.</p>

                    <div className="mt-5 flex items-center justify-center gap-3">
                        <button
                            type="button"
                            onClick={onCancel}
                            className="h-10 min-w-24 rounded-lg border border-black/8 bg-white px-5 text-[13px] font-medium text-gray-600 transition-colors hover:bg-black/3 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-300 dark:hover:bg-white/5"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            disabled={deleteSubmitting}
                            onClick={onConfirmDelete}
                            className="h-10 min-w-24 rounded-lg bg-red-600 px-5 text-[13px] font-medium text-white transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-red-500 dark:hover:bg-red-400"
                        >
                            {deleteSubmitting ? 'Deleting...' : 'Delete'}
                        </button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
