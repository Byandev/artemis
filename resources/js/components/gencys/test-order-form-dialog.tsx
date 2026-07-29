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
import { X } from 'lucide-react';
import React, { useEffect } from 'react';
import { toast } from 'sonner';

interface Props {
    workspace: Workspace;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Existing values, offered as datalist suggestions. */
    csrs?: string[];
    platforms?: string[];
    parcelStatuses?: string[];
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

/**
 * Defaults produce an order that already counts as delivered revenue for the
 * current month, which is the common case when testing income statements.
 */
function defaults() {
    return {
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
        order_details: '',
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
type Field = keyof FormData;

export function TestOrderFormDialog({
    workspace,
    open,
    onOpenChange,
    csrs = [],
    platforms = [],
    parcelStatuses = [],
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
        } = {},
    ) => {
        const listId = opts.suggestions?.length
            ? `test-order-${name}-options`
            : undefined;

        return (
            <div className="space-y-1.5">
                <label className={labelClass}>{label}</label>
                <input
                    type={opts.type ?? 'text'}
                    step={opts.step}
                    list={listId}
                    placeholder={opts.placeholder}
                    value={data[name]}
                    onChange={(e) => setData(name, e.target.value)}
                    className={inputClass}
                />
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
                            <div className="sm:col-span-2">
                                {field('order_details', 'Order Details', {
                                    placeholder: '1x2X MAGNERVE,1x HIKARIJOINT',
                                })}
                                <p className="mt-1 font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                    Comma-separated; each item splits on the
                                    first “x” into qty and sku.
                                </p>
                            </div>
                        </Section>

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
                                suggestions: parcelStatuses,
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
