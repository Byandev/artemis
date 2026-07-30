import { SearchableSelect } from '@/components/finance/searchable-select';
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
import { Package, Plus, Trash2, X } from 'lucide-react';
import React, { useEffect, useMemo } from 'react';
import { toast } from 'sonner';

/** A unit code the order's line items can be picked from. */
export interface UnitCodeOption {
    unit_code: string;
    sku: string | null;
    total_amount: string | null;
}

interface Props {
    workspace: Workspace;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Unit codes available as line items in this workspace. */
    unitCodes?: UnitCodeOption[];
    /** Existing values, offered as datalist suggestions. */
    csrs?: string[];
    platforms?: string[];
    orderStatuses?: string[];
}

const inputClass =
    'h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 transition-all outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100';

const labelClass =
    'block font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';

/** "2026-07-29T14:30" — the value format datetime-local inputs expect. */
function nowLocal(): string {
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 16);
}

function todayLocal(): string {
    return nowLocal().slice(0, 10);
}

/** One picked line item: a unit code and how many of it the order contains. */
interface FormItem {
    unit_code: string;
    quantity: string;
}

const emptyItem: FormItem = { unit_code: '', quantity: '1' };

/**
 * The parcel statuses Gencys uses. DELIVERED is what the income statement counts
 * as revenue; NEW / PENDING PRINTED WAYBILL are the "still open" ones that
 * SyncInventoryFromGencysOrders treats as unfulfilled demand.
 */
export const PARCEL_STATUSES = [
    'NEW',
    'PENDING PRINTED WAYBILL',
    'SHIPPED OUT',
    'IN-TRANSIT',
    'DELIVERING',
    'DELIVERED',
    'OUT OF DELIVERY ZONE',
    'FOR RETURN',
    'RETURNING',
    'RETURNED',
    'CANCELLED BY CUSTOMER',
    'CANCELLED BY WAREHOUSE',
] as const;

