import PageHeader from '@/components/common/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import {
    Check,
    ChevronDown,
    ChevronRight,
    Loader2,
    Plus,
    SlidersHorizontal,
    Trash2,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';

// ── Types ───────────────────────────────────────────────────────────────────

type Level = 'campaign' | 'ad_set' | 'ad';
type Status =
    | 'scaling'
    | 'maintain'
    | 'killed'
    | 'too_early'
    | 'unmatched';
type RuleStatus = 'scaling' | 'maintain' | 'killed';

interface DayCell {
    day: number;
    date: string;
    spend: number;
    roas: number | null;
}

interface ReasonItem {
    metric: string;
    window: string;
    operator: string;
    threshold: number;
    actual_value: number | null;
    passed: boolean;
}

interface TrailItem {
    date: string;
    status: Status;
}

interface Row {
    level: Level;
    entity_id: string;
    name: string;
    account_name: string | null;
    status: string | null;
    effective_status: string | null;
    start_date: string;
    day_number: number;
    series: DayCell[];
    total_spend: number;
    overall_roas: number | null;
    days_with_spend: number;
    suggested_status: Status;
    final_status: Status;
    reason: ReasonItem[];
    trail: TrailItem[];
    override: { final_status: RuleStatus | null; notes: string | null; decided_at: string | null } | null;
}

interface Condition {
    metric: string;
    operator: string;
    value: number;
    window: string;
}

interface Rule {
    status: RuleStatus;
    condition_operator: 'and' | 'or';
    is_active: boolean;
    conditions: Condition[];
}

interface Options {
    levels: Level[];
    metrics: string[];
    operators: string[];
    windows: string[];
    conditionOperators: ('and' | 'or')[];
    statuses: RuleStatus[];
}

interface Props {
    workspace: { id: number; name: string; slug: string };
    accounts: { id: string; name: string }[];
    rules: Rule[];
    options: Options;
    query: { level: Level; startedFrom: string; startedTo: string };
}

// ── Helpers ─────────────────────────────────────────────────────────────────

const LEVEL_LABEL: Record<Level, string> = {
    campaign: 'Campaign',
    ad_set: 'Ad set',
    ad: 'Ad',
};

const STATUS_META: Record<Status, { label: string; badge: string; dot: string }> = {
    scaling: {
        label: 'Scaling',
        badge: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-400',
        dot: 'bg-emerald-500',
    },
    maintain: {
        label: 'Maintain',
        badge: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-400',
        dot: 'bg-amber-500',
    },
    killed: {
        label: 'Killed',
        badge: 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-400',
        dot: 'bg-red-500',
    },
    too_early: {
        label: 'Too early',
        badge: 'border-gray-200 bg-gray-50 text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400',
        dot: 'bg-gray-400',
    },
    unmatched: {
        label: 'Unmatched',
        badge: 'border-gray-200 bg-gray-50 text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400',
        dot: 'bg-gray-400',
    },
};

const WINDOW_LABEL: Record<string, string> = {
    first_3_days: 'first 3 days',
    first_7_days: 'first 7 days',
    lifetime: 'lifetime',
};

const PRESETS = [
    { value: '7', label: 'Started last 7 days' },
    { value: '30', label: 'Started last 30 days' },
    { value: '90', label: 'Started last 90 days' },
];

function metricLabel(metric: string): string {
    return metric.replace(/_/g, ' ').toUpperCase();
}

