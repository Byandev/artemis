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
import { purchasedOrderStatusLabel } from '@/constants/purchased-order-statuses';

export interface CloseOrderTarget {
    /** Undelivered units still owed across the order's line items. */
    balance: number;
    /** The terminal status being moved to (Delivered or Cancelled). */
    status: number;
    control_no: string | null;
}

interface Props {
    target: CloseOrderTarget | null;
    processing?: boolean;
    onConfirm: () => void;
    onClose: () => void;
}

/**
 * Confirms closing a purchase order that still owes stock. Closing drops the
 * outstanding units out of incoming-stock and reorder figures, which is silent
 * and easy to do by accident — so it is spelled out rather than just allowed.
 * The server enforces the same rule independently (see
 * PurchaseOrderMonitoringController::updateStatus).
 */
export function CloseOrderDialog({
    target,
    processing = false,
    onConfirm,
    onClose,
}: Props) {
    return (
        <AlertDialog
            open={!!target}
            onOpenChange={(open) => !open && onClose()}
        >
            <AlertDialogContent className="max-w-[420px] border-none shadow-2xl dark:bg-zinc-900">
                <AlertDialogHeader>
                    <AlertDialogTitle className="text-[16px] font-semibold text-gray-900 dark:text-gray-100">
                        Close this order short?
                    </AlertDialogTitle>
                    <AlertDialogDescription className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                        This order
                        {target?.control_no ? (
                            <>
                                {' '}
                                (Control No.{' '}
                                <span className="font-mono text-emerald-600 dark:text-emerald-400">
                                    {target.control_no}
                                </span>
                                )
                            </>
                        ) : (
                            ''
                        )}{' '}
                        still has{' '}
                        <span className="font-mono font-medium text-amber-600 dark:text-amber-400">
                            {(target?.balance ?? 0).toLocaleString()} unit
                            {target?.balance === 1 ? '' : 's'}
                        </span>{' '}
                        undelivered.
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <div className="rounded-[10px] border border-amber-200 bg-amber-50 px-3.5 py-3 dark:border-amber-900/50 dark:bg-amber-950/40">
                    <p className="font-mono text-[11px] leading-relaxed text-amber-800 dark:text-amber-300">
                        Marking it &ldquo;
                        {target ? purchasedOrderStatusLabel(target.status) : ''}
                        &rdquo; removes those units from Waiting for Delivery,
                        which raises PO Needed and lowers Days It Can Last on
                        the affected items.
                    </p>
                </div>

                <AlertDialogFooter className="mt-4 gap-2">
                    <AlertDialogCancel
                        disabled={processing}
                        className="h-9 rounded-lg border-black/8 bg-white px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                    >
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        onClick={(e) => {
                            e.preventDefault();
                            onConfirm();
                        }}
                        disabled={processing}
                        className="h-9 rounded-lg bg-amber-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-amber-700 disabled:opacity-50"
                    >
                        {processing ? 'Closing...' : 'Close Anyway'}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
