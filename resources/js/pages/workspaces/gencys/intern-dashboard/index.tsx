import PageHeader from '@/components/common/PageHeader';
import DatePicker from '@/components/ui/date-picker';
import AppLayout from '@/layouts/app-layout';
import InternMultiSelect, {
    InternOption,
} from '@/pages/workspaces/gencys/components/intern-multi-select';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp } from 'lucide-react';
import { useState } from 'react';

interface Row {
    id: number;
    name: string | null;
    orders: number | null;
    status: 'up' | 'down' | 'flat';
    latest_sales: number | null;
    previous_sales: number | null;
    change: number | null;
    total_to_date: number | null;
    rank: number;
    roas_a: number | null;
    roas_b: number | null;
}

interface Subtotal {
    orders: number;
    latest_sales: number;
    previous_sales: number;
    change: number;
    total_to_date: number;
    roas_a: number;
    roas_b: number;
}

interface Props {
    workspace: Workspace;
    interns: InternOption[];
    view: {
        report_date: string | null;
        report_label: string | null;
        roas_a_label: string | null;
        roas_b_label: string | null;
        rows: Row[];
        subtotal: Subtotal | null;
    };
    filters: { gencys_intern_id: string[]; date: string | null };
}

// ─── Formatters ────────────────────────────────────────────────────────────────
const peso = (v: number | null) =>
    v === null
        ? '—'
        : `${v < 0 ? '-' : ''}₱${Math.abs(v).toLocaleString('en-PH', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          })}`;

const roasText = (v: number | null) => (v === null ? '—' : v.toFixed(2));

// Theme-aware conditional text/fills (Artemis emerald / red / amber).
const changeTone = (v: number | null) =>
    v === null || v === 0
        ? 'text-gray-500 dark:text-gray-400'
        : v > 0
          ? 'bg-emerald-500/[0.06] text-emerald-600 dark:text-emerald-400'
          : 'bg-red-500/[0.06] text-red-600 dark:text-red-400';

const roasTone = (v: number | null) =>
    v === null
        ? 'text-gray-400 dark:text-gray-600'
        : v >= 3
          ? 'bg-emerald-500/[0.06] text-emerald-600 dark:text-emerald-400'
          : 'bg-red-500/[0.06] text-red-600 dark:text-red-400';

// Shared cell chrome.
const cell =
    'border-b border-black/5 px-3 py-2 whitespace-nowrap dark:border-white/5';
const numCell = `${cell} text-right font-mono tabular-nums text-gray-700 dark:text-gray-300`;

// ─── Presentational bits ────────────────────────────────────────────────────────
function StatusPill({ status }: { status: Row['status'] }) {
    if (status === 'flat')
        return <span className="text-gray-300 dark:text-gray-600">—</span>;
    const up = status === 'up';
    return (
        <span
            className={`inline-flex h-5 w-5 items-center justify-center rounded-md ${
                up
                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                    : 'bg-red-500/10 text-red-600 dark:text-red-400'
            }`}
        >
            {up ? (
                <ArrowUp className="h-3.5 w-3.5" strokeWidth={2.5} />
            ) : (
                <ArrowDown className="h-3.5 w-3.5" strokeWidth={2.5} />
            )}
        </span>
    );
}

function RankBadge({ rank }: { rank: number }) {
    const medal =
        rank === 1
            ? {
                  emoji: '🥇',
                  cls: 'bg-amber-400/15 text-amber-700 dark:text-amber-300',
              }
            : rank === 2
              ? {
                    emoji: '🥈',
                    cls: 'bg-zinc-400/15 text-zinc-600 dark:text-zinc-300',
                }
              : rank === 3
                ? {
                      emoji: '🥉',
                      cls: 'bg-amber-700/15 text-amber-800 dark:text-amber-500',
                  }
                : {
                      emoji: '',
                      cls: 'bg-black/5 text-gray-500 dark:bg-white/5 dark:text-gray-400',
                  };

    const suffix =
        rank % 10 === 1 && rank !== 11
            ? 'st'
            : rank % 10 === 2 && rank !== 12
              ? 'nd'
              : rank % 10 === 3 && rank !== 13
                ? 'rd'
                : 'th';

    return (
        <span
            className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 font-mono text-[11px] font-semibold ${medal.cls}`}
        >
            {rank}
            {suffix}
            {medal.emoji && <span className="not-italic">{medal.emoji}</span>}
        </span>
    );
}

function DataRow({ row }: { row: Row }) {
    return (
        <tr className="transition-colors hover:bg-black/[0.015] dark:hover:bg-white/[0.02]">
            <td
                className={`${cell} font-medium text-gray-800 uppercase dark:text-gray-200`}
            >
                {row.name ?? `#${row.id}`}
            </td>
            <td
                className={`${cell} text-center font-mono text-gray-600 tabular-nums dark:text-gray-400`}
            >
                {row.orders ?? '—'}
            </td>
            <td className={`${cell} text-center`}>
                <StatusPill status={row.status} />
            </td>
            <td className={numCell}>{peso(row.latest_sales)}</td>
            <td className={numCell}>{peso(row.previous_sales)}</td>
            <td
                className={`${cell} text-right font-mono tabular-nums ${changeTone(row.change)}`}
            >
                {peso(row.change)}
            </td>
            <td
                className={`${cell} bg-amber-500/[0.05] text-right font-mono font-medium text-gray-800 tabular-nums dark:text-gray-200`}
            >
                {peso(row.total_to_date)}
            </td>
            <td className={`${cell} text-center`}>
                <RankBadge rank={row.rank} />
            </td>
            <td
                className={`${cell} text-center font-mono font-medium tabular-nums ${roasTone(row.roas_a)}`}
            >
                {roasText(row.roas_a)}
            </td>
            <td
                className={`${cell} text-center font-mono font-medium tabular-nums ${roasTone(row.roas_b)}`}
            >
                {roasText(row.roas_b)}
            </td>
        </tr>
    );
}

