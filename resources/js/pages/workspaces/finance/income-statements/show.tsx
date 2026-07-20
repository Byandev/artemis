import PageHeader from '@/components/common/PageHeader';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Download, RefreshCw, Save } from 'lucide-react';
import moment from 'moment';
import { useMemo, useState } from 'react';

interface ExpenseRow {
    type_key: number;
    type_name: string;
    amount: number;
    included: boolean;
}

interface Statement {
    id: number | null;
    period_month: string; // YYYY-MM-DD
    delivered: number;
    orders: number;
    total_expenses?: number;
    net_profit?: number;
    generated_at?: string | null;
    expenses: ExpenseRow[];
}

interface Props {
    workspace: Workspace;
    mode: 'preview' | 'saved';
    statement: Statement;
}

const fmt = (v: number) =>
    Number(v).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

export default function IncomeStatementShow({
    workspace,
    mode,
    statement,
}: Props) {
    const base = `/workspaces/${workspace.slug}/finance/income-statements`;
    const isPreview = mode === 'preview';
    const month = moment(statement.period_month).format('YYYY-MM');
    const monthLabel = moment(statement.period_month).format('MMMM YYYY');

    const [rows, setRows] = useState<ExpenseRow[]>(statement.expenses);
    const [saving, setSaving] = useState(false);

    const toggle = (key: number, checked: boolean) =>
        setRows((prev) =>
            prev.map((r) =>
                r.type_key === key ? { ...r, included: checked } : r,
            ),
        );

    // In preview the totals recompute live from the checkboxes; in saved mode the
    // server's frozen figures are authoritative.
    const totalExpenses = useMemo(() => {
        if (!isPreview) return statement.total_expenses ?? 0;
        return rows.filter((r) => r.included).reduce((s, r) => s + r.amount, 0);
    }, [isPreview, rows, statement.total_expenses]);

    const netProfit = isPreview
        ? statement.delivered - totalExpenses
        : (statement.net_profit ?? 0);

    const save = () => {
        setSaving(true);
        router.post(
            base,
            {
                month,
                included_keys: rows
                    .filter((r) => r.included)
                    .map((r) => r.type_key),
            },
            { onFinish: () => setSaving(false) },
        );
    };

    const regenerate = () => {
        if (!statement.id) return;
        router.post(
            `${base}/${statement.id}/regenerate`,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <AppLayout>
            <Head
                title={`${workspace.name} - Income Statement ${monthLabel}`}
            />
            <div className="mx-auto w-full max-w-(--breakpoint-lg) p-4 md:p-6">
                <PageHeader
                    title={`Income Statement — ${monthLabel}`}
                    description={
                        isPreview
                            ? 'Preview — pick which transaction types to include, then save.'
                            : statement.generated_at
                              ? `Saved snapshot · generated ${moment(statement.generated_at).format('MMM D, YYYY h:mm A')}`
                              : 'Saved snapshot'
                    }
                >
                    <div className="flex items-center gap-2">
                        <Button asChild variant="outline" size="sm">
                            <Link href={`${base}`}>
                                <ArrowLeft className="mr-1 h-4 w-4" />
                                Back
                            </Link>
                        </Button>
                        {isPreview ? (
                            <Button onClick={save} disabled={saving} size="sm">
                                <Save className="mr-1 h-4 w-4" />
                                {statement.id ? 'Save (overwrite)' : 'Save'}
                            </Button>
                        ) : (
                            <>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={regenerate}
                                >
                                    <RefreshCw className="mr-1 h-4 w-4" />
                                    Regenerate
                                </Button>
                                <Button asChild variant="outline" size="sm">
                                    <a href={`${base}/${statement.id}/export`}>
                                        <Download className="mr-1 h-4 w-4" />
                                        Export
                                    </a>
                                </Button>
                            </>
                        )}
                    </div>
                </PageHeader>

                {isPreview && statement.id && (
                    <div className="mb-4 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                        A statement already exists for {monthLabel}. Saving will
                        overwrite it.
                    </div>
                )}

                {/* Revenue */}
                <div className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div className="rounded-lg border p-4">
                        <div className="text-xs text-muted-foreground">
                            Total Delivered
                        </div>
                        <div className="mt-1 text-2xl font-semibold tabular-nums">
                            {fmt(statement.delivered)}
                        </div>
                    </div>
                    <div className="rounded-lg border p-4">
                        <div className="text-xs text-muted-foreground">
                            Delivered Orders
                        </div>
                        <div className="mt-1 text-2xl font-semibold tabular-nums">
                            {statement.orders.toLocaleString()}
                        </div>
                    </div>
                </div>

                {/* Expenses */}
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                {isPreview && <th className="w-12 px-4 py-3" />}
                                <th className="px-4 py-3 font-medium">
                                    Transaction Type
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Amount
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={isPreview ? 3 : 2}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No expense transactions for this month.
                                    </td>
                                </tr>
                            )}
                            {rows.map((r) => (
                                <tr
                                    key={r.type_key}
                                    className={`border-t ${
                                        isPreview && !r.included
                                            ? 'opacity-40'
                                            : ''
                                    }`}
                                >
                                    {isPreview && (
                                        <td className="px-4 py-3">
                                            <Checkbox
                                                checked={r.included}
                                                onCheckedChange={(v) =>
                                                    toggle(
                                                        r.type_key,
                                                        Boolean(v),
                                                    )
                                                }
                                            />
                                        </td>
                                    )}
                                    <td className="px-4 py-3">{r.type_name}</td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {fmt(r.amount)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="border-t bg-muted/30 font-medium">
                                <td
                                    className="px-4 py-3"
                                    colSpan={isPreview ? 2 : 1}
                                >
                                    Total Expenses
                                </td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {fmt(totalExpenses)}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {/* Net profit */}
                <div className="mt-4 flex items-center justify-between rounded-lg border bg-muted/30 px-4 py-4">
                    <div className="text-sm font-medium">Net Profit</div>
                    <div
                        className={`text-2xl font-bold tabular-nums ${
                            netProfit < 0 ? 'text-red-600' : 'text-emerald-600'
                        }`}
                    >
                        {fmt(netProfit)}
                    </div>
                </div>
                <p className="mt-2 text-xs text-muted-foreground">
                    Net Profit = Total Delivered − Total Expenses (included
                    types only).
                </p>
            </div>
        </AppLayout>
    );
}
