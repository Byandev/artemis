import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Workspace } from '@/types/models/Workspace';
import { Product } from '@/types/models/Product';
import { useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import { useEffect } from 'react';


interface InventoryItem {
    id: number;
    sku: string;
    product_id: number;
    sales_keywords: string;
    transaction_keywords: string;
    lead_time: number;
    unfulfilled_count: number;
    three_days_average: number;
    product?: {
        id: number;
        name: string;
    };
}

interface ItemFormDialogProps {
    workspace: Workspace;
    products: Product[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    item?: InventoryItem | null;
}

export function ItemFormDialog({ workspace, products, open, onOpenChange, item }: ItemFormDialogProps) {
    const isEditing = !!item;

    const [showAdditional, setShowAdditional] = useState(false);

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        id: '',
        product_id: '',
        sku: '',
        lead_time: '',
        unfulfilled_count: '',
        three_days_average: '',
        sales_keywords: '',
        transaction_keywords: '',
    });


    useEffect(() => {
        if (open) {
            if (item) {
                setData({
                    id: item.id.toString(),
                    product_id: item.product_id.toString(),
                    sku: item.sku,
                    lead_time: item.lead_time?.toString() ?? '0',
                    unfulfilled_count: item.unfulfilled_count?.toString() ?? '0',
                    three_days_average: item.three_days_average?.toString() ?? '0',
                    sales_keywords: item.sales_keywords ?? '',
                    transaction_keywords: item.transaction_keywords ?? '',
                });
            } else {
                reset();
                clearErrors();
            }
        }
    }, [open, item]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const url = isEditing
            ? `/workspaces/${workspace.slug}/inventory/items/${item.id}`
            : `/workspaces/${workspace.slug}/inventory/items`;

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        };

        if (isEditing) {
            put(url, options);
        } else {
            post(url, options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md p-0 gap-0 overflow-hidden border-none shadow-2xl dark:bg-zinc-900">
                <div className="px-5 pt-5 pb-4 border-b border-black/6 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            {isEditing ? 'Edit Items Record' : 'Add Items Record'}
                        </DialogTitle>
                        <DialogDescription className="text-[12px] text-gray-400 dark:text-gray-500 mt-0.5">
                            {isEditing ? 'Edit the details and settings for this inventory record.' : 'Add a new item to your workspace inventory.'}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="space-y-5 px-5 py-4">
                        {/* SKU */}
                        <div className="space-y-1.5">
                            <label className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                SKU <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="text"
                                placeholder="Unique SKU..."
                                value={data.sku}
                                onChange={(e) => setData('sku', e.target.value)}
                                className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100"
                            />
                            {errors.sku && <p className="font-mono text-[11px] text-red-500 mt-1">{errors.sku}</p>}
                        </div>
                        {/* Lead Time */}
                        <div className="space-y-1.5">
                            <label className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                Lead Time <span className="text-gray-300 dark:text-gray-600 font-normal normal-case">(days)</span>
                            </label>
                            <input
                                type="number"
                                min="0"
                                placeholder="0"
                                value={data.lead_time}
                                onChange={(e) => setData('lead_time', e.target.value)}
                                className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100"
                            />
                                {errors.lead_time && <p className="font-mono text-[11px] text-red-500 mt-1">{errors.lead_time}</p>}
                        </div>
                        {/* Unfulfilled Count */}
                        <div className="space-y-1.5">
                            <label className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                Unfulfilled Count
                            </label>
                            <input
                                type="number"
                                min="0"
                                placeholder="0"
                                value={data.unfulfilled_count}
                                onChange={(e) => setData('unfulfilled_count', e.target.value)}
                                className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100"
                            />
                            {errors.unfulfilled_count && <p className="font-mono text-[11px] text-red-500 mt-1">{errors.unfulfilled_count}</p>}
                        </div>
                        {/* 3-Day Average */}
                        <div className="space-y-1.5">
                            <label className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                3-Day Avg
                            </label>
                            <input
                                type="number"
                                min="0"
                                step="0.0001"
                                placeholder="0"
                                value={data.three_days_average}
                                onChange={(e) => setData('three_days_average', e.target.value)}
                                className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100"
                            />
                            {errors.three_days_average && <p className="font-mono text-[11px] text-red-500 mt-1">{errors.three_days_average}</p>}
                        </div>

                        {/* Product Selector */}
                        <div className="space-y-1.5">
                            <label className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                Select Product <span className="text-red-400">*</span>
                            </label>
                            <select
                                value={data.product_id}
                                onChange={(e) => setData('product_id', e.target.value)}
                                className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100"
                            >
                                <option value="">Select a product...</option>
                                {products.map((product) => (
                                    <option key={product.id} value={product.id}>
                                        {product.name}
                                    </option>
                                ))}
                            </select>
                            {errors.product_id && <p className="font-mono text-[11px] text-red-500 mt-1">{errors.product_id}</p>}
                        </div>


                        {/* 3. Additional Settings Accordion */}
                        <div className="space-y-3 mt-2">
                            <button
                                type="button"
                                onClick={() => setShowAdditional(!showAdditional)}
                                className="flex w-full items-center justify-between rounded-[10px] font-mono! border border-black/8 bg-stone-50/50 px-4 py-2.5 text-[13px]! font-medium text-gray-600 transition-all hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-800/50 dark:text-gray-300"
                            >
                                <span>Additional Settings</span>
                                <span className={`transition-transform duration-200 ${showAdditional ? 'rotate-180' : ''}`}>
                                    ▼
                                </span>
                            </button>

                            {showAdditional && (
                                <div className="space-y-4 mt-2 rounded-xl border border-dashed border-black/10 p-4 dark:border-white/10 bg-black/[0.01] dark:bg-white/[0.01] animate-in fade-in slide-in-from-top-1">
                                    {/* Sales Keywords */}
                                    <div className="space-y-1.5">
                                        <label className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                            Sales Keywords
                                        </label>
                                        <textarea
                                            placeholder="Enter sales keywords..."
                                            value={data.sales_keywords}
                                            onChange={(e) => setData('sales_keywords', e.target.value)}
                                            className="min-h-[80px] w-full rounded-[10px] border border-black/8 bg-white p-3 font-mono! text-[13px]! text-gray-800 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-100 resize-none"
                                        />
                                        {errors.sales_keywords && <p className="font-mono text-[11px] text-red-500 mt-1">{errors.sales_keywords}</p>}
                                    </div>

                                    {/* Transaction Keywords */}
                                    <div className="space-y-1.5">
                                        <label className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                            Transaction Keywords
                                        </label>
                                        <textarea
                                            placeholder="Enter transaction keywords..."
                                            value={data.transaction_keywords}
                                            onChange={(e) => setData('transaction_keywords', e.target.value)}
                                            className="min-h-[80px] w-full rounded-[10px] border border-black/8 bg-white p-3 font-mono! text-[13px]! text-gray-800 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-100 resize-none"
                                        />
                                        {errors.transaction_keywords && <p className="font-mono text-[11px] text-red-500 mt-1">{errors.transaction_keywords}</p>}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Footer Actions */}
                    <div className="flex items-center justify-end gap-2 border-t border-black/6 dark:border-white/6 px-5 py-3 bg-stone-50/50 dark:bg-white/2">
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
                            {processing ? (isEditing ? 'Saving…' : 'Creating…') : (isEditing ? 'Save Changes' : 'Create Item')}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
