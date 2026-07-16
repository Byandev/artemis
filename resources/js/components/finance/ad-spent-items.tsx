import { inputCls } from '@/components/finance/account-form-dialog';
import { Plus, Trash2 } from 'lucide-react';
import { useMemo } from 'react';

export interface ProductOption {
    id: number;
    name: string;
}

export interface PageOption {
    id: number;
    name: string;
    product_id: number | null;
    budget_per_day: string | number | null;
    budget_date: string | null;
}

export interface AdSpentItem {
    id?: number;
    product_id: number | '';
    page_id: number | '';
    creatives_running: number | string;
    budget_per_day: number | string;
    days: number | string;
}

export const emptyItem = (): AdSpentItem => ({
    product_id: '',
    page_id: '',
    creatives_running: '',
    budget_per_day: '',
    days: '',
});

export const lineTotal = (item: AdSpentItem): number => {
    const total = Number(item.budget_per_day || 0) * Number(item.days || 0);
    return Number.isFinite(total) ? total : 0;
};

export const grandTotal = (items: AdSpentItem[]): number =>
    items.reduce((sum, item) => sum + lineTotal(item), 0);

const peso = (v: number) =>
    v.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const labelCls =
    'block font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';

interface Props {
    items: AdSpentItem[];
    onChange: (items: AdSpentItem[]) => void;
    products: ProductOption[];
    pages: PageOption[];
    errors: Record<string, string>;
}

