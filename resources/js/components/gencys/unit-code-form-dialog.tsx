import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Workspace } from '@/types/models/Workspace';
import { useForm } from '@inertiajs/react';
import { Plus, Trash2, X } from 'lucide-react';
import React, { useEffect } from 'react';
import { toast } from 'sonner';

export interface UnitCodeItem {
    id?: number;
    inventory_item_code: string | null;
    quantity: number | string | null;
    price: number | string | null;
}

export interface UnitCode {
    id: number;
    sku: string | null;
    unit_code: string | null;
    total_amount: string | null;
    items?: UnitCodeItem[];
}

interface Props {
    workspace: Workspace;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    unitCode?: UnitCode | null;
}

type FormItem = {
    inventory_item_code: string;
    quantity: string;
    price: string;
};

const emptyItem: FormItem = {
    inventory_item_code: '',
    quantity: '',
    price: '',
};

const inputClass =
    'h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 transition-all outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100';

const labelClass =
    'block font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';

export function UnitCodeFormDialog({
    workspace,
    open,
    onOpenChange,
    unitCode,
}: Props) {
    const isEditing = !!unitCode;

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm({
            sku: '',
            unit_code: '',
            total_amount: '',
            items: [{ ...emptyItem }] as FormItem[],
        });

    useEffect(() => {
        if (!open) return;

        if (unitCode) {
            setData({
                sku: unitCode.sku ?? '',
                unit_code: unitCode.unit_code ?? '',
                total_amount: unitCode.total_amount ?? '',
                items:
                    unitCode.items && unitCode.items.length > 0
                        ? unitCode.items.map((item) => ({
                              inventory_item_code:
                                  item.inventory_item_code ?? '',
                              quantity:
                                  item.quantity != null
                                      ? String(item.quantity)
                                      : '',
                              price:
                                  item.price != null ? String(item.price) : '',
                          }))
                        : [{ ...emptyItem }],
            });
        } else {
            reset();
            clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, unitCode]);

    const setItem = (index: number, key: keyof FormItem, value: string) => {
        setData(
            'items',
            data.items.map((item, i) =>
                i === index ? { ...item, [key]: value } : item,
            ),
        );
    };

    const addItem = () => setData('items', [...data.items, { ...emptyItem }]);

    const removeItem = (index: number) =>
        setData(
            'items',
            data.items.filter((_, i) => i !== index),
        );

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const url = isEditing
            ? `/workspaces/${workspace.slug}/gencys/unit-codes/${unitCode.id}`
            : `/workspaces/${workspace.slug}/gencys/unit-codes`;

        const submit = isEditing ? put : post;

        submit(url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    isEditing ? 'Unit code updated' : 'Unit code created',
                );
                reset();
                clearErrors();
                onOpenChange(false);
            },
            onError: () =>
                toast.error('Failed to save unit code. Please check the form.'),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-0 overflow-hidden border-none p-0 shadow-2xl sm:max-w-lg dark:bg-zinc-900 [&_[data-default-close=true]]:hidden">
                <div className="relative border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            {isEditing ? 'Edit Unit Code' : 'Add Unit Code'}
                        </DialogTitle>
                        <DialogDescription className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                            {isEditing
                                ? 'Update this unit code and its inventory items.'
                                : 'Create a unit code and its inventory item breakdown.'}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogClose className="absolute top-4 right-4 inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-black/5 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-white/6 dark:hover:text-gray-300">
                        <X className="h-4 w-4" />
                        <span className="sr-only">Close</span>
                    </DialogClose>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="max-h-[65vh] space-y-5 overflow-y-auto px-5 py-4">
                        <div className="space-y-1.5">
                            <label className={labelClass}>
                                Unit Code{' '}
                                <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="text"
                                placeholder="Unique unit code..."
                                value={data.unit_code}
                                onChange={(e) =>
                                    setData('unit_code', e.target.value)
                                }
                                className={inputClass}
                            />
                            {errors.unit_code && (
                                <p className="mt-1 font-mono text-[11px] text-red-500">
                                    {errors.unit_code}
                                </p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <label className={labelClass}>SKU</label>
                            <input
                                type="text"
                                placeholder="SKU..."
                                value={data.sku}
                                onChange={(e) => setData('sku', e.target.value)}
                                className={inputClass}
                            />
                            {errors.sku && (
                                <p className="mt-1 font-mono text-[11px] text-red-500">
                                    {errors.sku}
                                </p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <label className={labelClass}>Total Amount</label>
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="0.00"
                                value={data.total_amount}
                                onChange={(e) =>
                                    setData('total_amount', e.target.value)
                                }
                                className={inputClass}
                            />
                            {errors.total_amount && (
                                <p className="mt-1 font-mono text-[11px] text-red-500">
                                    {errors.total_amount}
                                </p>
                            )}
                        </div>

                        {/* Inventory items */}
                        <div className="space-y-3 rounded-xl border border-dashed border-black/10 bg-black/[0.01] p-4 dark:border-white/10 dark:bg-white/[0.01]">
                            <div className="flex items-center justify-between">
                                <span className={labelClass}>
                                    Inventory Items
                                </span>
                                <button
                                    type="button"
                                    onClick={addItem}
                                    className="inline-flex items-center gap-1 rounded-md px-2 py-1 font-mono text-[11px] text-emerald-600 transition-colors hover:bg-emerald-500/10 dark:text-emerald-400"
                                >
                                    <Plus className="h-3.5 w-3.5" /> Add item
                                </button>
                            </div>

                            {data.items.length === 0 && (
                                <p className="font-mono text-[11px] text-gray-400">
                                    No items. Click “Add item” to add one.
                                </p>
                            )}

                            {data.items.map((item, index) => (
                                <div
                                    key={index}
                                    className="grid grid-cols-12 items-start gap-2"
                                >
                                    <div className="col-span-6">
                                        <input
                                            type="text"
                                            placeholder="Item code"
                                            value={item.inventory_item_code}
                                            onChange={(e) =>
                                                setItem(
                                                    index,
                                                    'inventory_item_code',
                                                    e.target.value,
                                                )
                                            }
                                            className={inputClass}
                                        />
                                        {(errors as Record<string, string>)[
                                            `items.${index}.inventory_item_code`
                                        ] && (
                                            <p className="mt-1 font-mono text-[10px] text-red-500">
                                                {
                                                    (
                                                        errors as Record<
                                                            string,
                                                            string
                                                        >
                                                    )[
                                                        `items.${index}.inventory_item_code`
                                                    ]
                                                }
                                            </p>
                                        )}
                                    </div>
                                    <div className="col-span-2">
                                        <input
                                            type="number"
                                            min="0"
                                            placeholder="Qty"
                                            value={item.quantity}
                                            onChange={(e) =>
                                                setItem(
                                                    index,
                                                    'quantity',
                                                    e.target.value,
                                                )
                                            }
                                            className={inputClass}
                                        />
                                    </div>
                                    <div className="col-span-3">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            placeholder="Price"
                                            value={item.price}
                                            onChange={(e) =>
                                                setItem(
                                                    index,
                                                    'price',
                                                    e.target.value,
                                                )
                                            }
                                            className={inputClass}
                                        />
                                    </div>
                                    <div className="col-span-1 flex justify-center pt-2">
                                        <button
                                            type="button"
                                            onClick={() => removeItem(index)}
                                            className="text-gray-400 transition-colors hover:text-red-500"
                                            aria-label="Remove item"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="flex justify-end gap-2 border-t border-black/6 px-5 py-4 dark:border-white/6">
                        <DialogClose className="h-9 rounded-lg border border-black/8 bg-white px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700">
                            Cancel
                        </DialogClose>
                        <button
                            type="submit"
                            disabled={processing}
                            className="h-9 rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                        >
                            {processing
                                ? 'Saving...'
                                : isEditing
                                  ? 'Save Changes'
                                  : 'Create Unit Code'}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
