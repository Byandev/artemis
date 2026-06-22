import DatePicker from '@/components/ui/date-picker';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Workspace } from '@/types/models/Workspace';
import { useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { useEffect } from 'react';
import { toast } from 'sonner';

export interface DeliveryTarget {
    id: number;
    delivery_date: string;
    delivery_no: string | null;
    qty: number;
}

interface Props {
    workspace: Workspace;
    /** The PO item the delivery belongs to (used for context + create endpoint). */
    itemId: number | null;
    /** Ordered count for the item, to surface the remaining balance. */
    itemBalance?: number;
    /** When set the dialog edits an existing delivery; otherwise it records a new one. */
    delivery?: DeliveryTarget | null;
    open: boolean;
    onClose: () => void;
}

const inputClass =
    'h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600';
const labelClass =
    'block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1.5';

export function RecordDeliveryDialog({
    workspace,
    itemId,
    itemBalance,
    delivery,
    open,
    onClose,
}: Props) {
    const isEdit = Boolean(delivery);

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm({
            delivery_date: '',
            delivery_no: '',
            qty: '',
        });

    // Sync form state whenever the dialog opens for a new target.
    useEffect(() => {
        if (!open) return;
        clearErrors();
        setData({
            delivery_date: delivery?.delivery_date?.slice(0, 10) ?? '',
            delivery_no: delivery?.delivery_no ?? '',
            qty: delivery ? String(delivery.qty) : '',
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, delivery?.id]);

    const base = `/workspaces/${workspace.slug}/inventory/po-monitoring`;
    const dateValid = /^\d{4}-\d{2}-\d{2}$/.test(data.delivery_date);
    const qtyValid = Number.isFinite(Number(data.qty)) && Number(data.qty) >= 1;
    const disabled = processing || !dateValid || !qtyValid;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (disabled) return;

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    isEdit ? 'Delivery updated' : 'Delivery recorded',
                );
                reset();
                onClose();
            },
            onError: () => toast.error('Please check the form and try again.'),
        };

        if (isEdit && delivery) {
            put(`${base}/deliveries/${delivery.id}`, options);
        } else if (itemId) {
            post(`${base}/items/${itemId}/deliveries`, options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="max-w-[420px] border-none shadow-2xl dark:bg-zinc-900">
                <DialogHeader>
                    <DialogTitle className="text-[16px] font-semibold text-gray-900 dark:text-gray-100">
                        {isEdit ? 'Edit Delivery' : 'Record Delivery'}
                    </DialogTitle>
                    <DialogDescription className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                        {typeof itemBalance === 'number' && !isEdit ? (
                            <>
                                Remaining balance:{' '}
                                <span className="font-mono text-emerald-600 dark:text-emerald-400">
                                    {itemBalance.toLocaleString()}
                                </span>{' '}
                                unit{itemBalance === 1 ? '' : 's'}.
                            </>
                        ) : (
                            'Log a delivery attempt against this purchase order item.'
                        )}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className={labelClass}>
                            Delivery Date{' '}
                            <span className="text-red-400">*</span>
                        </label>
                        {/* key forces a fresh flatpickr instance per target */}
                        <DatePicker
                            key={`del-date-${delivery?.id ?? 'new'}-${open}`}
                            id="delivery-date"
                            mode="single"
                            defaultDate={data.delivery_date || undefined}
                            onChange={(dates) =>
                                setData(
                                    'delivery_date',
                                    dates.length
                                        ? format(dates[0], 'yyyy-MM-dd')
                                        : '',
                                )
                            }
                        />
                        {errors.delivery_date && (
                            <p className="mt-1 font-mono text-[11px] text-red-500">
                                {errors.delivery_date}
                            </p>
                        )}
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className={labelClass}>Delivery No.</label>
                            <input
                                type="text"
                                value={data.delivery_no}
                                onChange={(e) =>
                                    setData('delivery_no', e.target.value)
                                }
                                placeholder="DR-001"
                                className={inputClass}
                            />
                            {errors.delivery_no && (
                                <p className="mt-1 font-mono text-[11px] text-red-500">
                                    {errors.delivery_no}
                                </p>
                            )}
                        </div>
                        <div>
                            <label className={labelClass}>
                                Quantity <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="number"
                                min="1"
                                value={data.qty}
                                onChange={(e) => setData('qty', e.target.value)}
                                placeholder="0"
                                className={inputClass}
                            />
                            {errors.qty && (
                                <p className="mt-1 font-mono text-[11px] text-red-500">
                                    {errors.qty}
                                </p>
                            )}
                        </div>
                    </div>

                    <DialogFooter className="mt-2 gap-2">
                        <button
                            type="button"
                            onClick={onClose}
                            disabled={processing}
                            className="h-9 rounded-lg border border-black/8 bg-white px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={disabled}
                            className="h-9 rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                        >
                            {processing
                                ? 'Saving…'
                                : isEdit
                                  ? 'Save Changes'
                                  : 'Record Delivery'}
                        </button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