export function AdSpentItems({
    items,
    onChange,
    products,
    pages,
    errors,
}: Props) {
    // A page reaches its product through its shop, so grouping the user's pages
    // by product is what lets picking a product narrow the page list down.
    const pagesByProduct = useMemo(() => {
        const map = new Map<number, PageOption[]>();
        pages.forEach((page) => {
            if (page.product_id === null) return;
            const list = map.get(page.product_id) ?? [];
            list.push(page);
            map.set(page.product_id, list);
        });
        return map;
    }, [pages]);

    /**
     * The pages offered for a product. A page is linked to a product only
     * through its shop (shops.product_id), and that link is often unset — so
     * rather than dead-ending on an empty dropdown, fall back to offering every
     * page the user runs and say so.
     */
    const pagesFor = (productId: number | '') => {
        if (!productId) return { options: [] as PageOption[], fallback: false };

        const matches = pagesByProduct.get(Number(productId)) ?? [];

        return matches.length
            ? { options: matches, fallback: false }
            : { options: pages, fallback: true };
    };

    const update = (index: number, patch: Partial<AdSpentItem>) => {
        onChange(
            items.map((item, i) =>
                i === index ? { ...item, ...patch } : item,
            ),
        );
    };

    const pickProduct = (index: number, productId: number | '') => {
        const { options, fallback } = pagesFor(productId);
        // Changing product invalidates the chosen page. When exactly one page
        // is on offer there is nothing to choose, so take it and pull its
        // budget straight away — but never presume on the fallback list.
        const only = !fallback && options.length === 1 ? options[0] : null;

        update(index, {
            product_id: productId,
            page_id: only?.id ?? '',
            budget_per_day:
                only?.budget_per_day != null ? String(only.budget_per_day) : '',
        });
    };

    const pickPage = (index: number, pageId: number | '') => {
        const page = pages.find((p) => p.id === pageId);

        update(index, {
            page_id: pageId,
            // Only overwrite from a page that actually has a budget on record;
            // otherwise leave whatever the user typed alone.
            ...(page?.budget_per_day != null
                ? { budget_per_day: String(page.budget_per_day) }
                : {}),
        });
    };

    const remove = (index: number) => {
        onChange(items.filter((_, i) => i !== index));
    };

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <span className={labelCls}>For Scaling / Running</span>
                {errors.items && (
                    <span className="font-mono text-[11px] text-red-500">
                        {errors.items}
                    </span>
                )}
            </div>

            <div className="space-y-2">
                {items.map((item, index) => {
                    const { options, fallback } = pagesFor(item.product_id);
                    const page = pages.find((p) => p.id === item.page_id);
                    const err = (field: string) =>
                        errors[`items.${index}.${field}`];

                    return (
                        <div
                            key={index}
                            className="rounded-[10px] border border-black/6 bg-stone-50/60 p-3 dark:border-white/6 dark:bg-white/2"
                        >
                            <div className="flex items-start gap-2">
                                <div className="grid flex-1 grid-cols-12 gap-2">
                                    <div className="col-span-12 space-y-1 sm:col-span-4">
                                        <label className={labelCls}>
                                            Item / Product
                                        </label>
                                        <select
                                            value={item.product_id}
                                            onChange={(e) =>
                                                pickProduct(
                                                    index,
                                                    e.target.value
                                                        ? Number(e.target.value)
                                                        : '',
                                                )
                                            }
                                            className={inputCls}
                                        >
                                            <option value="">Select…</option>
                                            {products.map((p) => (
                                                <option key={p.id} value={p.id}>
                                                    {p.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div className="col-span-12 space-y-1 sm:col-span-4">
                                        <label className={labelCls}>Page</label>
                                        <select
                                            value={item.page_id}
                                            onChange={(e) =>
                                                pickPage(
                                                    index,
                                                    e.target.value
                                                        ? Number(e.target.value)
                                                        : '',
                                                )
                                            }
                                            disabled={!item.product_id}
                                            className={`${inputCls} disabled:opacity-50`}
                                        >
                                            <option value="">
                                                {!item.product_id
                                                    ? 'Pick a product first'
                                                    : options.length
                                                      ? 'Select…'
                                                      : 'No pages assigned to you'}
                                            </option>

                                            {options.map((p) => (
                                                <option key={p.id} value={p.id}>
                                                    {p.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div className="col-span-4 space-y-1 sm:col-span-2">
                                        <label className={labelCls}>
                                            Creatives
                                        </label>
                                        <input
                                            type="number"
                                            min="0"
                                            step="1"
                                            value={item.creatives_running}
                                            onChange={(e) =>
                                                update(index, {
                                                    creatives_running:
                                                        e.target.value,
                                                })
                                            }
                                            className={inputCls}
                                        />
                                    </div>

                                    <div className="col-span-4 space-y-1 sm:col-span-2">
                                        <label className={labelCls}>Days</label>
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={item.days}
                                            onChange={(e) =>
                                                update(index, {
                                                    days: e.target.value,
                                                })
                                            }
                                            className={inputCls}
                                        />
                                    </div>

                                    <div className="col-span-6 space-y-1 sm:col-span-4">
                                        <label className={labelCls}>
                                            Budget / Day
                                        </label>
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={item.budget_per_day}
                                            onChange={(e) =>
                                                update(index, {
                                                    budget_per_day:
                                                        e.target.value,
                                                })
                                            }
                                            className={inputCls}
                                        />
                                    </div>

                                    <div className="col-span-6 space-y-1 sm:col-span-8">
                                        <label className={labelCls}>
                                            Total Request
                                        </label>
                                        <div className="flex h-9 items-center justify-end rounded-[10px] border border-black/6 bg-white px-3 font-mono text-[12px] font-medium text-gray-800 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-100">
                                            {peso(lineTotal(item))}
                                        </div>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    onClick={() => remove(index)}
                                    disabled={items.length === 1}
                                    title={
                                        items.length === 1
                                            ? 'An Ad Spent request needs at least one item'
                                            : 'Remove item'
                                    }
                                    className="mt-5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-black/6 bg-white text-gray-400 transition-all hover:border-red-200 hover:text-red-500 disabled:opacity-30 disabled:hover:border-black/6 disabled:hover:text-gray-400 dark:border-white/6 dark:bg-zinc-800"
                                >
                                    <Trash2 className="h-3.5 w-3.5" />
                                </button>
                            </div>

                            {page?.budget_date ? (
                                <p className="mt-2 font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                    Budget auto-filled from {page.name} ·{' '}
                                    {new Date(
                                        page.budget_date,
                                    ).toLocaleDateString('en-PH')}
                                </p>
                            ) : page ? (
                                <p className="mt-2 font-mono text-[10px] text-amber-600 dark:text-amber-500">
                                    No budget on record for {page.name} — enter
                                    it manually.
                                </p>
                            ) : fallback ? (
                                <p className="mt-2 font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                    No page is linked to this product, so all
                                    your pages are listed.
                                </p>
                            ) : null}

                            {[
                                'product_id',
                                'page_id',
                                'creatives_running',
                                'budget_per_day',
                                'days',
                            ]
                                .map(err)
                                .filter(Boolean)
                                .map((message) => (
                                    <p
                                        key={message}
                                        className="mt-1 font-mono text-[11px] text-red-500"
                                    >
                                        {message}
                                    </p>
                                ))}
                        </div>
                    );
                })}
            </div>

            <div className="flex items-center justify-between gap-3">
                <button
                    type="button"
                    onClick={() => onChange([...items, emptyItem()])}
                    className="flex h-8 items-center gap-1.5 rounded-lg border border-black/8 bg-white px-3 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                >
                    <Plus className="h-3.5 w-3.5" />
                    Add Item
                </button>

                <div className="flex items-center gap-3 rounded-[10px] bg-emerald-50 px-3 py-2 dark:bg-emerald-500/10">
                    <span className="font-mono text-[10px] font-medium tracking-wider text-emerald-700 uppercase dark:text-emerald-400">
                        Total
                    </span>
                    <span className="font-mono text-[13px] font-semibold text-emerald-700 dark:text-emerald-400">
                        ₱{peso(grandTotal(items))}
                    </span>
                </div>
            </div>
        </div>
    );
}
