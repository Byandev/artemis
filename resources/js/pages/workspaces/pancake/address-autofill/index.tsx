import PageHeader from '@/components/common/PageHeader';
import { DataTable } from '@/components/ui/data-table';
import DatePicker from '@/components/ui/date-picker';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    MessageSquareOff,
    MinusCircle,
    XCircle,
} from 'lucide-react';
import moment from 'moment';
import { useMemo } from 'react';
import { DailyCostChart, DailyOutcomeChart } from './partials/Charts';

type Status =
    | 'updated'
    | 'dry_run'
    | 'needs_review'
    | 'no_address'
    | 'skipped'
    | 'failed'
    | 'queued';

export interface DailyPoint {
    date: string;
    filled: number;
    needs_review: number;
    no_address: number;
    failed: number;
    skipped: number;
    cost_usd: number;
}

interface Summary {
    total: number;
    counts: Record<Status, number>;
    filled: number;
    fill_rate: number | null;
    match_rate: number | null;
    cost_usd: number;
    ai_checks: number;
    avg_cost_usd: number | null;
    cost_per_fill_usd: number | null;
    input_tokens: number;
    output_tokens: number;
}

type Level = 'province' | 'district' | 'commune';

interface Review {
    reasons: { reason: string; total: number }[];
    unmatched: Record<Level, number>;
    picked: Record<Level, number>;
    rescued_by_shortlist: number;
}

interface Row {
    id: number;
    shop: string | null;
    pancake_order_id: string;
    status: Status;
    reason: string | null;
    attempts: number;
    address_text: string | null;
    formatted_address: string | null;
    match_status: string | null;
    picked_by_ai: Level[];
    ai_cost_usd: number | null;
    created_at: string;
}

interface Props {
    workspace: Workspace;
    shops: { id: number; name: string; auto_fill_address: boolean }[];
    summary: Summary;
    daily: DailyPoint[];
    review: Review;
    rows: PaginatedData<Row>;
    filters: {
        date_from: string;
        date_to: string;
        shop_id: string | null;
        status: Status | null;
    };
    dryRun: boolean;
}

const STATUS_META: Record<
    Status,
    { label: string; icon: typeof CheckCircle2; className: string }
> = {
    updated: {
        label: 'Updated',
        icon: CheckCircle2,
        className: 'text-emerald-700 dark:text-emerald-400',
    },
    dry_run: {
        label: 'Would update',
        icon: CheckCircle2,
        className: 'text-emerald-700 dark:text-emerald-400',
    },
    needs_review: {
        label: 'Needs review',
        icon: AlertTriangle,
        className: 'text-amber-700 dark:text-amber-400',
    },
    no_address: {
        label: 'No address',
        icon: MessageSquareOff,
        className: 'text-blue-700 dark:text-blue-400',
    },
    failed: {
        label: 'Failed',
        icon: XCircle,
        className: 'text-red-700 dark:text-red-400',
    },
    skipped: {
        label: 'Skipped',
        icon: MinusCircle,
        className: 'text-gray-500 dark:text-gray-400',
    },
    queued: {
        label: 'Waiting',
        icon: Clock,
        className: 'text-gray-500 dark:text-gray-400',
    },
};

const STATUS_FILTERS: Status[] = [
    'updated',
    'dry_run',
    'needs_review',
    'no_address',
    'failed',
    'skipped',
    'queued',
];

const LEVEL_LABELS: Record<Level, string> = {
    province: 'Province',
    district: 'District',
    commune: 'Commune',
};

const usd = (v: number | null, digits = 4) =>
    v === null ? '—' : `$${v.toFixed(digits)}`;
const pct = (v: number | null) =>
    v === null ? '—' : `${Math.round(v * 100)}%`;