function peso(n: number | null | undefined): string {
    return `₱${(n ?? 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function roasFmt(n: number | null | undefined): string {
    return n == null ? '—' : `${n.toFixed(2)}x`;
}

function rangeFromPreset(preset: string): { startedFrom: string; startedTo: string } {
    const days = preset === '7' ? 7 : preset === '90' ? 90 : 30;
    const to = new Date();
    const from = new Date();
    from.setDate(from.getDate() - (days - 1));
    const fmt = (d: Date) => d.toISOString().slice(0, 10);
    return { startedFrom: fmt(from), startedTo: fmt(to) };
}

function StatusBadge({ status }: { status: Status }) {
    const meta = STATUS_META[status];
    return (
        <Badge variant="outline" className={cn('gap-1', meta.badge)}>
            <span className={cn('h-1.5 w-1.5 rounded-full', meta.dot)} />
            {meta.label}
        </Badge>
    );
}

// ── Page ────────────────────────────────────────────────────────────────────

export default function EntityMonitor({
    workspace,
    rules: initialRules,
    options,
    query,
}: Props) {
    const indexUrl = `/workspaces/${workspace.slug}/integrations/meta/entity-monitor`;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Entity Monitor', href: indexUrl },
    ];

    const [level, setLevel] = useState<Level>(query.level);
    const [preset, setPreset] = useState('30');
    const [statusFilter, setStatusFilter] = useState<'all' | Status>('all');
    const [search, setSearch] = useState('');
    const [rows, setRows] = useState<Row[]>([]);
    const [loading, setLoading] = useState(true);
    const [expanded, setExpanded] = useState<string | null>(null);
    const [rulesOpen, setRulesOpen] = useState(false);

    const fetchRows = useCallback(() => {
        setLoading(true);
        const { startedFrom, startedTo } = rangeFromPreset(preset);
        const qs = new URLSearchParams({ level, startedFrom, startedTo });
        let active = true;
        fetch(`${indexUrl}/data?${qs.toString()}`, {
            headers: { Accept: 'application/json' },
        })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then((d: { rows: Row[] }) => {
                if (active) {
                    setRows(d.rows);
                    setLoading(false);
                }
            })
            .catch(() => active && setLoading(false));
        return () => {
            active = false;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [level, preset]);

    useEffect(() => fetchRows(), [fetchRows]);

    const filtered = useMemo(
        () =>
            rows.filter((r) => {
                if (statusFilter !== 'all' && r.final_status !== statusFilter)
                    return false;
                if (search && !r.name.toLowerCase().includes(search.toLowerCase()))
                    return false;
                return true;
            }),
        [rows, statusFilter, search],
    );

    const counts = useMemo(() => {
        const c: Record<string, number> = {};
        rows.forEach((r) => (c[r.final_status] = (c[r.final_status] ?? 0) + 1));
        return c;
    }, [rows]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Entity Monitor" />

            <div className="p-4 sm:p-6">
                <PageHeader
                    title="Entity Monitor"
                    description="Is this campaign / ad set / ad winning? Auto-filled Day 1–7 spend & ROAS with a suggested status."
                    stackActionsOnMobile
                >
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setRulesOpen(true)}
                    >
                        <SlidersHorizontal className="mr-1 h-4 w-4" />
                        Status rules
                    </Button>
                </PageHeader>

                {/* Controls */}
                <div className="mb-4 flex flex-wrap items-center gap-2">
                    <ToggleGroup
                        type="single"
                        value={level}
                        onValueChange={(v) => v && setLevel(v as Level)}
                        variant="outline"
                        size="sm"
                    >
                        {options.levels.map((l) => (
                            <ToggleGroupItem key={l} value={l} className="px-3">
                                {LEVEL_LABEL[l]}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>

                    <Select value={preset} onValueChange={setPreset}>
                        <SelectTrigger className="h-8 w-[180px] text-xs">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {PRESETS.map((p) => (
                                <SelectItem key={p.value} value={p.value}>
                                    {p.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={statusFilter}
                        onValueChange={(v) => setStatusFilter(v as 'all' | Status)}
                    >
                        <SelectTrigger className="h-8 w-[150px] text-xs">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            {(['scaling', 'maintain', 'killed', 'too_early'] as Status[]).map(
                                (s) => (
                                    <SelectItem key={s} value={s}>
                                        {STATUS_META[s].label}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>

                    <Input
                        placeholder="Search name…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="h-8 w-[200px] text-xs"
                    />

                    <div className="ml-auto flex items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400">
                        {(['scaling', 'maintain', 'killed'] as Status[]).map((s) => (
                            <span key={s} className="flex items-center gap-1">
                                <span
                                    className={cn(
                                        'h-2 w-2 rounded-full',
                                        STATUS_META[s].dot,
                                    )}
                                />
                                {counts[s] ?? 0} {STATUS_META[s].label}
                            </span>
                        ))}
                    </div>
                </div>

                {/* Table */}
                <div className="overflow-x-auto rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <table className="w-full min-w-[1100px] text-sm">
                        <thead>
                            <tr className="border-b border-black/6 text-left text-[11px] tracking-wide text-gray-400 uppercase dark:border-white/6">
                                <th className="w-8 py-2 pl-3" />
                                <th className="py-2 pr-3">Name</th>
                                <th className="px-2">Day</th>
                                {Array.from({ length: 7 }, (_, i) => (
                                    <th key={i} className="px-2 text-center">
                                        D{i + 1}
                                    </th>
                                ))}
                                <th className="px-2 text-right">Spend</th>
                                <th className="px-2 text-right">ROAS</th>
                                <th className="px-2">Status</th>
                                <th className="w-10" />
                            </tr>
                        </thead>
                        <tbody>
                            {loading ? (
                                <tr>
                                    <td colSpan={13} className="py-16 text-center">
                                        <Loader2 className="mx-auto h-5 w-5 animate-spin text-gray-400" />
                                    </td>
                                </tr>
                            ) : filtered.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={13}
                                        className="py-16 text-center text-xs text-gray-400"
                                    >
                                        No {LEVEL_LABEL[level].toLowerCase()}s started in this
                                        window.
                                    </td>
                                </tr>
                            ) : (
                                filtered.map((row) => (
                                    <MonitorRow
                                        key={row.entity_id}
                                        row={row}
                                        expanded={expanded === row.entity_id}
                                        onToggle={() =>
                                            setExpanded(
                                                expanded === row.entity_id
                                                    ? null
                                                    : row.entity_id,
                                            )
                                        }
                                        indexUrl={indexUrl}
                                        statuses={options.statuses}
                                        onChanged={fetchRows}
                                    />
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <RulesDialog
                open={rulesOpen}
                onOpenChange={setRulesOpen}
                indexUrl={indexUrl}
                initialRules={initialRules}
                options={options}
                onSaved={fetchRows}
            />
        </AppLayout>
    );
}

// ── Row ─────────────────────────────────────────────────────────────────────

function MonitorRow({
    row,
    expanded,
    onToggle,
    indexUrl,
    statuses,
    onChanged,
}: {
    row: Row;
    expanded: boolean;
    onToggle: () => void;
    indexUrl: string;
    statuses: RuleStatus[];
    onChanged: () => void;
}) {
    return (
        <>
            <tr className="border-b border-black/4 hover:bg-gray-50/60 dark:border-white/4 dark:hover:bg-white/3">
                <td className="py-2 pl-3 align-top">
                    <button
                        onClick={onToggle}
                        className="text-gray-400 hover:text-gray-600"
                    >
                        {expanded ? (
                            <ChevronDown className="h-4 w-4" />
                        ) : (
                            <ChevronRight className="h-4 w-4" />
                        )}
                    </button>
                </td>
                <td className="py-2 pr-3 align-top">
                    <div className="max-w-[260px] truncate font-medium text-gray-800 dark:text-gray-100">
                        {row.name}
                    </div>
                    <div className="truncate text-[11px] text-gray-400">
                        {row.account_name ?? '—'}
                    </div>
                </td>
                <td className="px-2 align-top text-[11px] whitespace-nowrap text-gray-500">
                    Day {Math.min(row.day_number, 7)}/7
                </td>
                {row.series.map((d) => (
                    <td key={d.day} className="px-2 text-center align-top">
                        {d.spend > 0 ? (
                            <div className="leading-tight">
                                <div className="text-[11px] text-gray-600 tabular-nums dark:text-gray-300">
                                    {peso(d.spend)}
                                </div>
                                <div className="text-[11px] font-medium tabular-nums">
                                    {roasFmt(d.roas)}
                                </div>
                            </div>
                        ) : (
                            <span className="text-gray-300 dark:text-gray-600">–</span>
                        )}
                    </td>
                ))}
                <td className="px-2 text-right align-top text-[12px] font-medium tabular-nums whitespace-nowrap">
                    {peso(row.total_spend)}
                </td>
                <td className="px-2 text-right align-top text-[12px] font-semibold tabular-nums">
                    {roasFmt(row.overall_roas)}
                </td>
                <td className="px-2 align-top">
                    <div className="flex items-center gap-1">
                        <StatusBadge status={row.final_status} />
                        {row.override?.final_status && (
                            <span
                                title="Manually overridden"
                                className="text-[10px] text-gray-400"
                            >
                                ✎
                            </span>
                        )}
                    </div>
                </td>
                <td className="px-2 align-top">
                    <OverridePopover
                        row={row}
                        indexUrl={indexUrl}
                        statuses={statuses}
                        onChanged={onChanged}
                    />
                </td>
            </tr>

            {expanded && (
                <tr className="border-b border-black/6 bg-gray-50/40 dark:border-white/6 dark:bg-white/2">
                    <td colSpan={13} className="px-6 py-4">
                        <div className="grid gap-6 md:grid-cols-2">
                            {/* Why */}
                            <div>
                                <div className="mb-2 text-[11px] font-semibold tracking-wide text-gray-500 uppercase">
                                    Why “{STATUS_META[row.suggested_status].label}”
                                </div>
                                {row.reason.length === 0 ? (
                                    <p className="text-xs text-gray-400">
                                        {row.suggested_status === 'too_early'
                                            ? 'Not enough days of spend yet to judge (needs 3).'
                                            : 'No status rule matched.'}
                                    </p>
                                ) : (
                                    <ul className="space-y-1">
                                        {row.reason.map((c, i) => (
                                            <li
                                                key={i}
                                                className="flex items-center gap-2 text-xs"
                                            >
                                                {c.passed ? (
                                                    <Check className="h-3.5 w-3.5 text-emerald-500" />
                                                ) : (
                                                    <X className="h-3.5 w-3.5 text-red-400" />
                                                )}
                                                <span className="text-gray-600 dark:text-gray-300">
                                                    {metricLabel(c.metric)} {c.operator}{' '}
                                                    {c.threshold} over {WINDOW_LABEL[c.window]}
                                                </span>
                                                <span className="text-gray-400">
                                                    (actual{' '}
                                                    {c.actual_value == null
                                                        ? '—'
                                                        : c.actual_value.toFixed(2)}
                                                    )
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                {row.override?.notes && (
                                    <p className="mt-3 text-xs text-gray-500">
                                        <span className="font-medium">Note:</span>{' '}
                                        {row.override.notes}
                                    </p>
                                )}
                            </div>

                            {/* Daily trail */}
                            <div>
                                <div className="mb-2 text-[11px] font-semibold tracking-wide text-gray-500 uppercase">
                                    Daily status trail
                                </div>
                                {row.trail.length === 0 ? (
                                    <p className="text-xs text-gray-400">
                                        No history recorded yet (the daily job runs
                                        overnight).
                                    </p>
                                ) : (
                                    <div className="flex flex-wrap gap-1.5">
                                        {row.trail.map((t) => (
                                            <div
                                                key={t.date}
                                                className="flex items-center gap-1 rounded-md border border-black/6 px-1.5 py-0.5 text-[10px] dark:border-white/6"
                                                title={t.date}
                                            >
                                                <span
                                                    className={cn(
                                                        'h-1.5 w-1.5 rounded-full',
                                                        STATUS_META[t.status].dot,
                                                    )}
                                                />
                                                {t.date.slice(5)}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                    </td>
                </tr>
            )}
        </>
    );
}

// ── Override popover ────────────────────────────────────────────────────────

function OverridePopover({
    row,
    indexUrl,
    statuses,
    onChanged,
}: {
    row: Row;
    indexUrl: string;
    statuses: RuleStatus[];
    onChanged: () => void;
}) {
    const [open, setOpen] = useState(false);
    const [status, setStatus] = useState<RuleStatus | 'auto'>(
        row.override?.final_status ?? 'auto',
    );
    const [notes, setNotes] = useState(row.override?.notes ?? '');
    const [saving, setSaving] = useState(false);

    const save = () => {
        setSaving(true);
        router.post(
            `${indexUrl}/status`,
            {
                level: row.level,
                entity_id: row.entity_id,
                final_status: status === 'auto' ? null : status,
                notes: notes || null,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setOpen(false);
                    onChanged();
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button variant="ghost" size="sm" className="h-7 px-2 text-[11px]">
                    Set
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-64" align="end">
                <div className="space-y-3">
                    <div>
                        <label className="mb-1 block text-[11px] font-medium text-gray-500">
                            Status
                        </label>
                        <Select
                            value={status}
                            onValueChange={(v) => setStatus(v as RuleStatus | 'auto')}
                        >
                            <SelectTrigger className="h-8 text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="auto">
                                    Use suggestion ({STATUS_META[row.suggested_status].label})
                                </SelectItem>
                                {statuses.map((s) => (
                                    <SelectItem key={s} value={s}>
                                        {STATUS_META[s].label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <label className="mb-1 block text-[11px] font-medium text-gray-500">
                            Note
                        </label>
                        <textarea
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            rows={2}
                            className="w-full rounded-md border border-black/10 bg-transparent px-2 py-1 text-xs dark:border-white/10"
                            placeholder="Optional reason…"
                        />
                    </div>
                    <Button
                        size="sm"
                        className="w-full"
                        disabled={saving}
                        onClick={save}
                    >
                        {saving && <Loader2 className="mr-1 h-3 w-3 animate-spin" />}
                        Save
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    );
}

// ── Rules config dialog ─────────────────────────────────────────────────────

function RulesDialog({
    open,
    onOpenChange,
    indexUrl,
    initialRules,
    options,
    onSaved,
}: {
    open: boolean;
    onOpenChange: (v: boolean) => void;
    indexUrl: string;
    initialRules: Rule[];
    options: Options;
    onSaved: () => void;
}) {
    const { data, setData, post, processing } = useForm<{ rules: Rule[] }>({
        rules: initialRules,
    });
    const rules = data.rules;

    useEffect(() => {
        if (open) setData('rules', initialRules);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, initialRules]);

    const update = (status: RuleStatus, fn: (r: Rule) => Rule) =>
        setData(
            'rules',
            rules.map((r) => (r.status === status ? fn(r) : r)),
        );

    const save = () => {
        post(`${indexUrl}/rules`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                onOpenChange(false);
                onSaved();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Status rules</DialogTitle>
                    <DialogDescription>
                        Evaluated top to bottom — the first status whose conditions match
                        wins. Windows are relative to the entity’s first spend day.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-5">
                    {rules.map((rule) => (
                        <div
                            key={rule.status}
                            className="rounded-lg border border-black/8 p-3 dark:border-white/8"
                        >
                            <div className="mb-3 flex items-center gap-2">
                                <StatusBadge status={rule.status} />
                                <span className="text-[11px] text-gray-400">match</span>
                                <Select
                                    value={rule.condition_operator}
                                    onValueChange={(v) =>
                                        update(rule.status, (r) => ({
                                            ...r,
                                            condition_operator: v as 'and' | 'or',
                                        }))
                                    }
                                >
                                    <SelectTrigger className="h-7 w-20 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="and">all</SelectItem>
                                        <SelectItem value="or">any</SelectItem>
                                    </SelectContent>
                                </Select>
                                <span className="text-[11px] text-gray-400">
                                    of these conditions
                                </span>
                            </div>

                            <div className="space-y-2">
                                {rule.conditions.map((cond, i) => (
                                    <div key={i} className="flex items-center gap-2">
                                        <ConditionSelect
                                            value={cond.metric}
                                            options={options.metrics}
                                            label={metricLabel}
                                            width="w-44"
                                            onChange={(v) =>
                                                update(rule.status, (r) => ({
                                                    ...r,
                                                    conditions: r.conditions.map((c, j) =>
                                                        j === i ? { ...c, metric: v } : c,
                                                    ),
                                                }))
                                            }
                                        />
                                        <ConditionSelect
                                            value={cond.operator}
                                            options={options.operators}
                                            width="w-16"
                                            onChange={(v) =>
                                                update(rule.status, (r) => ({
                                                    ...r,
                                                    conditions: r.conditions.map((c, j) =>
                                                        j === i ? { ...c, operator: v } : c,
                                                    ),
                                                }))
                                            }
                                        />
                                        <Input
                                            type="number"
                                            step="any"
                                            value={cond.value}
                                            onChange={(e) =>
                                                update(rule.status, (r) => ({
                                                    ...r,
                                                    conditions: r.conditions.map((c, j) =>
                                                        j === i
                                                            ? {
                                                                  ...c,
                                                                  value: Number(
                                                                      e.target.value,
                                                                  ),
                                                              }
                                                            : c,
                                                    ),
                                                }))
                                            }
                                            className="h-8 w-20 text-xs"
                                        />
                                        <ConditionSelect
                                            value={cond.window}
                                            options={options.windows}
                                            label={(w) => WINDOW_LABEL[w] ?? w}
                                            width="w-32"
                                            onChange={(v) =>
                                                update(rule.status, (r) => ({
                                                    ...r,
                                                    conditions: r.conditions.map((c, j) =>
                                                        j === i ? { ...c, window: v } : c,
                                                    ),
                                                }))
                                            }
                                        />
                                        <button
                                            onClick={() =>
                                                update(rule.status, (r) => ({
                                                    ...r,
                                                    conditions: r.conditions.filter(
                                                        (_, j) => j !== i,
                                                    ),
                                                }))
                                            }
                                            className="text-gray-300 hover:text-red-500"
                                            title="Remove condition"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                ))}
                            </div>

                            <Button
                                variant="ghost"
                                size="sm"
                                className="mt-2 h-7 text-[11px]"
                                onClick={() =>
                                    update(rule.status, (r) => ({
                                        ...r,
                                        conditions: [
                                            ...r.conditions,
                                            {
                                                metric: 'roas',
                                                operator: '>=',
                                                value: 0,
                                                window: 'first_3_days',
                                            },
                                        ],
                                    }))
                                }
                            >
                                <Plus className="mr-1 h-3 w-3" />
                                Add condition
                            </Button>
                        </div>
                    ))}
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button onClick={save} disabled={processing}>
                        {processing && (
                            <Loader2 className="mr-1 h-4 w-4 animate-spin" />
                        )}
                        Save rules
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function ConditionSelect({
    value,
    options,
    onChange,
    label,
    width,
}: {
    value: string;
    options: string[];
    onChange: (v: string) => void;
    label?: (v: string) => string;
    width: string;
}) {
    return (
        <Select value={value} onValueChange={onChange}>
            <SelectTrigger className={cn('h-8 text-xs', width)}>
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {options.map((o) => (
                    <SelectItem key={o} value={o}>
                        {label ? label(o) : o}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
