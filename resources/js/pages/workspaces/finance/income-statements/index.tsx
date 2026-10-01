import PageHeader from '@/components/common/PageHeader';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link, router } from '@inertiajs/react';
import { Download, FileText, Lock, Trash2 } from 'lucide-react';
import moment from 'moment';
import { useState } from 'react';

interface StatementRow {
    id: number;
    period_month: string;
    total_delivered: number;
    delivered_orders: number;
    gross_profit_delivered_cogs: number;
    status: string;
    generated_at: string | null;
    /** Set once the month is closed — a locked statement can't be deleted. */
    locked_at: string | null;
}

interface Props {
    workspace: Workspace;
    statements: StatementRow[];
    currentMonth: string; // YYYY-MM
}

const fmt = (v: number) =>
    Number(v).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

export default function IncomeStatementsIndex({
    workspace,
    statements,
    currentMonth,
}: Props) {
    const base = `/workspaces/${workspace.slug}/finance/income-statements`;
    const [month, setMonth] = useState(currentMonth);

    const generate = () => {
        router.get(`${base}/preview`, { month });
    };

    const remove = (id: number) => {
        if (!confirm('Delete this income statement?')) return;
        router.delete(`${base}/${id}`, { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Income Statements`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Income Statements"
                    description="Monthly delivered revenue less expenses, grouped by transaction type."
                >
                    <div className="flex items-end gap-2">
                        <div className="flex flex-col">
                            <label className="mb-1 text-xs text-muted-foreground">
                                Month
                            </label>
                            <input
                                type="month"
                                value={month}
                                onChange={(e) => setMonth(e.target.value)}
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            />
                        </div>
                        <Button onClick={generate}>Generate</Button>
                    </div>
                </PageHeader>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Period
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Delivered
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Orders
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Gross Profit
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Generated
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
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No income statements yet. Pick a month
                                        and click Generate.
                                    </td>
                                </tr>
                            )}
                            {statements.map((s) => (
                                <tr
                                    key={s.id}
                                    className="border-t hover:bg-muted/30"
                                >
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-1.5">
                                            <Link
                                                href={`${base}/${s.id}`}
                                                className="font-medium text-primary hover:underline"
                                            >
                                                {moment(s.period_month).format(
                                                    'MMMM YYYY',
                                                )}
                                            </Link>
                                            {s.locked_at && (
                                                <span
                                                    title={`Locked ${moment(s.locked_at).format('MMM D, YYYY h:mm A')}`}
                                                    aria-label="Locked"
                                                >
                                                    <Lock className="h-3.5 w-3.5 text-amber-600" />
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {fmt(s.total_delivered)}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {Number(
                                            s.delivered_orders,
                                        ).toLocaleString('en-PH')}
                                    </td>
                                    {/* On the delivered-COGS basis, the one the
                                        statement leads with. */}
                                    <td
                                        className={`px-4 py-3 text-right font-medium tabular-nums ${
                                            s.gross_profit_delivered_cogs < 0
                                                ? 'text-red-600'
                                                : 'text-emerald-600'
                                        }`}
                                    >
                                        {fmt(s.gross_profit_delivered_cogs)}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {s.generated_at
                                            ? moment(s.generated_at).format(
                                                  'MMM D, YYYY h:mm A',
                                              )
                                            : '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="icon"
                                                title="View"
                                            >
                                                <Link href={`${base}/${s.id}`}>
                                                    <FileText className="h-4 w-4" />
                                                </Link>
                                            </Button>
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="icon"
                                                title="Export CSV"
                                            >
                                                <a
                                                    href={`${base}/${s.id}/export`}
                                                >
                                                    <Download className="h-4 w-4" />
                                                </a>
                                            </Button>
                                            {/* A locked month is held as it
                                                stands; deleting it loses more
                                                than regenerating would. */}
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                disabled={Boolean(s.locked_at)}
                                                title={
                                                    s.locked_at
                                                        ? 'Locked — unlock it on the statement to delete'
                                                        : 'Delete'
                                                }
                                                onClick={() => remove(s.id)}
                                            >
                                                <Trash2 className="h-4 w-4 text-red-600" />
                                            </Button>
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
