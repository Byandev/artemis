import DatePicker from '@/components/ui/date-picker';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { InventoryTransaction } from '@/types/models/InventoryTransaction';
import { Workspace } from '@/types/models/Workspace';
import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { format } from 'date-fns';
import { X } from 'lucide-react';
import React, { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

interface InventoryItem {
    id: number;
    sku: string;
    product?: {
        id: number;
        name: string;
    };
}

interface Props {
    workspace: Workspace;
    inventory?: InventoryTransaction;
    items?: InventoryItem[];
    open: boolean;
    onOpenChange: (value: boolean) => void;
    onSuccess?: () => void;
}

const InventoryFormDialog = ({
    workspace,
    open,
    onOpenChange,
    inventory,
    items = [],
    onSuccess,
}: Props) => {
    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
        patch,
        clearErrors,
    } = useForm({
        inventory_item_id: '',
        date: '',
        ref_no: '',
        po_qty_in: 0,
        po_qty_out: 0,
        rts_goods_in: 0,
        rts_goods_out: 0,
        rts_bad: 0,
        lost: 0,
        remaining_qty: 0,
    });

    const isEditing = useMemo(() => !!inventory, [inventory]);

    // Carry-over remaining quantity from the last transaction dated before the
    // selected date for the chosen item. `null` = not yet loaded (don't auto-compute).
    const [baseRemaining, setBaseRemaining] = useState<number | null>(null);
    const [baseRemainingLoading, setBaseRemainingLoading] = useState(false);
    const [baseRemainingFound, setBaseRemainingFound] = useState(false);

    // Fetch the last remaining quantity whenever the item or date changes.
    useEffect(() => {
        if (!open || !data.inventory_item_id || !data.date) {
            setBaseRemaining(null);
            setBaseRemainingFound(false);
            return;
        }

        const controller = new AbortController();
        setBaseRemainingLoading(true);

        axios
            .get(
                `/workspaces/${workspace.slug}/inventory/transactions/last-remaining`,
                {
                    params: {
                        inventory_item_id: data.inventory_item_id,
                        date: data.date,
                        exclude_id: inventory?.id,
                    },
                    signal: controller.signal,
                },
            )
            .then((res) => {
                setBaseRemaining(Number(res.data.remaining_qty) || 0);
                setBaseRemainingFound(!!res.data.found);
            })
            .catch((err) => {
                if (!axios.isCancel(err)) {
                    setBaseRemaining(0);
                    setBaseRemainingFound(false);
                }
            })
            .finally(() => setBaseRemainingLoading(false));

        return () => controller.abort();
    }, [open, data.inventory_item_id, data.date, inventory?.id, workspace.slug]);

    // Auto-compute the remaining quantity from the carry-over plus this entry's flows:
    // last remaining + PO in + RTS goods in − PO out − RTS goods out − RTS bad − loss.
    const computedRemaining = useMemo(() => {
        if (baseRemaining === null) return null;
        return (
            baseRemaining +
            data.po_qty_in +
            data.rts_goods_in -
            data.po_qty_out -
            data.rts_goods_out -
            data.rts_bad -
            data.lost
        );
    }, [
        baseRemaining,
        data.po_qty_in,
        data.rts_goods_in,
        data.po_qty_out,
        data.rts_goods_out,
        data.rts_bad,
        data.lost,
    ]);

    useEffect(() => {
        if (computedRemaining !== null) {
            setData('remaining_qty', computedRemaining);
        }
    }, [computedRemaining]);

    useEffect(() => {
        if (inventory) {
            setData({
                inventory_item_id:
                    inventory.inventory_item_id?.toString() ?? '',
                date: inventory.date || '',
                ref_no: inventory.ref_no || '',
                po_qty_in: inventory.po_qty_in || 0,
                po_qty_out: inventory.po_qty_out || 0,
                rts_goods_in: inventory.rts_goods_in || 0,
                rts_goods_out: inventory.rts_goods_out || 0,
                rts_bad: inventory.rts_bad || 0,
                lost: inventory.lost || 0,
                remaining_qty: inventory.remaining_qty || 0,
            });
        } else {
            reset();
            clearErrors();
        }
    }, [inventory, open]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const url = isEditing
            ? `/workspaces/${workspace.slug}/inventory/transactions/${inventory?.id}`
            : `/workspaces/${workspace.slug}/inventory/transactions`;

        const request = isEditing ? patch : post;

        request(url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    isEditing
                        ? 'Transaction updated successfully'
                        : 'New transaction logged successfully',
                );

                reset();
                clearErrors();
                onOpenChange(false);
            },

            onError: () =>
                toast.error(
                    'Failed to save transaction. Please check the form.',
                ),
        });
    };

    const inputClass =
        'h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400';
    const labelClass =
        'block font-mono! text-[10px]! font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500';

    // Helper to ensure values are non-negative integers
    const handleNumericChange = (key: keyof typeof data, value: string) => {
        const parsed = parseInt(value) || 0;
        setData(key as any, Math.max(0, parsed));
    };

    // Display 0 as an empty field with a "0" placeholder so it can be cleared
    // and typed over without having to select the zero first.
    const numValue = (v: number) => (v === 0 ? '' : v);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-xl [&_[data-default-close=true]]:hidden">
                <div className="relative border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            {isEditing
                                ? 'Edit Transaction'
                                : 'Log New Transaction'}
                        </DialogTitle>
                        <DialogDescription className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                            {isEditing
                                ? 'Update the details for this inventory record'
                                : 'Add a new entry to the inventory transaction log'}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogClose className="absolute top-4 right-4 inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-black/5 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-white/6 dark:hover:text-gray-300">
                        <X className="h-4 w-4" />
                        <span className="sr-only">Close</span>
                    </DialogClose>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="max-h-[60vh] items-center justify-center space-y-4 overflow-y-auto px-5 py-4">
                        {/* Inventory Item Selector */}
                        <div className="space-y-1.5">
                            <label className={labelClass}>
                                Inventory Item{' '}
                                <span className="text-red-400">*</span>
                            </label>
                            <select
                                value={data.inventory_item_id}
                                onChange={(e) =>
                                    setData('inventory_item_id', e.target.value)
                                }
                                className={inputClass}
                            >
                                <option value="">
                                    Select an inventory item...
                                </option>
                                {items.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.sku}
                                        {item.product
                                            ? ` — ${item.product.name}`
                                            : ''}
                                    </option>
                                ))}
                            </select>
                            {errors.inventory_item_id && (
                                <p className="text-[11px] text-red-500">
                                    {errors.inventory_item_id}
                                </p>
                            )}
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="items-center justify-center space-y-1.5">
                                <label className={labelClass}>
                                    Transaction Date{' '}
                                    <span className="text-red-400">*</span>
                                </label>
                                <DatePicker
                                    id="inventory-transaction-date"
                                    mode="single"
                                    defaultDate={data.date || undefined}
                                    onChange={(dates) => {
                                        if (dates.length) {
                                            setData(
                                                'date',
                                                format(dates[0], 'yyyy-MM-dd'),
                                            );
                                        } else {
                                            setData('date', '');
                                        }
                                    }}
                                />
                                {errors.date && (
                                    <p className="text-[11px] text-red-500">
                                        {errors.date}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-1.5">
                                <label className={labelClass}>
                                    Reference No.{' '}
                                    <span className="text-red-400">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={data.ref_no}
                                    onChange={(e) =>
                                        setData('ref_no', e.target.value)
                                    }
                                    placeholder="PO-001"
                                    className={inputClass}
                                />
                                {errors.ref_no && (
                                    <p className="text-[11px] text-red-500">
                                        {errors.ref_no}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-1.5">
                                <label className={labelClass}>
                                    PO Quantity In{' '}
                                    <span className="text-red-400">*</span>
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    value={numValue(data.po_qty_in)}
                                    placeholder="0"
                                    onChange={(e) =>
                                        handleNumericChange(
                                            'po_qty_in',
                                            e.target.value,
                                        )
                                    }
                                    className={inputClass}
                                />
                                {errors.po_qty_in && (
                                    <p className="text-[11px] text-red-500">
                                        {errors.po_qty_in}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-1.5">
                                <label className={labelClass}>
                                    PO Quantity Out{' '}
                                    <span className="text-red-400">*</span>
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    value={numValue(data.po_qty_out)}
                                    placeholder="0"
                                    onChange={(e) =>
                                        handleNumericChange(
                                            'po_qty_out',
                                            e.target.value,
                                        )
                                    }
                                    className={inputClass}
                                />
                                {errors.po_qty_out && (
                                    <p className="text-[11px] text-red-500">
                                        {errors.po_qty_out}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-1.5">
                                <label className={labelClass}>
                                    Rts Goods In{' '}
                                    <span className="text-red-400">*</span>
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    value={numValue(data.rts_goods_in)}
                                    placeholder="0"
                                    onChange={(e) =>
                                        handleNumericChange(
                                            'rts_goods_in',
                                            e.target.value,
                                        )
                                    }
                                    className={inputClass}
                                />
                                {errors.rts_goods_in && (
                                    <p className="text-[11px] text-red-500">
                                        {errors.rts_goods_in}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-1.5">
                                <label className={labelClass}>
                                    Rts Goods Out{' '}
                                    <span className="text-red-400">*</span>
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    value={numValue(data.rts_goods_out)}
                                    placeholder="0"
                                    onChange={(e) =>
                                        handleNumericChange(
                                            'rts_goods_out',
                                            e.target.value,
                                        )
                                    }
                                    className={inputClass}
                                />
                                {errors.rts_goods_out && (
                                    <p className="text-[11px] text-red-500">
                                        {errors.rts_goods_out}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-1.5">
                                <label className={labelClass}>
                                    Rts Bad (Damaged){' '}
                                    <span className="text-red-400">*</span>
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    value={numValue(data.rts_bad)}
                                    placeholder="0"
                                    onChange={(e) =>
                                        handleNumericChange(
                                            'rts_bad',
                                            e.target.value,
                                        )
                                    }
                                    className={inputClass}
                                />
                                {errors.rts_bad && (
                                    <p className="text-[11px] text-red-500">
                                        {errors.rts_bad}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-1.5">
                                <label className={labelClass}>
                                    Lost <span className="text-red-400">*</span>
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    value={numValue(data.lost)}
                                    placeholder="0"
                                    onChange={(e) =>
                                        handleNumericChange(
                                            'lost',
                                            e.target.value,
                                        )
                                    }
                                    className={inputClass}
                                />
                                {errors.lost && (
                                    <p className="text-[11px] text-red-500">
                                        {errors.lost}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-1.5">
                                <label className={labelClass}>
                                    Remaining Quantity{' '}
                                    <span className="text-gray-300 dark:text-gray-600">
                                        (auto)
                                    </span>
                                </label>
                                <input
                                    type="number"
                                    value={data.remaining_qty}
                                    readOnly
                                    tabIndex={-1}
                                    className={`${inputClass} cursor-not-allowed opacity-70`}
                                />
                                <p className="text-[10px] text-gray-400 dark:text-gray-500">
                                    {!data.inventory_item_id || !data.date
                                        ? 'Select an item and date to compute.'
                                        : baseRemainingLoading
                                          ? 'Computing…'
                                          : `Last remaining ${baseRemaining ?? 0}${
                                                baseRemainingFound
                                                    ? ''
                                                    : ' (no prior transaction)'
                                            } + in − out.`}
                                </p>
                                {errors.remaining_qty && (
                                    <p className="text-[11px] text-red-500">
                                        {errors.remaining_qty}
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-2 border-t border-black/6 bg-stone-50/50 px-5 py-3 dark:border-white/6 dark:bg-white/2">
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
                            {processing
                                ? isEditing
                                    ? 'Saving…'
                                    : 'Creating…'
                                : isEditing
                                  ? 'Save Changes'
                                  : 'Create Record'}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
};

export default InventoryFormDialog;
