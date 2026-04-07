import { Calendar, ChevronDown } from 'lucide-react';
import { Dispatch, SetStateAction, useRef } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { StatusId } from '@/types/models/PurchasedOrder';

export interface AddItemForm {
    issue_date: string;
    delivery_no: string;
    cust_po_no: string;
    control_no: string;
    item: string;
    cog_amount: string;
    delivery_fee: string;
    total_amount: string;
    status: StatusId;
}

interface AddItemDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode?: 'add' | 'edit';
    addItemForm: AddItemForm;
    setAddItemForm: Dispatch<SetStateAction<AddItemForm>>;
    itemOptions: string[];
    isFormComplete: boolean;
    addItemFieldErrors: Record<string, string>;
    submitting: boolean;
    onSubmit: () => void;
    statusOptions: Array<{ value: StatusId; label: string }>;
    statusBadgeClass: (status: StatusId | string) => string;
    statusLabel: (value: StatusId | number | string) => string;
    statusOptionTextClass: (status: StatusId | string) => string;
    monoFont: string;
    sansFont: string;
}

export function AddItemDialog({
    open,
    onOpenChange,
    mode = 'add',
    addItemForm,
    setAddItemForm,
    itemOptions,
    isFormComplete,
    addItemFieldErrors,
    submitting,
    onSubmit,
    statusOptions,
    statusBadgeClass,
    statusLabel,
    statusOptionTextClass,
    monoFont,
    sansFont,
}: AddItemDialogProps) {
    const dateInputRef = useRef<HTMLInputElement | null>(null);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                hideClose
                className="max-w-[520px] rounded-xl border border-gray-200 bg-white p-0 shadow-[0_10px_30px_rgba(0,0,0,0.08)] dark:border-white/10 dark:bg-zinc-900"
                style={{ fontFamily: sansFont }}
            >
                <DialogTitle className="sr-only">Add Item</DialogTitle>
                <DialogDescription className="sr-only">Fill in the details below to record a new item.</DialogDescription>
                <div className="px-5 py-4">
                    <h2 className="text-[14px] font-semibold uppercase tracking-tight text-gray-900 dark:text-gray-100">{mode === 'edit' ? 'Edit Item' : 'Add Item'}</h2>
                    <p className="mt-0.5 text-[10px] text-gray-500">{mode === 'edit' ? 'Update the details below.' : 'Fill in the details below to record a new item.'}</p>

                    <div className="mt-4 space-y-3.5">
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-[11px] font-medium text-gray-500">Transaction Date</label>
                                <div
                                    className="relative"
                                    onClick={() => {
                                        const el = dateInputRef.current;
                                        if (!el) return;
                                        if (typeof el.showPicker === 'function') {
                                            el.showPicker();
                                        }
                                        el.focus();
                                    }}
                                >
                                    <input
                                        type="date"
                                        value={addItemForm.issue_date}
                                        onChange={(e) => setAddItemForm((prev) => ({ ...prev, issue_date: e.target.value }))}
                                        placeholder="Select date"
                                        ref={dateInputRef}
                                        className="h-9 w-full rounded-md border border-gray-200 bg-white pl-9 pr-2.5 text-[12px] text-gray-700 outline-none transition-colors hover:border-emerald-500 focus:border-emerald-500 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-200"
                                    />
                                    <Calendar className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                </div>
                                {addItemFieldErrors.issue_date && <p className="mt-1 text-[10px] text-red-500">{addItemFieldErrors.issue_date}</p>}
                            </div>

                            <div>
                                <label className="mb-1 block text-[11px] font-medium text-gray-500">Delivered No.</label>
                                <input
                                    value={addItemForm.delivery_no}
                                    onChange={(e) => setAddItemForm((prev) => ({ ...prev, delivery_no: e.target.value }))}
                                    placeholder="e.g. DN-1001"
                                    className="h-9 w-full rounded-md border border-gray-200 bg-white px-2.5 text-[12px] text-gray-700 outline-none transition-colors hover:border-emerald-500 focus-border-emerald-500 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-200"
                                />
                                {addItemFieldErrors.delivery_no && <p className="mt-1 text-[10px] text-red-500">{addItemFieldErrors.delivery_no}</p>}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-[11px] font-medium text-gray-500">Customer No.</label>
                                <input
                                    value={addItemForm.cust_po_no}
                                    onChange={(e) => setAddItemForm((prev) => ({ ...prev, cust_po_no: e.target.value }))}
                                    placeholder="e.g. CUST-1001"
                                    className="h-9 w-full rounded-md border border-gray-200 bg-white px-2.5 text-[12px] text-gray-700 outline-none transition-colors hover:border-emerald-500 focus:border-emerald-500 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-200"
                                />
                                {addItemFieldErrors.cust_po_no && <p className="mt-1 text-[10px] text-red-500">{addItemFieldErrors.cust_po_no}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-[11px] font-medium text-gray-500">Control No.</label>
                                <input
                                    value={addItemForm.control_no}
                                    onChange={(e) => setAddItemForm((prev) => ({ ...prev, control_no: e.target.value }))}
                                    placeholder="e.g. CTRL-2001"
                                    className="h-9 w-full rounded-md border border-gray-200 bg-white px-2.5 text-[12px] text-gray-700 outline-none transition-colors hover:border-emerald-500 focus:border-emerald-500 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-200"
                                />
                                {addItemFieldErrors.control_no && <p className="mt-1 text-[10px] text-red-500">{addItemFieldErrors.control_no}</p>}
                            </div>
                        </div>

                        <div>
                            <label className="mb-1 block text-[11px] font-medium text-gray-500">Item</label>
                            <select
                                value={addItemForm.item}
                                onChange={(e) => setAddItemForm((prev) => ({ ...prev, item: e.target.value }))}
                                className="h-9 w-full rounded-md border border-gray-200 bg-white px-2.5 text-[12px] text-gray-700 outline-none transition-colors hover:border-emerald-500 focus:border-emerald-500 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-200"
                            >
                                <option value="">Choose a Product</option>
                                {itemOptions.map((option) => (
                                    <option key={option} value={option}>
                                        {option}
                                    </option>
                                ))}
                            </select>
                            {addItemFieldErrors.item && <p className="mt-1 text-[10px] text-red-500">{addItemFieldErrors.item}</p>}
                        </div>

                        <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <div>
                                <label className="mb-1 block text-[11px] font-medium text-gray-500">COG Amount</label>
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={addItemForm.cog_amount}
                                    onChange={(e) => setAddItemForm((prev) => ({ ...prev, cog_amount: e.target.value }))}
                                    placeholder="0.00"
                                    className="h-9 w-full rounded-md border border-gray-200 bg-white px-2.5 text-[12px] text-gray-700 outline-none transition-colors hover:border-emerald-500 focus:border-emerald-500 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-200"
                                />
                                {addItemFieldErrors.cog_amount && <p className="mt-1 text-[10px] text-red-500">{addItemFieldErrors.cog_amount}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-[11px] font-medium text-gray-500">Delivery Fee</label>
                                    <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={addItemForm.delivery_fee}
                                    onChange={(e) => setAddItemForm((prev) => ({ ...prev, delivery_fee: e.target.value }))}
                                        placeholder="0.00"
                                        className="h-9 w-full rounded-md border border-gray-200 bg-white px-2.5 text-[12px] text-gray-700 outline-none transition-colors hover:border-emerald-500 focus:border-emerald-500 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-200"
                                />
                                {addItemFieldErrors.delivery_fee && <p className="mt-1 text-[10px] text-red-500">{addItemFieldErrors.delivery_fee}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-[11px] font-medium text-gray-500">Total Amount</label>
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={addItemForm.total_amount}
                                    readOnly
                                    placeholder="0.00"
                                    className="h-9 w-full rounded-md border border-gray-200 bg-gray-50 px-2.5 text-[12px] text-gray-700 outline-none transition-colors hover:border-emerald-500 focus:border-emerald-500 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-200"
                                />
                                {addItemFieldErrors.total_amount && <p className="mt-1 text-[10px] text-red-500">{addItemFieldErrors.total_amount}</p>}
                            </div>
                        </div>

                        <div>
                            <label className="mb-1 block text-[11px] font-medium text-gray-500">Status</label>
                            <div className="flex justify-start">
                                <div className="inline-flex h-6 w-[172px] items-center rounded-2xl pl-1.5">
                                    <span className={`inline-flex w-full items-center gap-1 rounded-2xl px-2 py-1 text-[11px] font-medium ${statusBadgeClass(addItemForm.status)}`}>
                                        <span className="h-1.5 w-1.5 rounded-full bg-current/60" />
                                        <span>{statusLabel(addItemForm.status)}</span>
                                    </span>

                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <button
                                                type="button"
                                                className="ml-0.5 inline-flex h-6 w-5 items-center justify-center rounded-md text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-300"
                                                aria-label="Open add-item status dropdown"
                                            >
                                                <ChevronDown className="h-3.5 w-3.5" />
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" className="w-[170px] p-1.5">
                                            {statusOptions.map((opt) => (
                                                <DropdownMenuItem
                                                    key={opt.value}
                                                    className={statusOptionTextClass(opt.value)}
                                                    onClick={() => setAddItemForm((prev) => ({ ...prev, status: opt.value }))}
                                                >
                                                    {opt.label}
                                                </DropdownMenuItem>
                                            ))}
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </div>
                            {addItemFieldErrors.status && <p className="mt-1 text-[10px] text-red-500">{addItemFieldErrors.status}</p>}
                        </div>

                        <div className="pt-3 flex items-center justify-end gap-2.5">
                            <button
                                type="button"
                                onClick={() => onOpenChange(false)}
                                className="h-9 rounded-md border border-gray-200 bg-white px-4 text-[12px] font-medium text-gray-600 transition-colors hover:border-gray-300 hover:bg-gray-50 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-300 dark:hover:bg-white/5"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                disabled={submitting || !isFormComplete}
                                onClick={onSubmit}
                                className="h-9 rounded-md bg-emerald-600 px-4 text-[12px] font-medium text-white transition-colors hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-emerald-500 dark:hover:bg-emerald-400"
                            >
                                {submitting ? (mode === 'edit' ? 'Saving...' : 'Adding...') : (mode === 'edit' ? 'Save' : 'Add Item')}
                            </button>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