const peso = (v: number | string | null) =>
    `₱${Number(v ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;

/** Laravel reports item errors as `items.0.unit_code`; surface either field. */
function itemError(errors: object, index: number): string | undefined {
    const bag = errors as Record<string, string>;

    return bag[`items.${index}.unit_code`] ?? bag[`items.${index}.quantity`];
}

/**
 * Defaults produce an order that already counts as delivered revenue for the
 * current month, which is the common case when testing income statements.
 */
function defaults() {
    return {
        items: [{ ...emptyItem }] as FormItem[],
        order_no: '',
        order_date: nowLocal(),
        csr: '',
        verifier_name: '',
        upsell_by: '',
        customer_name: '',
        address: '',
        province: '',
        city: '',
        brgy: '',
        contact: '',
        total_qty: '',
        price_final: '',
        price_initial: '',
        shipping_fee: '',
        page: '',
        platform: '',
        tracking_number: '',
        courier: '',
        parcel_status: 'DELIVERED',
        order_status: '',
        mop: '',
        encoded_date: nowLocal(),
        parcel_updated_date: nowLocal(),
        shipped_out_date: todayLocal(),
        date_added: nowLocal(),
        price_upsell: '',
        intern_brands_name: '',
        total_cog: '',
    };
}

type FormData = ReturnType<typeof defaults>;
/** The plain string columns — `items` is edited through its own repeater. */
type Field = Exclude<keyof FormData, 'items'>;

export function TestOrderFormDialog({
    workspace,
    open,
    onOpenChange,
    unitCodes = [],
    csrs = [],
    platforms = [],
    orderStatuses = [],
}: Props) {
    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm<FormData>(defaults());

    useEffect(() => {
        if (!open) return;
        // Re-stamp the date defaults each time the dialog opens.
        setData(defaults());
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const codeLabels = useMemo(
        () => unitCodes.map((c) => c.unit_code),
        [unitCodes],
    );
    const byLabel = useMemo(
        () => new Map(unitCodes.map((c) => [c.unit_code, c])),
        [unitCodes],
    );

    const setItem = (index: number, key: keyof FormItem, value: string) =>
        setData(
            'items',
            data.items.map((item, i) =>
                i === index ? { ...item, [key]: value } : item,
            ),
        );

    const addItem = () => setData('items', [...data.items, { ...emptyItem }]);

    const removeItem = (index: number) =>
        setData(
            'items',
            data.items.filter((_, i) => i !== index),
        );

    // What the picked lines add up to. The unit code's total_amount is a selling
    // price, so this is a Price (Final) suggestion — not a cost.
    const totals = useMemo(() => {
        let qty = 0;
        let amount = 0;

        for (const item of data.items) {
            const n = Number(item.quantity) || 0;
            qty += n;
            amount +=
                n * Number(byLabel.get(item.unit_code)?.total_amount ?? 0);
        }

        return { qty, amount };
    }, [data.items, byLabel]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        post(`/workspaces/${workspace.slug}/gencys/daily-sales-tracker`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Test order created');
                reset();
                clearErrors();
                onOpenChange(false);
            },
            onError: () =>
                toast.error('Failed to create test order. Check the form.'),
        });
    };

    const field = (
        name: Field,
        label: string,
        opts: {
            type?: string;
            step?: string;
            placeholder?: string;
            suggestions?: string[];
            /** Render a fixed dropdown instead of a free-text input. */
            choices?: readonly string[];
        } = {},
    ) => {
        const listId = opts.suggestions?.length
            ? `test-order-${name}-options`
            : undefined;

        return (
            <div className="space-y-1.5">
                <label className={labelClass}>{label}</label>
                {opts.choices ? (
                    <select
                        value={data[name]}
                        onChange={(e) => setData(name, e.target.value)}
                        className={inputClass}
                    >
                        <option value="">—</option>
                        {opts.choices.map((v) => (
                            <option key={v} value={v}>
                                {v}
                            </option>
                        ))}
                    </select>
                ) : (
                    <input
                        type={opts.type ?? 'text'}
                        step={opts.step}
                        list={listId}
                        placeholder={opts.placeholder}
                        value={data[name]}
                        onChange={(e) => setData(name, e.target.value)}
                        className={inputClass}
                    />
                )}
                {listId && (
                    <datalist id={listId}>
                        {opts.suggestions?.map((v) => (
                            <option key={v} value={v} />
                        ))}
                    </datalist>
                )}
                {errors[name] && (
                    <p className="mt-1 font-mono text-[11px] text-red-500">
                        {errors[name]}
                    </p>
                )}
            </div>
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-0 overflow-hidden border-none p-0 shadow-2xl sm:max-w-2xl dark:bg-zinc-900 [&_[data-default-close=true]]:hidden">
                <div className="relative border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            Add Test Order
                        </DialogTitle>
                        <DialogDescription className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                            Creates a hand-made Gencys order for testing. Not
                            available in production, and an ERP sync will never
                            overwrite it.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogClose className="absolute top-4 right-4 inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-black/5 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-white/6 dark:hover:text-gray-300">
                        <X className="h-4 w-4" />
                        <span className="sr-only">Close</span>
                    </DialogClose>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="max-h-[65vh] space-y-5 overflow-y-auto px-5 py-4">
                        <Section title="Order">
                            {field('order_no', 'Order No.')}
                            {field('order_date', 'Order Date', {
                                type: 'datetime-local',
                            })}
                            {field('total_qty', 'Total Qty', {
                                type: 'number',
                                placeholder: '0',
                            })}
                            {field('mop', 'MOP', { placeholder: 'COD' })}
                        </Section>

                        {/* Line items — unit codes, exactly like real orders. */}
                        <div className="space-y-3 rounded-xl border border-dashed border-black/10 bg-black/[0.01] p-4 dark:border-white/10 dark:bg-white/[0.01]">
                            <div className="flex items-center justify-between">
                                <span className={labelClass}>
                                    Order Items{' '}
                                    <span className="text-red-400">*</span>
                                </span>
                                <button
                                    type="button"
                                    onClick={addItem}
                                    className="inline-flex items-center gap-1 rounded-md px-2 py-1 font-mono text-[11px] text-emerald-600 transition-colors hover:bg-emerald-500/10 dark:text-emerald-400"
                                >
                                    <Plus className="h-3.5 w-3.5" /> Add item
                                </button>
                            </div>

                            <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                Picked from this workspace's unit codes. Saved
                                to gencys_order_items, and Order Details is
                                built as “{'{qty}'}x{'{unit code}'}”.
                            </p>

                            {unitCodes.length === 0 && (
                                <p className="font-mono text-[11px] text-amber-600 dark:text-amber-400">
                                    No unit codes in this workspace yet — add
                                    some under Gencys → Unit Codes first.
                                </p>
                            )}

                            {data.items.map((item, index) => {
                                const picked = byLabel.get(item.unit_code);

                                return (
                                    <div key={index} className="space-y-1.5">
                                        <div className="grid grid-cols-12 items-start gap-2">
                                            <div className="col-span-8">
                                                <SearchableSelect
                                                    options={codeLabels}
                                                    value={item.unit_code}
                                                    onChange={(v) =>
                                                        setItem(
                                                            index,
                                                            'unit_code',
                                                            v,
                                                        )
                                                    }
                                                    placeholder="Select unit code…"
                                                    searchPlaceholder="Search unit codes…"
                                                    emptyText="No unit codes found."
                                                    icon={Package}
                                                />
                                            </div>
                                            <div className="col-span-3">
                                                <input
                                                    type="number"
                                                    min="1"
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
                                            <div className="col-span-1 flex justify-center pt-2.5">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        removeItem(index)
                                                    }
                                                    className="text-gray-400 transition-colors hover:text-red-500"
                                                    aria-label="Remove item"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </div>

                                        {picked && (
                                            <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                                {picked.sku ?? 'no sku'}
                                                {picked.total_amount
                                                    ? ` · ${peso(picked.total_amount)} each`
                                                    : ''}
                                            </p>
                                        )}

                                        {itemError(errors, index) && (
                                            <p className="font-mono text-[11px] text-red-500">
                                                {itemError(errors, index)}
                                            </p>
                                        )}
                                    </div>
                                );
                            })}

                            {(errors as Record<string, string>).items && (
                                <p className="font-mono text-[11px] text-red-500">
                                    {(errors as Record<string, string>).items}
                                </p>
                            )}

                            {data.items.length > 0 && (
                                <div className="flex items-center justify-between border-t border-black/6 pt-2.5 dark:border-white/6">
                                    <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                                        {totals.qty} pc(s) ·{' '}
                                        {peso(totals.amount)}
                                    </span>
                                    {totals.amount > 0 && (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setData(
                                                    'price_final',
                                                    totals.amount.toFixed(2),
                                                )
                                            }
                                            className="rounded-md px-2 py-1 font-mono text-[11px] text-emerald-600 transition-colors hover:bg-emerald-500/10 dark:text-emerald-400"
                                        >
                                            Use as Price (Final)
                                        </button>
                                    )}
                                </div>
                            )}
                        </div>

                        <Section title="Pricing">
                            {field('price_final', 'Price (Final) ₱', {
                                type: 'number',
                                step: '0.01',
                                placeholder: '0.00',
                            })}
                            {field('price_initial', 'Price (Initial) ₱', {
                                type: 'number',
                                step: '0.01',
                                placeholder: '0.00',
                            })}
                            {field('price_upsell', 'Price (Upsell) ₱', {
                                type: 'number',
                                step: '0.01',
                                placeholder: '0.00',
                            })}
                            {field('shipping_fee', 'Shipping Fee ₱', {
                                type: 'number',
                                step: '0.01',
                                placeholder: '0.00',
                            })}
                            {field('total_cog', 'Total COG ₱', {
                                type: 'number',
                                step: '0.01',
                                placeholder: '0.00',
                            })}
                        </Section>

                        <Section title="Attribution">
                            {field('csr', 'CSR', { suggestions: csrs })}
                            {field('intern_brands_name', 'Intern & Brands')}
                            {field('verifier_name', 'Verifier')}
                            {field('upsell_by', 'Upsell By')}
                            {field('page', 'Page')}
                            {field('platform', 'Platform', {
                                suggestions: platforms,
                            })}
                        </Section>

                        <Section title="Customer">
                            {field('customer_name', 'Customer Name')}
                            {field('contact', 'Contact')}
                            {field('province', 'Province')}
                            {field('city', 'City')}
                            {field('brgy', 'Brgy')}
                            {field('address', 'Address')}
                        </Section>

                        <Section title="Fulfilment">
                            {field('tracking_number', 'Tracking No.')}
                            {field('courier', 'Courier')}
                            {field('parcel_status', 'Parcel Status', {
                                choices: PARCEL_STATUSES,
                            })}
                            {field('order_status', 'Order Status', {
                                suggestions: orderStatuses,
                            })}
                            {field('shipped_out_date', 'Shipped Out Date', {
                                type: 'date',
                            })}
                            {field('parcel_updated_date', 'Parcel Updated', {
                                type: 'datetime-local',
                            })}
                            {field('encoded_date', 'Encoded Date', {
                                type: 'datetime-local',
                            })}
                            {field('date_added', 'Date Added', {
                                type: 'datetime-local',
                            })}
                        </Section>
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
                            {processing ? 'Creating...' : 'Create Test Order'}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Section({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <div className="space-y-3 rounded-xl border border-dashed border-black/10 bg-black/[0.01] p-4 dark:border-white/10 dark:bg-white/[0.01]">
            <span className={labelClass}>{title}</span>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                {children}
            </div>
        </div>
    );
}
