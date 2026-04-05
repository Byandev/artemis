import { Workspace } from '@/types/models/Workspace';
import { Inventory } from '@/types/models/InventoryTransaction';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { useForm } from '@inertiajs/react';
import React, { useEffect, useMemo } from 'react';

interface Props {
    workspace: Workspace;
    inventory?: Inventory;
    open: boolean;
    onOpenChange: (value: boolean) => void;
    onSuccess?: () => void;
}

const InventoryFormDialog = ({ workspace, open, onOpenChange, inventory }: Props) => {
    const { data, setData, post, processing, errors, reset, patch } = useForm({
        date: '',
        ref_no: '',
        po_qty_in: 0,
        po_qty_out: 0,
        rts_goods_in: 0,
        rts_goods_out: 0,
        rts_bad: 0,
        remaining_qty: 0,
    });

    const isEditing = useMemo(() => !!inventory, [inventory]);

    useEffect(() => {
        if (inventory) {
            setData({
                date: inventory.date || '',
                ref_no: inventory.ref_no || '',
                po_qty_in: inventory.po_qty_in || 0,
                po_qty_out: inventory.po_qty_out || 0,
                rts_goods_in: inventory.rts_goods_in || 0,
                rts_goods_out: inventory.rts_goods_out || 0,
                rts_bad: inventory.rts_bad || 0,
                remaining_qty: inventory.remaining_qty || 0,

            });
        } else {
            reset();
        }
    }, [inventory, open]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const url = isEditing
            ? `/workspaces/${workspace.slug}/inventory/transactions/${inventory?.id}`
            : `/workspaces/${workspace.slug}/inventory/transactions`;

        const options = {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                onOpenChange(false);
            },
        };

        if (isEditing) {
            patch(url, options);
        } else {
            post(url, {
                ...options,
                onSuccess: () => {
                    reset();
                    onOpenChange(false);
                }
            });
        }
    }

    const inputClass = "h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400";
    const labelClass = "block font-mono! text-[10px]! font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500";

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-xl">
                <div className="border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
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
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="max-h-[60vh] items-center justify-center space-y-4 overflow-y-auto px-5 py-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="items-center justify-center space-y-1.5">
                                <label className={labelClass}>
                                    Transaction Date{' '}
                                    <span className="text-red-400">*</span>
                                </label>
                                <input
                                    type="date"
                                    value={data.date}
                                    onChange={(e) =>
                                        setData('date', e.target.value)
                                    }
                                    className={inputClass}
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
                                    value={data.po_qty_in}
                                    onChange={(e) =>
                                        setData(
                                            'po_qty_in',
                                            parseInt(e.target.value) || 0,
                                        )
                                    }
                                    className={inputClass}
                                />
                            </div>
                            <div className="space-y-1.5">
                                <label className={labelClass}>
                                    PO Quantity Out{' '}
                                    <span className="text-red-400">*</span>
                                </label>
                                <input
                                    type="number"
                                    value={data.po_qty_out}
                                    onChange={(e) =>
                                        setData(
                                            'po_qty_out',
                                            parseInt(e.target.value) || 0,
                                        )
                                    }
                                    className={inputClass}
                                />
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
                                    value={data.rts_goods_in}
                                    onChange={(e) =>
                                        setData(
                                            'rts_goods_in',
                                            parseInt(e.target.value) || 0,
                                        )
                                    }
                                    className={inputClass}
                                />
                            </div>
                            <div className="space-y-1.5">
                                <label className={labelClass}>
                                    Rts Goods Out{' '}
                                    <span className="text-red-400">*</span>
                                </label>
                                <input
                                    type="number"
                                    value={data.rts_goods_out}
                                    onChange={(e) =>
                                        setData(
                                            'rts_goods_out',
                                            parseInt(e.target.value) || 0,
                                        )
                                    }
                                    className={inputClass}
                                />
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
                                    value={data.rts_bad}
                                    onChange={(e) =>
                                        setData(
                                            'rts_bad',
                                            parseInt(e.target.value) || 0,
                                        )
                                    }
                                    className={inputClass}
                                />
                            </div>
                            <div className="space-y-1.5">
                                <label className={labelClass}>
                                    Remaining Quantity{' '}
                                    <span className="text-red-400">*</span>
                                </label>
                                <input
                                    type="number"
                                    value={data.remaining_qty}
                                    onChange={(e) =>
                                        setData(
                                            'rts_bad',
                                            parseInt(e.target.value) || 0,
                                        )
                                    }
                                    className={inputClass}
                                />
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
