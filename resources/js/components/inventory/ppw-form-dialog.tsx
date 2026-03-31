import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Workspace } from '@/types/models/Workspace';
import { Product } from '@/types/models/Product';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { format } from "date-fns"; // Standard for formatting dates
import { CalendarIcon } from "lucide-react";
import { cn } from "@/lib/utils";

interface Ppw {
    id: number;
    transaction_date: string;
    count: number;
    product_id: number;
}

interface PpwFormDialogProps {
    workspace: Workspace;
    products: Product[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    ppw?: Ppw | null;
}

export function PpwFormDialog({ workspace, products, open, onOpenChange, ppw }: PpwFormDialogProps) {
    const isEditing = !!ppw;

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        product_id: '',
        transaction_date: new Date().toISOString().split('T')[0],
        count: 0 as number | string,
    });


    useEffect(() => {
        if (open) {
            if (ppw) {
                setData({
                    product_id: ppw.product_id.toString(),
                    transaction_date: ppw.transaction_date,
                    count: ppw.count,
                });
            } else {
                reset();
                clearErrors();
            }
        }
    }, [ppw, open]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const url = isEditing
            ? `/workspaces/${workspace.slug}/inventory/ppw/${ppw.id}`
            : `/workspaces/${workspace.slug}/inventory/ppw`;

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
                            {isEditing ? 'Edit PPW Record' : 'Add PPW Record'}
                        </DialogTitle>
                        <DialogDescription className="text-[12px] text-gray-400 dark:text-gray-500 mt-0.5">
                            {isEditing ? 'Update the stock count or date for this record.' : 'Log a new product count for a specific date.'}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="space-y-5 px-5 py-4">
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

                        {/* Transaction Date */}
                        <div className="space-y-1.5">
                            <label className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                Transaction Date <span className="text-red-400">*</span>
                            </label>

                            <Popover>
                                <PopoverTrigger asChild>
                                    <Button
                                        variant={"outline"}
                                        className={cn(
                                            "h-10 w-full justify-start rounded-[10px] border border-black/8 bg-stone-50 px-3 text-left font-mono! text-[13px]! font-normal transition-all hover:bg-stone-100 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100",
                                            !data.transaction_date && "text-muted-foreground"
                                        )}
                                    >
                                        <CalendarIcon className="mr-2 h-4 w-4 text-emerald-600" />
                                        {data.transaction_date ? (
                                            format(new Date(data.transaction_date), "PPP")
                                        ) : (
                                            <span>Pick a date</span>
                                        )}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent className="w-auto p-0" align="start">
                                    <Calendar
                                        mode="single"
                                        selected={data.transaction_date ? new Date(data.transaction_date) : undefined}
                                        onSelect={(date) =>
                                            setData('transaction_date', date ? format(date, "yyyy-MM-dd") : '')
                                        }
                                        initialFocus
                                        className="rounded-md border dark:bg-zinc-900"
                                    />
                                </PopoverContent>
                            </Popover>
                            {errors.transaction_date && <p className="font-mono text-[11px] text-red-500 mt-1">{errors.transaction_date}</p>}
                        </div>

                        {/* Count */}
                        <div className="space-y-1.5">
                            <label className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                Stock Count <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="number"
                                placeholder="0"
                                value={data.count}
                                // FIX: Handles empty input gracefully to prevent NaN
                                onChange={(e) => setData('count', e.target.value === '' ? '' : parseInt(e.target.value))}
                                className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100"
                            />
                            {errors.count && <p className="font-mono text-[11px] text-red-500 mt-1">{errors.count}</p>}
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
                            {processing ? (isEditing ? 'Saving…' : 'Creating…') : (isEditing ? 'Save Changes' : 'Create Record')}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}