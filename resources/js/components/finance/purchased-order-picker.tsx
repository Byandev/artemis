import { Field } from '@/components/finance/account-form-dialog';
import axios from 'axios';
import { format, parseISO } from 'date-fns';
import { ChevronDown, Loader2, X } from 'lucide-react';
import React, { useEffect } from 'react';

/** One product's quantity on an order — the weight its share is cut by. */
export interface PurchasedOrderProductQty {
    /**
     * The catalog product the order's SKU belongs to, or the SKU itself when it
     * has no product. Matches what a transaction's product share is keyed on.
     */
    product: string;
    qty: number;
    /** False for a SKU with no catalog product: allocated, but untagged. */
    mapped: boolean;
}

export interface PurchasedOrderOption {
    id: number;
    /** Delivery no., or the next reference the ERP filled in. */
    label: string;
    supplier: string | null;
    issue_date: string | null;
    status_label: string;
    total_amount: number;
    delivery_fee: number;
    products: PurchasedOrderProductQty[];
}

interface Props {
    workspaceSlug: string;
    selected: PurchasedOrderOption | null;
    onSelect: (order: PurchasedOrderOption | null) => void;
    error?: string;
}

const money = (n: number) =>
    n.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const shortDate = (iso: string | null) =>
    iso ? format(parseISO(iso), 'd MMM yyyy') : '—';

/** Matches the inputs the rest of the transaction form uses. */
const triggerCls =
    'flex h-9 w-full items-center justify-between gap-2 rounded-[10px] border border-black/6 bg-stone-100 px-3 text-left font-mono! text-[12px]! text-gray-700 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-200';

/**
 * Searchable picker for the purchase order a delivery fee belongs to.
 *
 * Orders are searched on the server (by delivery/PO/control number, supplier or
 * SKU) rather than shipped with the page: the list grows with every order ever
 * raised, and only a handful are ever wanted. Each result carries the order's
 * quantity per product, which is what the caller splits the fee by.
 */