export default function AddressAutofillIndex({
    workspace,
    shops,
    summary,
    daily,
    review,
    rows,
    filters,
    dryRun,
}: Props) {
    const baseUrl = `/workspaces/${workspace.slug}/pancake/address-autofill`;

    const go = (next: Partial<Props['filters']> & { page?: number }) => {
        const merged = { ...filters, ...next };
        router.get(
            baseUrl,
            {
                filter: {
                    date_from: merged.date_from,
                    date_to: merged.date_to,
                    ...(merged.shop_id ? { shop_id: merged.shop_id } : {}),
                    ...(merged.status ? { status: merged.status } : {}),
                },
                page: next.page ?? 1,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const columns: ColumnDef<Row>[] = useMemo(
        () => [
            {
                id: 'created_at',
                header: () => <Th>Time</Th>,
                cell: ({ row }) => (
                    <span className="font-mono text-[11px] whitespace-nowrap text-gray-500">
                        {moment(row.original.created_at).format(
                            'MMM D, h:mm A',
                        )}
                    </span>
                ),
            },
            {
                id: 'order',
                header: () => <Th>Order</Th>,
                cell: ({ row }) => (
                    <div className="leading-tight">
                        <a
                            href={`/workspaces/${workspace.slug}/pancake/orders?filter[search]=${row.original.pancake_order_id}`}
                            className="font-mono text-[11px] font-medium text-emerald-700 hover:underline dark:text-emerald-400"
                        >
                            #{row.original.pancake_order_id}
                        </a>
                        <div className="text-[11px] text-gray-400">
                            {row.original.shop ?? '—'}
                        </div>
                    </div>
                ),
            },
            {
                id: 'status',
                header: () => <Th>Result</Th>,
                cell: ({ row }) => {
                    const meta = STATUS_META[row.original.status];
                    const Icon = meta?.icon ?? MinusCircle;
                    return (
                        <div className="max-w-[220px] leading-tight">
                            <span
                                className={`inline-flex items-center gap-1 text-[12px] font-medium ${meta?.className ?? ''}`}
                            >
                                <Icon className="size-3.5" />
                                {meta?.label ?? row.original.status}
                            </span>
                            {row.original.reason && (
                                <div
                                    className="truncate text-[11px] text-gray-400"
                                    title={row.original.reason}
                                >
                                    {row.original.reason}
                                </div>
                            )}
                        </div>
                    );
                },
            },
            {
                id: 'address',
                header: () => <Th>Typed → matched</Th>,
                cell: ({ row }) => (
                    <div className="max-w-[360px] leading-tight">
                        <div
                            className="truncate text-[12px] text-gray-700 dark:text-gray-300"
                            title={row.original.address_text ?? ''}
                        >
                            {row.original.address_text || '—'}
                        </div>
                        {row.original.formatted_address && (
                            <div
                                className="truncate text-[11px] text-gray-400"
                                title={row.original.formatted_address}
                            >
                                → {row.original.formatted_address}
                                {row.original.picked_by_ai?.length > 0 && (
                                    <span className="ml-1.5 rounded bg-violet-50 px-1 py-px text-[10px] text-violet-700 dark:bg-violet-500/10 dark:text-violet-300">
                                        AI picked{' '}
                                        {row.original.picked_by_ai
                                            .map((l) => LEVEL_LABELS[l])
                                            .join(', ')
                                            .toLowerCase()}
                                    </span>
                                )}
                            </div>
                        )}
                    </div>
                ),
            },
            {
                id: 'attempts',
                header: () => <Th>Reads</Th>,
                cell: ({ row }) => (
                    <span className="font-mono text-[11px] text-gray-500">
                        {row.original.attempts}
                    </span>
                ),
            },
            {
                id: 'cost',
                header: () => <Th>AI cost</Th>,
                cell: ({ row }) => (
                    <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                        {usd(row.original.ai_cost_usd, 5)}
                    </span>
                ),
            },
        ],
        [workspace.slug],
    );

    return (
        <AppLayout>
            <Head title="Address Auto-fill" />
            <div className="space-y-5 p-4 md:p-6">
                <PageHeader
                    title="Address Auto-fill"
                    description="How the AI is doing at filling in new orders' addresses from the Messenger chat."
                />

                {dryRun && (
                    <p className="rounded-[10px] border border-amber-200 bg-amber-50 px-4 py-2.5 text-[12px] text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                        Dry run is on — orders are read and matched, but Pancake
                        is not updated. "Would update" counts as filled below.
                    </p>
                )}

                {/* Filters — one row, above everything they affect. */}
                <div className="flex flex-wrap items-center gap-3">
                    <div className="w-[260px]">
                        <DatePicker
                            id="autofill-date-range"
                            mode="range"
                            placeholder="Date range"
                            defaultDate={[filters.date_from, filters.date_to]}
                            onChange={(dates) => {
                                if (dates.length === 2) {
                                    go({
                                        date_from: moment(dates[0]).format(
                                            'YYYY-MM-DD',
                                        ),
                                        date_to: moment(dates[1]).format(
                                            'YYYY-MM-DD',
                                        ),
                                    });
                                }
                            }}
                        />
                    </div>
                    <Select
                        value={filters.shop_id ?? 'all'}
                        onValueChange={(v) =>
                            go({ shop_id: v === 'all' ? null : v })
                        }
                    >
                        <SelectTrigger className="w-[220px]">
                            <SelectValue placeholder="All shops" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All shops</SelectItem>
                            {shops.map((s) => (
                                <SelectItem key={s.id} value={String(s.id)}>
                                    {s.name}
                                    {s.auto_fill_address ? '' : ' (off)'}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {/* Headline numbers */}
                <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                    <Tile
                        label="Orders checked"
                        value={summary.total.toLocaleString()}
                        hint={`${summary.counts.skipped} skipped · ${summary.counts.queued} waiting`}
                    />
                    <Tile
                        label="Filled"
                        value={summary.filled.toLocaleString()}
                        hint={`${pct(summary.fill_rate)} of orders read`}
                    />
                    <Tile
                        label="Match rate"
                        value={pct(summary.match_rate)}
                        hint="when the customer gave an address"
                    />
                    <Tile
                        label="Needs review"
                        value={summary.counts.needs_review.toLocaleString()}
                        hint={`${summary.counts.no_address} no address · ${summary.counts.failed} failed`}
                    />
                    <Tile
                        label="AI cost"
                        value={usd(summary.cost_usd, 3)}
                        hint={`${summary.ai_checks} AI checks`}
                    />
                    <Tile
                        label="Cost per fill"
                        value={usd(summary.cost_per_fill_usd)}
                        hint={`avg ${usd(summary.avg_cost_usd, 5)} per check`}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card title="Daily results" className="lg:col-span-2">
                        <DailyOutcomeChart daily={daily} />
                    </Card>
                    <Card title="Daily AI cost (USD)">
                        <DailyCostChart daily={daily} />
                    </Card>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <Card title="Why orders need review">
                        {review.reasons.length === 0 ? (
                            <Empty />
                        ) : (
                            <ul className="space-y-2">
                                {review.reasons.map((r) => (
                                    <BarRow
                                        key={r.reason}
                                        label={r.reason}
                                        value={r.total}
                                        max={review.reasons[0].total}
                                    />
                                ))}
                            </ul>
                        )}
                    </Card>
                    <Card title="Level that did not match">
                        <ul className="space-y-2">
                            {(Object.keys(LEVEL_LABELS) as Level[]).map((l) => (
                                <BarRow
                                    key={l}
                                    label={LEVEL_LABELS[l]}
                                    value={review.unmatched[l]}
                                    max={Math.max(
                                        1,
                                        ...Object.values(review.unmatched),
                                    )}
                                />
                            ))}
                        </ul>
                        <p className="mt-3 text-[11px] text-gray-400">
                            Among needs-review orders. Commune is usually the
                            hardest: nicknames and numbered barangays.
                        </p>
                    </Card>
                    <Card title="Shortlist check">
                        <div className="text-2xl font-semibold text-gray-900 tabular-nums dark:text-gray-100">
                            {review.rescued_by_shortlist}
                        </div>
                        <p className="text-[12px] text-gray-500">
                            filled orders the AI completed by picking from the
                            real list
                        </p>
                        <ul className="mt-3 space-y-1 text-[12px] text-gray-600 dark:text-gray-400">
                            {(Object.keys(LEVEL_LABELS) as Level[]).map((l) => (
                                <li key={l} className="flex justify-between">
                                    <span>{LEVEL_LABELS[l]} picked</span>
                                    <span className="font-mono tabular-nums">
                                        {review.picked[l]}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </Card>
                </div>

                <div className="space-y-3">
                    <div className="flex flex-wrap gap-1.5">
                        <Chip
                            active={!filters.status}
                            onClick={() => go({ status: null })}
                        >
                            All · {summary.total}
                        </Chip>
                        {STATUS_FILTERS.filter(
                            (s) => summary.counts[s] > 0,
                        ).map((s) => (
                            <Chip
                                key={s}
                                active={filters.status === s}
                                onClick={() => go({ status: s })}
                            >
                                {STATUS_META[s].label} · {summary.counts[s]}
                            </Chip>
                        ))}
                    </div>
                    <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                        <DataTable
                            columns={columns}
                            data={rows.data}
                            enableInternalPagination={false}
                            meta={{ ...omit(rows, ['data']) }}
                            onFetch={(params) =>
                                go({ page: Number(params?.page ?? 1) })
                            }
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function Th({ children }: { children: React.ReactNode }) {
    return (
        <span className="font-mono text-[10px] tracking-wider text-gray-400 uppercase">
            {children}
        </span>
    );
}

function Tile({
    label,
    value,
    hint,
}: {
    label: string;
    value: string;
    hint?: string;
}) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900">
            <div className="font-mono text-[10px] tracking-wider text-gray-400 uppercase">
                {label}
            </div>
            <div className="mt-1 text-2xl font-semibold text-gray-900 tabular-nums dark:text-gray-100">
                {value}
            </div>
            {hint && (
                <div className="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                    {hint}
                </div>
            )}
        </div>
    );
}

function Card({
    title,
    className = '',
    children,
}: {
    title: string;
    className?: string;
    children: React.ReactNode;
}) {
    return (
        <div
            className={`rounded-[14px] border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900 ${className}`}
        >
            <div className="mb-3 text-[13px] font-medium text-gray-800 dark:text-gray-200">
                {title}
            </div>
            {children}
        </div>
    );
}

function BarRow({
    label,
    value,
    max,
}: {
    label: string;
    value: number;
    max: number;
}) {
    return (
        <li>
            <div className="flex justify-between gap-2 text-[12px]">
                <span className="truncate text-gray-600 dark:text-gray-400">
                    {label}
                </span>
                <span className="font-mono text-gray-800 tabular-nums dark:text-gray-200">
                    {value}
                </span>
            </div>
            <div className="mt-1 h-1.5 rounded-full bg-gray-100 dark:bg-white/5">
                <div
                    className="h-1.5 rounded-full bg-amber-500 dark:bg-amber-600"
                    style={{ width: `${max ? (value / max) * 100 : 0}%` }}
                />
            </div>
        </li>
    );
}

function Chip({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`rounded-full border px-3 py-1 text-[12px] transition-colors ${
                active
                    ? 'border-emerald-600 bg-emerald-50 text-emerald-800 dark:border-emerald-500 dark:bg-emerald-500/10 dark:text-emerald-300'
                    : 'border-black/8 text-gray-600 hover:bg-gray-50 dark:border-white/10 dark:text-gray-400 dark:hover:bg-white/5'
            }`}
        >
            {children}
        </button>
    );
}

function Empty() {
    return <p className="text-[12px] text-gray-400">Nothing in this range.</p>;
}
