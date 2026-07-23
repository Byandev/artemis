import PageHeader from '@/components/common/PageHeader';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link, router } from '@inertiajs/react';
import { Download, FileText, Trash2 } from 'lucide-react';
import moment from 'moment';
import { useState } from 'react';

interface StatementRow {
    id: number;
    intern_name: string;
    period_month: string;
    total_delivered: number;
    gross_profit: number;
    total_opex: number;
    net_profit: number;
    status: string;
    generated_at: string | null;
}

interface InternOpt {
    id: number;
    name: string;
}

interface Props {
    workspace: Workspace;
    statements: StatementRow[];
    interns: InternOpt[];
    currentMonth: string; // YYYY-MM
}

const fmt = (v: number) =>
    Number(v).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

export default function UserIncomeStatementsIndex({
    workspace,
    statements,
    interns,
    currentMonth,
}: Props) {
    const base = `/workspaces/${workspace.slug}/finance/user-income-statements`;
    const [internId, setInternId] = useState<string>(
        interns[0] ? String(interns[0].id) : '',
    );
    const [month, setMonth] = useState(currentMonth);

    const generate = () => {
        if (!internId) return;
        router.get(`${base}/preview`, { intern_id: internId, month });
    };

    const remove = (id: number) => {
        if (!confirm('Delete this income statement?')) return;
        router.delete(`${base}/${id}`, { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title={`${workspace.name} - User Income Statements`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="User Income Statements"
                    description="Per-intern monthly P&L — delivered revenue broken down by product."
                >
                    <div className="flex flex-wrap items-end gap-2">
                        <div className="flex flex-col">
                            <label className="mb-1 text-xs text-muted-foreground">
                                Intern
                            </label>
                            <select
                                value={internId}
                                onChange={(e) => setInternId(e.target.value)}
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                {interns.length === 0 && (
                                    <option value="">No interns</option>
                                )}
                                {interns.map((i) => (
                                    <option key={i.id} value={i.id}>
                                        {i.name}
                                    </option>
                                ))}
                            </select>
                        </div>
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
                        <Button onClick={generate} disabled={!internId}>
                            Generate
                        </Button>
                    </div>
                </PageHeader>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Intern</th>
                                <th className="px-4 py-3 font-medium">Period</th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Delivered
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Gross
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    OPEX
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Net Profit
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
                                        colSpan={8}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No statements yet. Pick an intern and
                                        month, then click Generate.
                                    </td>
                                </tr>
                            )}
                            {statements.map((s) => (
                                <tr
                                    key={s.id}
                                    className="border-t hover:bg-muted/30"
                                >
                                    <td className="px-4 py-3">
                                        <Link
                                            href={`${base}/${s.id}`}
                                            className="font-medium text-primary hover:underline"
                                        >
                                            {s.intern_name}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">
                                        {moment(s.period_month).format(
                                            'MMMM YYYY',
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {fmt(s.total_delivered)}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {fmt(s.gross_profit)}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {fmt(s.total_opex)}
                                    </td>
                                    <td
                                        className={`px-4 py-3 text-right font-medium tabular-nums ${
                                            s.net_profit < 0
                                                ? 'text-red-600'
                                                : 'text-emerald-600'
                                        }`}
                                    >
                                        {fmt(s.net_profit)}
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
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                title="Delete"
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
