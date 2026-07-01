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
import { format, parseISO } from 'date-fns';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

export interface AdjustCountTarget {
    id: number;
    sku: string;
    product?: { id: number; name: string } | null;
    /** Current displayed remaining stock (ledger + latest adjustment). */
    current_stocks: number | null;
}

interface Props {
    workspace: Workspace;
    item: AdjustCountTarget | null;
    open: boolean;
    onClose: () => void;
}

const inputClass =
    'h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600';
const labelClass =
    'block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1.5';

// Short "30 Jun" label, tolerant of a plain yyyy-MM-dd string.
const shortDate = (d: string) => {
    try {
        return format(parseISO(d), 'd MMM');
    } catch {
        return d;
    }
};

export function AdjustCountDialog({ workspace, item, open, onClose }: Props) {
    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm({
            date: '',
            counted_qty: '',
        });

    // The ledger stock as of the selected date, fetched from the server.
    const [asOf, setAsOf] = useState<number | null>(null);
    const [asOfLoading, setAsOfLoading] = useState(false);

    // Reset the form each time the dialog opens for a new item; default to today.
    useEffect(() => {
        if (!open) return;
        clearErrors();
        setData({
            date: format(new Date(), 'yyyy-MM-dd'),
            counted_qty: '',
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, item?.id]);

    const dateValid = /^\d{4}-\d{2}-\d{2}$/.test(data.date);

    // Whenever the item or the chosen date changes, load the system's stock as of that
    // date — the count is measured against it. A stale response can't clobber a newer one.
    useEffect(() => {
        if (!open || !item || !dateValid) {
            setAsOf(null);
            return;
        }
        let cancelled = false;
        setAsOfLoading(true);
        fetch(
            `/workspaces/${workspace.slug}/inventory/items/${item.id}/stock-as-of?date=${data.date}`,
            { headers: { Accept: 'application/json' } },
        )
            .then((r) => (r.ok ? r.json() : Promise.reject(r)))
            .then((json) => {
                if (!cancelled) setAsOf(json.remaining_qty ?? null);
            })
            .catch(() => {
                if (!cancelled) setAsOf(null);
            })
            .finally(() => {
                if (!cancelled) setAsOfLoading(false);
            });
        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, item?.id, data.date, dateValid, workspace.slug]);

    const countedValid =
        data.counted_qty !== '' &&
        Number.isInteger(Number(data.counted_qty)) &&
        Number(data.counted_qty) >= 0;
    const disabled = processing || !dateValid || !countedValid;

    const counted = countedValid ? Number(data.counted_qty) : null;
    // Discrepancy that will take effect = count − stock as of the date (0 if none).
    const change = counted !== null ? counted - (asOf ?? 0) : null;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (disabled || !item) return;

        post(
            `/workspaces/${workspace.slug}/inventory/items/${item.id}/discrepancies`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Stock count recorded');
                    reset();
                    onClose();
                },
                onError: () =>
                    toast.error('Please check the form and try again.'),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="max-w-[420px] border-none shadow-2xl dark:bg-zinc-900">
                <DialogHeader>
                    <DialogTitle className="text-[16px] font-semibold text-gray-900 dark:text-gray-100">
                        Adjust Count
                    </DialogTitle>
                    <DialogDescription className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                        {item ? (
                            <>
                                Record a physical count for{' '}
                                <span className="font-mono text-gray-700 dark:text-gray-300">
                                    {item.sku}
                                </span>
                                .
                            </>
                        ) : (
                            'Record a physical count for this item.'
                        )}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className={labelClass}>
                            Count Date <span className="text-red-400">*</span>
                        </label>
                        {/* key forces a fresh flatpickr instance per item */}
                        <DatePicker
                            key={`adjust-date-${item?.id ?? 'new'}-${open}`}
                            id="adjust-count-date"
                            mode="single"
                            defaultDate={data.date || undefined}
                            onChange={(dates) =>
                                setData(
                                    'date',
                                    dates.length
                                        ? format(dates[0], 'yyyy-MM-dd')
                                        : '',
                                )
                            }
                        />
                        {errors.date && (
                            <p className="mt-1 font-mono text-[11px] text-red-500">
                                {errors.date}
                            </p>
                        )}
                        <p className="mt-1.5 font-mono text-[11px] text-gray-500 dark:text-gray-400">
                            System count as of{' '}
                            {dateValid ? shortDate(data.date) : 'this date'}:{' '}
                            <span className="font-medium text-violet-600 dark:text-violet-400">
                                {asOfLoading
                                    ? '…'
                                    : asOf === null
                                      ? '—'
                                      : asOf.toLocaleString()}
                            </span>
                        </p>
                    </div>

                    <div>
                        <label className={labelClass}>
                            Counted Quantity{' '}
                            <span className="text-red-400">*</span>
                        </label>
                        <input
                            type="number"
                            min="0"
                            step="1"
                            value={data.counted_qty}
                            onChange={(e) =>
                                setData('counted_qty', e.target.value)
                            }
                            placeholder="0"
                            className={inputClass}
                        />
                        {errors.counted_qty && (
                            <p className="mt-1 font-mono text-[11px] text-red-500">
                                {errors.counted_qty}
                            </p>
                        )}
                        {change !== null && (
                            <p className="mt-1.5 font-mono text-[11px] text-gray-500 dark:text-gray-400">
                                Discrepancy taking effect:{' '}
                                <span
                                    className={
                                        change > 0
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : change < 0
                                              ? 'text-red-500 dark:text-red-400'
                                              : 'text-gray-500 dark:text-gray-400'
                                    }
                                >
                                    {change > 0 ? '+' : ''}
                                    {change.toLocaleString()}
                                </span>
                            </p>
                        )}
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
                            {processing ? 'Saving…' : 'Record Count'}
                        </button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