export function PurchasedOrderPicker({
    workspaceSlug,
    selected,
    onSelect,
    error,
}: Props) {
    const [open, setOpen] = React.useState(false);
    const [search, setSearch] = React.useState('');
    const [options, setOptions] = React.useState<PurchasedOrderOption[]>([]);
    const [loading, setLoading] = React.useState(false);
    const [failed, setFailed] = React.useState(false);
    const containerRef = React.useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;

        const onClickOutside = (event: MouseEvent) => {
            if (!containerRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', onClickOutside);

        return () => document.removeEventListener('mousedown', onClickOutside);
    }, [open]);

    // Debounced so typing a delivery number doesn't fire a request per keystroke;
    // an aborted request can't land on top of a newer one.
    useEffect(() => {
        if (!open) return;

        const controller = new AbortController();
        const timer = setTimeout(() => {
            setLoading(true);
            setFailed(false);

            axios
                .get<{ data: PurchasedOrderOption[] }>(
                    `/workspaces/${workspaceSlug}/finance/purchased-orders`,
                    { params: { search }, signal: controller.signal },
                )
                .then(({ data }) => setOptions(data.data))
                .catch((e) => {
                    if (axios.isCancel(e)) return;
                    setFailed(true);
                    setOptions([]);
                })
                .finally(() => {
                    if (!controller.signal.aborted) setLoading(false);
                });
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [open, search, workspaceSlug]);

    const choose = (order: PurchasedOrderOption) => {
        onSelect(order);
        setOpen(false);
        setSearch('');
    };

    const unmapped = (selected?.products ?? []).filter((p) => !p.mapped);
    const totalQty = (selected?.products ?? []).reduce(
        (sum, p) => sum + p.qty,
        0,
    );

    return (
        <Field label="Purchase Order" error={error}>
            <div className="relative" ref={containerRef}>
                <button
                    type="button"
                    onClick={() => setOpen((o) => !o)}
                    className={triggerCls}
                >
                    <span className="truncate">
                        {selected
                            ? `${selected.label}${selected.supplier ? ` — ${selected.supplier}` : ''}`
                            : 'Search a purchase order…'}
                    </span>
                    <ChevronDown className="h-3.5 w-3.5 shrink-0 opacity-50" />
                </button>

                {selected && (
                    <button
                        type="button"
                        aria-label="Clear purchase order"
                        onClick={(e) => {
                            e.stopPropagation();
                            onSelect(null);
                        }}
                        className="absolute top-1/2 right-8 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    >
                        <X className="h-3.5 w-3.5" />
                    </button>
                )}

                {open && (
                    <div className="absolute z-50 mt-1 w-full overflow-hidden rounded-[10px] border border-black/6 bg-white shadow-lg dark:border-white/6 dark:bg-zinc-800">
                        <div className="flex items-center gap-2 border-b border-black/6 p-1.5 dark:border-white/6">
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Delivery no., PO no., supplier or SKU…"
                                autoFocus
                                className="h-7 w-full rounded-md bg-stone-100 px-2 text-[11px] outline-none placeholder:text-gray-400 dark:bg-zinc-700 dark:text-gray-200"
                            />
                            {loading && (
                                <Loader2 className="h-3.5 w-3.5 shrink-0 animate-spin text-gray-400" />
                            )}
                        </div>

                        <div className="max-h-64 overflow-y-auto p-1">
                            {options.map((order) => (
                                <button
                                    key={order.id}
                                    type="button"
                                    onClick={() => choose(order)}
                                    className="block w-full rounded-md px-2 py-1.5 text-left hover:bg-stone-100 dark:hover:bg-zinc-700"
                                >
                                    <span className="block truncate font-mono text-[11px] text-gray-700 dark:text-gray-200">
                                        {order.label}
                                        {order.supplier
                                            ? ` — ${order.supplier}`
                                            : ''}
                                    </span>
                                    <span className="block truncate font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                        {shortDate(order.issue_date)} ·{' '}
                                        {order.status_label} ·{' '}
                                        {order.products.length} product
                                        {order.products.length === 1
                                            ? ''
                                            : 's'}{' '}
                                        · delivery fee{' '}
                                        {money(order.delivery_fee)}
                                    </span>
                                </button>
                            ))}

                            {!loading && options.length === 0 && (
                                <p className="px-2 py-3 text-center font-mono text-[11px] text-gray-400">
                                    {failed
                                        ? 'Could not load purchase orders.'
                                        : 'No purchase orders found.'}
                                </p>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {selected && (
                <div className="mt-2 space-y-1 rounded-[10px] border border-black/8 bg-stone-50 p-2.5 dark:border-white/8 dark:bg-zinc-800/60">
                    <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                        {shortDate(selected.issue_date)} ·{' '}
                        {selected.status_label} · order total{' '}
                        {money(selected.total_amount)} · its own delivery fee{' '}
                        {money(selected.delivery_fee)}
                    </p>

                    {selected.products.map((p) => (
                        <div
                            key={p.product}
                            className="flex items-center justify-between gap-2 font-mono text-[11px]"
                        >
                            <span className="min-w-0 flex-1 truncate text-gray-600 dark:text-gray-300">
                                {p.product}
                                {!p.mapped && (
                                    <span className="ml-1 text-amber-600 dark:text-amber-500">
                                        (no catalog product)
                                    </span>
                                )}
                            </span>
                            <span className="shrink-0 text-gray-400 dark:text-gray-500">
                                {p.qty.toLocaleString()} pc
                                {p.qty === 1 ? '' : 's'}
                                {totalQty > 0 &&
                                    ` · ${((p.qty / totalQty) * 100).toFixed(1)}%`}
                            </span>
                        </div>
                    ))}

                    {selected.products.length === 0 && (
                        <p className="font-mono text-[11px] text-amber-600 dark:text-amber-500">
                            This order has no quantities to split the amount by.
                        </p>
                    )}

                    {unmapped.length > 0 && (
                        <p className="font-mono text-[10px] text-amber-600 dark:text-amber-500">
                            {unmapped.length} line
                            {unmapped.length === 1 ? '' : 's'} sit
                            {unmapped.length === 1 ? 's' : ''} on a SKU with no
                            catalog product. Their share is still allocated, but
                            it won&apos;t reach any product on the income
                            statement until the SKU is linked.
                        </p>
                    )}
                </div>
            )}

            <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                Picking an order splits the amount across its products in
                proportion to the quantity of each it carried. The split follows
                the amount as you change it, until you edit a share by hand.
            </p>
        </Field>
    );
}
