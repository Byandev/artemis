import PageHeader from '@/components/common/PageHeader';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link, router } from '@inertiajs/react';
import { Download, FileText, Trash2 } from 'lucide-react';
import moment from 'moment';
import { useState } from 'react';

interface StatementRow {
    id: number;
    product: string;
    period_month: string;
    total_delivered: number;
    total_expenses: number;
    net_profit: number;
    status: string;
    generated_at: string | null;
}

interface ProductOption {
    product: string;
    orders: number;
}

interface Props {
    workspace: Workspace;
    statements: StatementRow[];
    products: ProductOption[];
    currentMonth: string; // YYYY-MM
}

const fmt = (v: number) =>
    Number(v).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

export default function ProductIncomeStatementsIndex({
    workspace,
    statements,
    products,
    currentMonth,
}: Props) {
    const base = `/workspaces/${workspace.slug}/finance/product-income-statements`;
    const [product, setProduct] = useState('');
    const [month, setMonth] = useState(currentMonth);

    const generate = () => {
        if (!product.trim()) return;
        router.get(`${base}/preview`, { product: product.trim(), month });
    };

    const remove = (id: number) => {
        if (!confirm('Delete this product income statement?')) return;
        router.delete(`${base}/${id}`, { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Product Income Statements`} />
            <div className="w-full p-4 font-mono md:p-6">
                <PageHeader
                    title="Product Income Statements"
                    description="Per-product P&L — product is the order's normalized item name."
                >
                    <div className="flex flex-wrap items-end gap-2">
                        <div className="flex flex-col">
                            <label className="mb-1 text-[10px] tracking-wider text-gray-400 uppercase">
                                Product
                            </label>
                            <input
                                list="product-options"
                                value={product}
                                onChange={(e) => setProduct(e.target.value)}
                                placeholder="Search product…"
                                className="h-9 w-72 rounded-md border border-input bg-background px-3 text-sm"
                            />
                            <datalist id="product-options">
                                {products.map((p) => (
                                    <option key={p.product} value={p.product}>
                                        {p.orders.toLocaleString()} orders
                                    </option>
                                ))}
                            </datalist>
                        </div>
                        <div className="flex flex-col">
                            <label className="mb-1 text-[10px] tracking-wider text-gray-400 uppercase">
                                Month
                            </label>
                            <input
                                type="month"
                                value={month}
                                onChange={(e) => setMonth(e.target.value)}
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            />
                        </div>
                        <button
                            onClick={generate}
                            disabled={!product.trim()}
                            className="flex h-9 items-center rounded-lg bg-emerald-600 px-4 text-[12px] font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                        >
                            Generate
                        </button>
                    </div>
                </PageHeader>

                <div className="overflow-x-auto rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <table className="w-full text-sm">
                        <thead className="border-b border-black/6 text-left text-[10px] tracking-wider text-gray-400 uppercase dark:border-white/6">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Product
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Period
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Delivered
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Expenses
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Net Profit
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {statements.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-gray-400"
                                    >
                                        No product statements yet. Pick a
                                        product and month, then Generate.
                                    </td>
                                </tr>
                            )}
                            {statements.map((s) => (
                                <tr
                                    key={s.id}
                                    className="border-t border-black/5 hover:bg-stone-50 dark:border-white/5 dark:hover:bg-zinc-800/40"
                                >
                                    <td className="px-4 py-3">
                                        <Link
                                            href={`${base}/${s.id}`}
                                            className="font-medium text-emerald-700 hover:underline dark:text-emerald-400"
                                        >
                                            {s.product}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3 text-gray-500">
                                        {moment(s.period_month).format(
                                            'MMM YYYY',
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {fmt(s.total_delivered)}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {fmt(s.total_expenses)}
                                    </td>
                                    <td
                                        className={`px-4 py-3 text-right font-medium tabular-nums ${
                                            s.net_profit < 0
                                                ? 'text-rose-600'
                                                : 'text-emerald-600'
                                        }`}
                                    >
                                        {fmt(s.net_profit)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-2 text-gray-400">
                                            <Link
                                                href={`${base}/${s.id}`}
                                                title="View"
                                                className="hover:text-gray-700 dark:hover:text-gray-200"
                                            >
                                                <FileText className="h-4 w-4" />
                                            </Link>
                                            <a
                                                href={`${base}/${s.id}/export`}
                                                title="Export CSV"
                                                className="hover:text-gray-700 dark:hover:text-gray-200"
                                            >
                                                <Download className="h-4 w-4" />
                                            </a>
                                            <button
                                                onClick={() => remove(s.id)}
                                                title="Delete"
                                                className="hover:text-rose-600"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