function SubtotalRow({ st }: { st: Subtotal }) {
    const darkCell =
        'border-b border-black/5 bg-zinc-800 px-3 py-2 text-center font-mono text-[12px] font-semibold text-white tabular-nums dark:border-white/5 dark:bg-zinc-950';

    return (
        <tr className="bg-stone-50 dark:bg-white/2">
            <td
                className={`${cell} font-mono text-[11px] font-semibold text-gray-500 italic dark:text-gray-400`}
            >
                Sub-Total
            </td>
            <td
                className={`${cell} text-center font-mono font-semibold text-gray-700 tabular-nums dark:text-gray-300`}
            >
                {st.orders.toLocaleString('en-PH')}
            </td>
            <td className={cell} />
            <td className={`${numCell} font-semibold`}>
                {peso(st.latest_sales)}
            </td>
            <td className={`${numCell} font-semibold`}>
                {peso(st.previous_sales)}
            </td>
            <td
                className={`${cell} text-right font-mono font-semibold tabular-nums ${changeTone(st.change)}`}
            >
                {peso(st.change)}
            </td>
            <td
                className={`${cell} bg-amber-500/[0.08] text-right font-mono font-semibold text-gray-800 tabular-nums dark:text-gray-100`}
            >
                {peso(st.total_to_date)}
            </td>
            <td className={darkCell}>EXPO. ROAS</td>
            <td className={darkCell}>{roasText(st.roas_a)}</td>
            <td className={darkCell}>{roasText(st.roas_b)}</td>
        </tr>
    );
}

export default function InternDashboard({
    workspace,
    interns,
    view,
    filters,
}: Props) {
    const [internIds, setInternIds] = useState<string[]>(
        filters.gencys_intern_id ?? [],
    );
    const [date, setDate] = useState<string>(filters.date ?? '');
    const baseUrl = `/workspaces/${workspace.slug}/gencys/intern-dashboard`;

    const reload = (next: { gencys_intern_id?: string[]; date?: string }) => {
        router.get(
            baseUrl,
            {
                filter: {
                    gencys_intern_id: next.gencys_intern_id ?? internIds,
                    date: next.date ?? date,
                },
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['view', 'filters'],
            },
        );
    };

    const applyInterns = (ids: string[]) => {
        setInternIds(ids);
        reload({ gencys_intern_id: ids });
    };

    const applyDate = (value: string) => {
        setDate(value);
        reload({ date: value });
    };

    const headCell =
        'border-b border-black/6 bg-stone-50 px-3 py-2.5 text-center align-middle font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:border-white/6 dark:bg-white/2 dark:text-gray-500';

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Intern Dashboard`} />

            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Interns Quick Data View"
                    description={
                        view.report_label
                            ? `Sales / ROAS · report date ${view.report_label}`
                            : 'Per-intern sales & ROAS from Gencys ERP'
                    }
                    stackActionsOnMobile
                >
                    <InternMultiSelect
                        interns={interns}
                        selected={internIds}
                        onApply={applyInterns}
                    />
                    <DatePicker
                        id="intern-dashboard-date"
                        key={date}
                        mode="single"
                        placeholder="Report date"
                        defaultDate={date || undefined}
                        onChange={(_dates, dateStr) => {
                            if (dateStr) applyDate(dateStr);
                        }}
                    />
                </PageHeader>

                <div className="mt-4 overflow-x-auto rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <table className="w-full min-w-[1080px] border-collapse text-[12px] text-gray-800 dark:text-gray-200">
                        <thead>
                            <tr>
                                <th className={`${headCell} text-left`}>
                                    Name
                                </th>
                                <th className={headCell}>Latest Orders</th>
                                <th className={headCell}>Status</th>
                                <th className={headCell}>Latest Sales</th>
                                <th className={headCell}>Prev. Day Sales</th>
                                <th className={headCell}>Change (+/-)</th>
                                <th className={headCell}>
                                    Total Sales To-Date
                                </th>
                                <th className={headCell}>Top Sales Ranking</th>
                                <th
                                    className={`${headCell} text-emerald-600/70 dark:text-emerald-400/60`}
                                >
                                    {view.roas_a_label ?? '—'} ROAS
                                </th>
                                <th
                                    className={`${headCell} text-emerald-600/70 dark:text-emerald-400/60`}
                                >
                                    {view.roas_b_label ?? '—'} ROAS
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            {view.rows.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={10}
                                        className="px-3 py-16 text-center text-[12px] text-gray-400 dark:text-gray-500"
                                    >
                                        No intern records for this date.
                                    </td>
                                </tr>
                            )}

                            {view.rows.map((row) => (
                                <DataRow key={row.id} row={row} />
                            ))}

                            {view.subtotal && (
                                <SubtotalRow st={view.subtotal} />
                            )}
                        </tbody>
                    </table>
                </div>

                <p className="mt-3 font-mono text-[11px] text-gray-400 dark:text-gray-500">
                    REMARKS: —
                </p>
            </div>
        </AppLayout>
    );
}
