import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import clsx from 'clsx';
import {
    Check,
    ChevronDown,
    Filter,
    Plus,
    Search,
    TriangleAlert,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

/**
 * Every way the order list can be narrowed, asked the same way: pick a field,
 * pick how to compare it, type what to compare it against. One dropdown holds
 * the lot, so a filter is added and removed in the same place rather than being
 * a control that is either on the bar or not.
 *
 * The server did not change for this. Each row is translated back into the
 * `filter[…]` parameters the page has always sent — see buildOrderFilter, and
 * deserializeOrderFilters for the way back in off a shared link.
 */

/* ───────────────────────── Fields ───────────────────────── */

/**
 * The lifecycle stamps a range can be applied to. Keys match
 * OrderController::DATE_FIELDS; which of them are offered comes from the server,
 * so the dropdown cannot name a column the server would ignore.
 */
const DATE_FIELD_LABELS: Record<string, string> = {
    inserted_at: 'Created (Pancake)',
    confirmed_at: 'Confirmed',
    shipped_at: 'Shipped',
    delivered_at: 'Delivered',
    returning_at: 'Returning',
    returned_at: 'Returned',
};

export const DEFAULT_DATE_FIELD = 'inserted_at';

/** Where the order is in its life. One status or several — the server's own
 *  filter is a whereIn, so the row names as many as the reader wants at once. */
const STATUS_FIELD = 'status';
/** The customer's return history, and — for those who have one — its rate. */
const RTS_FIELD = 'customer_rts';
/** How many orders that customer has placed in total. */
const ORDERS_FIELD = 'customer_orders';
/** Whether anyone here has called the number on the order. */
const CALL_LOGS_FIELD = 'call_logs';
/** One exact order, set by a link that already knew which one it meant. */
const ORDER_ID_FIELD = 'order_id';
const RIDER_FIELD = 'rider';

const FIELD_LABELS: Record<string, string> = {
    ...DATE_FIELD_LABELS,
    [STATUS_FIELD]: 'Status',
    [RTS_FIELD]: 'Customer RTS',
    [ORDERS_FIELD]: 'Customer orders',
    [CALL_LOGS_FIELD]: 'Call logs',
    [ORDER_ID_FIELD]: 'Order ID',
    [RIDER_FIELD]: 'Rider',
};

const isDateField = (field: string) => field in DATE_FIELD_LABELS;

/* ──────────────────────── Operators ─────────────────────── */

const DATE_OPS: Record<string, string> = {
    on: '= On',
    before: '≤ On or before',
    after: '≥ On or after',
    between: '↔ Between',
};

/**
 * The numeric comparisons, keyed as OrderController::RTS_COMPARISONS keys them.
 * Both number fields compare the same way, so both read from this.
 */
const NUMERIC_OPS: Record<string, string> = {
    gt: '> Greater than',
    gte: '≥ Greater than or equal',
    lt: '< Less than',
    lte: '≤ Less than or equal',
    eq: '= Equal to',
    between: '↔ Between',
};

/**
 * Customer RTS asks two questions through one field: whether the customer has a
 * return history at all, and — only where they do — how its rate compares. Both
 * are operators here, because "has history" and "rate > 20" are alternatives
 * rather than two filters that could stand side by side.
 *
 * The comparisons are written out rather than read off NUMERIC_OPS: "rate
 * greater than or equal" does not fit the row, and the symbol says the same
 * thing in the width there is. The keys still have to match.
 */
const RTS_OPS: Record<string, string> = {
    has_report: 'has history',
    no_report: 'no history',
    gt: 'rate >',
    gte: 'rate ≥',
    lt: 'rate <',
    lte: 'rate ≤',
    eq: 'rate =',
    between: 'rate between',
};

/** Keys match OrderCallLogsFilter::ANSWERS. */
const CALL_LOGS_OPS: Record<string, string> = {
    has: 'has calls',
    none: 'no calls',
};

/**
 * Which calls count, keyed as OrderCallLogsFilter::KINDS keys them.
 *
 * A call the sync could place as neither — it reached the number on no
 * delivery, and no order had just come in — carries no persona and so answers
 * only "any kind". That is the honest reading: someone rang, and nothing is
 * known about what it was for.
 *
 * `any` rather than '' because Radix rejects an empty SelectItem value; it is
 * the resting state, so the row asks about calls of every kind until it is
 * moved off.
 */
const CALL_KIND_LABELS: Record<string, string> = {
    any: 'of any kind',
    rmo: 'for a delivery',
    verification: 'for verification',
};

const DEFAULT_CALL_KIND = 'any';

const TEXT_OPS: Record<string, string> = { is: 'is' };

/** Its own map, so switching between status and a text field starts over. */
const STATUS_OPS: Record<string, string> = { is: 'is' };

const opsFor = (field: string): Record<string, string> =>
    isDateField(field)
        ? DATE_OPS
        : field === RTS_FIELD
          ? RTS_OPS
          : field === ORDERS_FIELD
            ? NUMERIC_OPS
            : field === CALL_LOGS_FIELD
              ? CALL_LOGS_OPS
              : field === STATUS_FIELD
                ? STATUS_OPS
                : TEXT_OPS;

const defaultOpFor = (field: string): string =>
    Object.keys(opsFor(field))[0] ?? 'is';

/** Both operators that take a second value, in one place. */
const isRangeOp = (op: string) => op === 'between';

/**
 * The operators that are whole questions in themselves and so have no value box
 * beside them. The Call logs operators are not among them: "has calls" is
 * finished by the kind of call it means.
 */
const VALUELESS_OPS = ['has_report', 'no_report'];

const opTakesValue = (op: string) => !VALUELESS_OPS.includes(op);

/**
 * What a field's value starts at. Only the kind of call has a resting value —
 * every other field starts empty, and stays out of the query until it is
 * filled in.
 */
const defaultValueFor = (field: string): string =>
    field === CALL_LOGS_FIELD ? DEFAULT_CALL_KIND : '';

/* ────────────────────────── Rows ────────────────────────── */

export interface OrderFilter {
    id: string; // client-only key for React
    field: string;
    op: string;
    value: string;
    /** Upper bound, only used when op is 'between'. */
    value2: string;
}

/** A row the server can act on — an unfinished one is simply not sent. */
const isComplete = (f: OrderFilter): boolean =>
    !!f.field &&
    !!f.op &&
    (!opTakesValue(f.op) || f.value !== '') &&
    (!isRangeOp(f.op) || f.value2 !== '');

/** How many filters are actually in force, for the trigger's badge. */
export const activeOrderFilterCount = (filters: OrderFilter[]): number =>
    filters.filter(isComplete).length;

const row = (
    field: string,
    op: string,
    value = '',
    value2 = '',
): OrderFilter => ({
    id: crypto.randomUUID(),
    field,
    op,
    value,
    value2,
});

/* ───────────────── To and from the query string ──────────── */

/** The `filter[…]` parameters the page sends, all of them optional. */
export type OrderFilterParams = Record<string, string | undefined>;

/**
 * The date range is one question on the server — a from, a to, and the single
 * column both apply to — so only the first complete date row can be honoured.
 * A second one is flagged in the dropdown rather than silently dropped.
 */
const firstDateRow = (filters: OrderFilter[]) =>
    filters.find((f) => isDateField(f.field) && isComplete(f));

/** Whether a row is one the server will act on, given the rows around it. */
const isOrderFilterIgnored = (
    filters: OrderFilter[],
    f: OrderFilter,
): boolean =>
    isDateField(f.field) && isComplete(f) && firstDateRow(filters)?.id !== f.id;

const dateBounds = (f: OrderFilter): [string?, string?] => {
    switch (f.op) {
        case 'on':
            return [f.value, f.value];
        case 'before':
            return [undefined, f.value];
        case 'after':
            return [f.value, undefined];
        default:
            return [f.value, f.value2];
    }
};

/**
 * Turn the rows back into the parameters the list has always been filtered by.
 * Search stays separate because it is its own box above the table rather than a
 * row here — it is typed into constantly, so it narrows as you type.
 */
export function buildOrderFilter(
    filters: OrderFilter[],
    search: string,
): OrderFilterParams {
    const params: OrderFilterParams = { search: search || undefined };

    const date = firstDateRow(filters);

    if (date) {
        const [from, to] = dateBounds(date);
        params.date_from = from;
        params.date_to = to;
        // Only worth sending alongside a range; on its own it narrows nothing.
        params.date_type =
            date.field === DEFAULT_DATE_FIELD ? undefined : date.field;
    }

    for (const f of filters) {
        if (!isComplete(f) || isDateField(f.field)) continue;

        if (f.field === RTS_FIELD) {
            // A rate comparison is a question about customers who have a
            // history, so it carries `has_report` along with it.
            params.report = f.op === 'no_report' ? 'no_report' : 'has_report';

            if (opTakesValue(f.op)) {
                params.rts_op = f.op;
                params.rts_value = f.value;
                params.rts_value2 = isRangeOp(f.op) ? f.value2 : undefined;
            }
        } else if (f.field === CALL_LOGS_FIELD) {
            // The answer is the operator; the value beside it says which calls
            // were being asked about. "Any" is the server's own default, so it
            // travels as nothing at all.
            params.call_logs = f.op;
            params.call_logs_kind =
                f.value === DEFAULT_CALL_KIND ? undefined : f.value;
        } else if (f.field === ORDERS_FIELD) {
            params.customer_orders = f.value;
            params.customer_orders_op = f.op;
            params.customer_orders_to = isRangeOp(f.op) ? f.value2 : undefined;
        } else {
            params[f.field] = f.value;
        }
    }

    return params;
}

/**
 * The way back in: a shared link or a jump from elsewhere in the app arrives as
 * those same parameters, and each becomes the row that would have produced it.
 */
/** One value, however the link spelled it — `a,b` and `[a, b]` mean the same. */
const whole = (value: unknown): string =>
    Array.isArray(value) ? value.join(',') : ((value as string) ?? '');

export function deserializeOrderFilters(
    filter: Record<string, string | undefined> | undefined,
    dateFields: string[],
): OrderFilter[] {
    const f = filter ?? {};
    const out: OrderFilter[] = [];

    const from = f.date_from ?? '';
    const to = f.date_to ?? '';

    if (from || to) {
        // A link can still carry a column the server has since stopped
        // offering, in which case it filtered on the default — so read the row
        // the same way, rather than showing a column that was never applied.
        const field =
            f.date_type && dateFields.includes(f.date_type)
                ? f.date_type
                : DEFAULT_DATE_FIELD;

        out.push(
            from && to
                ? from === to
                    ? row(field, 'on', from)
                    : row(field, 'between', from, to)
                : from
                  ? row(field, 'after', from)
                  : row(field, 'before', to),
        );
    }

    if (f.report === 'no_report') {
        out.push(row(RTS_FIELD, 'no_report'));
    } else if (f.report === 'has_report') {
        out.push(
            f.rts_value
                ? row(
                      RTS_FIELD,
                      f.rts_op || 'gt',
                      f.rts_value,
                      f.rts_value2 ?? '',
                  )
                : row(RTS_FIELD, 'has_report'),
        );
    }

    if (f.call_logs) {
        out.push(
            row(
                CALL_LOGS_FIELD,
                f.call_logs,
                f.call_logs_kind || DEFAULT_CALL_KIND,
            ),
        );
    }

    if (f.customer_orders) {
        out.push(
            row(
                ORDERS_FIELD,
                f.customer_orders_op || 'gt',
                f.customer_orders,
                f.customer_orders_to ?? '',
            ),
        );
    }

    // A list may arrive either whole or already split, depending on the link
    // that carried it.
    const status = whole(f.status);

    if (status) out.push(row(STATUS_FIELD, 'is', status));
    if (f.order_id) out.push(row(ORDER_ID_FIELD, 'is', f.order_id));
    if (f.rider) out.push(row(RIDER_FIELD, 'is', f.rider));

    return out;
}

/* ─────────────────────── Field picker ───────────────────── */

interface FieldOption {
    id: string;
    label: string;
}

interface FieldGroup {
    category: string;
    fields: FieldOption[];
}

function FieldCombobox({
    value,
    onValueChange,
    grouped,
}: {
    value: string;
    onValueChange: (v: string) => void;
    grouped: FieldGroup[];
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    useEffect(() => {
        if (!open) setSearch('');
    }, [open]);

    const q = search.toLowerCase();
    const shown = grouped
        .map((g) => ({
            ...g,
            fields: g.fields.filter(
                (f) => !q || f.label.toLowerCase().includes(q),
            ),
        }))
        .filter((g) => g.fields.length > 0);

    // A row can outlive the field list that offered it, so fall back to the
    // label map rather than showing the reader a bare field key.
    const selectedLabel =
        grouped.flatMap((g) => g.fields).find((f) => f.id === value)?.label ??
        FIELD_LABELS[value] ??
        value;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    className="flex h-8 w-44 items-center justify-between gap-1 rounded-lg border border-black/6 bg-stone-50 px-2 font-mono text-[11px] text-gray-700 transition-colors hover:border-black/10 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-300 dark:hover:border-white/10"
                >
                    <span className="truncate">{selectedLabel}</span>
                    <ChevronDown className="h-3 w-3 shrink-0 text-gray-400" />
                </button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                className="w-64 p-0 font-mono text-[11px]"
            >
                <div className="border-b border-black/6 p-2 dark:border-white/6">
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 h-3 w-3 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            type="text"
                            placeholder="Search field..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            autoFocus
                            className="h-7 w-full rounded-md border border-black/6 bg-stone-50 pr-2 pl-7 font-mono text-[11px] outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-200"
                        />
                    </div>
                </div>

                <div className="max-h-64 overflow-y-auto">
                    {shown.length === 0 && (
                        <p className="py-4 text-center text-[11px] text-gray-400 dark:text-gray-500">
                            No fields match.
                        </p>
                    )}
                    {shown.map((g) => (
                        <div key={g.category}>
                            <div className="px-2 pt-2 pb-1 text-[9px] tracking-wider text-emerald-600 uppercase dark:text-emerald-400">
                                {g.category}
                            </div>
                            {g.fields.map((f) => (
                                <button
                                    key={f.id}
                                    type="button"
                                    onClick={() => {
                                        onValueChange(f.id);
                                        setOpen(false);
                                    }}
                                    className="flex w-full items-center justify-between px-2 py-1.5 text-left text-[11px] text-gray-700 transition-colors hover:bg-stone-100 dark:text-gray-300 dark:hover:bg-zinc-700"
                                >
                                    {f.label}
                                    {f.id === value && (
                                        <Check className="h-3 w-3 text-emerald-500" />
                                    )}
                                </button>
                            ))}
                        </div>
                    ))}
                </div>
            </PopoverContent>
        </Popover>
    );
}

/* ────────────────────────- Builder ──────────────────────── */

const VALUE_INPUT_CLS =
    'h-8 rounded-lg border border-black/6 bg-stone-50 px-2 font-mono text-[11px] outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 dark:border-white/6 dark:bg-zinc-800';

/**
 * The value control for a row that names several things at once — today only
 * the status row. A popover rather than a Select because more than one can be
 * ticked, which a Select has no way to show.
 */
function MultiValuePicker({
    options,
    selected,
    onChange,
    placeholder,
}: {
    options: string[];
    selected: string[];
    onChange: (next: string[]) => void;
    placeholder: string;
}) {
    const [open, setOpen] = useState(false);

    const picked = new Set(selected);
    const allPicked = options.length > 0 && picked.size === options.length;

    const toggle = (value: string) => {
        const next = new Set(picked);
        if (next.has(value)) next.delete(value);
        else next.add(value);
        // Kept in the options' order, so the value reads the same however it
        // was clicked together.
        onChange(options.filter((o) => next.has(o)));
    };

    const label = allPicked
        ? 'Any'
        : picked.size === 0
          ? placeholder
          : picked.size === 1
            ? [...picked][0]
            : `${picked.size} selected`;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    className={clsx(
                        'flex h-8 w-44 items-center justify-between gap-1 rounded-lg border border-black/6 bg-stone-50 px-2 font-mono text-[11px] capitalize transition-colors hover:border-black/10 dark:border-white/6 dark:bg-zinc-800 dark:hover:border-white/10',
                        picked.size === 0
                            ? 'text-gray-400 dark:text-gray-500'
                            : 'text-gray-700 dark:text-gray-300',
                    )}
                >
                    <span className="truncate">{label}</span>
                    <ChevronDown className="h-3 w-3 shrink-0 text-gray-400" />
                </button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                className="w-52 p-0 font-mono text-[11px]"
            >
                <div className="max-h-64 overflow-y-auto py-1">
                    {options.length === 0 && (
                        <p className="py-4 text-center text-gray-400 dark:text-gray-500">
                            Nothing to pick.
                        </p>
                    )}
                    {options.map((o) => (
                        <button
                            key={o}
                            type="button"
                            onClick={() => toggle(o)}
                            className="flex w-full items-center justify-between gap-2 px-3 py-1.5 text-left text-gray-700 capitalize transition-colors hover:bg-stone-100 dark:text-gray-300 dark:hover:bg-zinc-700"
                        >
                            <span className="truncate">{o}</span>
                            {picked.has(o) && (
                                <Check className="h-3 w-3 shrink-0 text-emerald-500" />
                            )}
                        </button>
                    ))}
                </div>
            </PopoverContent>
        </Popover>
    );
}

interface OrderFilterBuilderProps {
    filters: OrderFilter[];
    onChange: (filters: OrderFilter[]) => void;
    /** The date columns the server will filter on, from its own allowlist. */
    dateFields: string[];
    /** The comparisons the server accepts, from the same list it compares on. */
    comparisons: string[];
    /** The statuses the workspace's orders actually carry, in lifecycle order. */
    statuses: string[];
    /** The kinds of call the Call logs row may ask about, from the server. */
    kinds: string[];
}

export function OrderFilterBuilder({
    filters,
    onChange,
    dateFields,
    comparisons,
    statuses,
    kinds,
}: OrderFilterBuilderProps) {
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState<OrderFilter[]>(filters);

    // Sync draft from committed filters whenever the dropdown opens.
    useEffect(() => {
        if (open) setDraft(filters);
    }, [open]); // eslint-disable-line react-hooks/exhaustive-deps

    const update = (id: string, patch: Partial<OrderFilter>) =>
        setDraft((prev) =>
            prev.map((f) => (f.id === id ? { ...f, ...patch } : f)),
        );
    const remove = (id: string) =>
        setDraft((prev) => prev.filter((f) => f.id !== id));
    // A new row starts on a created-date range: it is what the list is cut
    // down by more often than anything else here.
    const add = () =>
        setDraft((prev) => [...prev, row(DEFAULT_DATE_FIELD, 'between')]);

    /**
     * A date, a number and a piece of text share nothing but the field, so
     * crossing between them starts the operator and values over — carrying
     * "> 20" onto a rider name only builds a filter the server drops.
     */
    const changeField = (f: OrderFilter, field: string) =>
        update(
            f.id,
            opsFor(field) === opsFor(f.field)
                ? { field }
                : {
                      field,
                      op: defaultOpFor(field),
                      value: defaultValueFor(field),
                      value2: '',
                  },
        );

    const apply = () => {
        onChange(draft);
        setOpen(false);
    };
    const cancel = () => {
        setDraft(filters);
        setOpen(false);
    };

    // Dates lead the list: they are what the list is most often cut down by,
    // and there is one of them per step of the order's life.
    const grouped = useMemo<FieldGroup[]>(
        () => [
            {
                category: 'Dates',
                fields: dateFields.map((id) => ({
                    id,
                    label: DATE_FIELD_LABELS[id] ?? id,
                })),
            },
            {
                category: 'Customer',
                fields: [RTS_FIELD, ORDERS_FIELD, CALL_LOGS_FIELD].map(
                    (id) => ({
                        id,
                        label: FIELD_LABELS[id],
                    }),
                ),
            },
            {
                category: 'Order',
                fields: [STATUS_FIELD, ORDER_ID_FIELD, RIDER_FIELD].map(
                    (id) => ({ id, label: FIELD_LABELS[id] }),
                ),
            },
        ],
        [dateFields],
    );

    const activeCount = activeOrderFilterCount(filters);

    return (
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <DropdownMenuTrigger asChild>
                <button className="flex h-9 items-center gap-1.5 rounded-[10px] border border-black/10 bg-white px-3 font-mono! text-[12px]! text-gray-700 transition-colors hover:bg-stone-50 data-[state=open]:border-emerald-500 data-[state=open]:ring-2 data-[state=open]:ring-emerald-500/15 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-300 dark:hover:bg-zinc-800">
                    <Filter className="h-3.5 w-3.5 text-gray-400 dark:text-gray-500" />
                    Filters
                    {activeCount > 0 && (
                        <span className="ml-0.5 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">
                            {activeCount}
                        </span>
                    )}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="start"
                className="w-[580px] p-3 font-mono text-[12px]"
                onCloseAutoFocus={(e) => e.preventDefault()}
            >
                <DropdownMenuLabel className="mb-2 font-mono text-[10px] tracking-wider text-gray-400 uppercase">
                    Filters
                </DropdownMenuLabel>

                {draft.length === 0 && (
                    <p className="py-2 text-center text-[11px] text-gray-400 dark:text-gray-500">
                        No filters. Click + Add Filter to start.
                    </p>
                )}

                <div className="space-y-2">
                    {draft.map((f) => {
                        const isDate = isDateField(f.field);
                        const isRts = f.field === RTS_FIELD;
                        const isNumber = isRts || f.field === ORDERS_FIELD;
                        const isStatus = f.field === STATUS_FIELD;
                        const isCallLogs = f.field === CALL_LOGS_FIELD;
                        // The server has one date range, so a second date row
                        // would be quietly dropped — say so rather than leaving
                        // it looking applied.
                        const ignored = isOrderFilterIgnored(draft, f);
                        const ops = opsFor(f.field);

                        return (
                            <div
                                key={f.id}
                                className="flex items-center gap-1.5"
                            >
                                {/* Field */}
                                <FieldCombobox
                                    value={f.field}
                                    onValueChange={(v) => changeField(f, v)}
                                    grouped={grouped}
                                />

                                {ignored && (
                                    <span
                                        className="flex items-center text-amber-500"
                                        title="Only one date filter applies — this one is ignored."
                                    >
                                        <TriangleAlert className="h-3.5 w-3.5" />
                                    </span>
                                )}

                                {/* Operator */}
                                <Select
                                    value={f.op}
                                    onValueChange={(v) =>
                                        update(f.id, {
                                            op: v,
                                            // Nothing to compare against once
                                            // the question stands on its own.
                                            ...(opTakesValue(v)
                                                ? {}
                                                : { value: '', value2: '' }),
                                        })
                                    }
                                >
                                    <SelectTrigger className="h-8 w-44 rounded-lg border border-black/6 bg-stone-50 px-2 font-mono! text-[11px]! dark:border-white/6 dark:bg-zinc-800">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent className="font-mono text-[11px]">
                                        {Object.entries(ops)
                                            // The numeric comparisons the
                                            // server offers are its own list,
                                            // so never show one it would drop.
                                            .filter(
                                                ([op]) =>
                                                    !isNumber ||
                                                    !(op in NUMERIC_OPS) ||
                                                    comparisons.includes(op),
                                            )
                                            .map(([op, label]) => (
                                                <SelectItem key={op} value={op}>
                                                    {label}
                                                </SelectItem>
                                            ))}
                                    </SelectContent>
                                </Select>

                                {/* Value(s) */}
                                {isCallLogs ? (
                                    <Select
                                        value={f.value || DEFAULT_CALL_KIND}
                                        onValueChange={(v) =>
                                            update(f.id, { value: v })
                                        }
                                    >
                                        <SelectTrigger className="h-8 w-44 rounded-lg border border-black/6 bg-stone-50 px-2 font-mono! text-[11px]! dark:border-white/6 dark:bg-zinc-800">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent className="font-mono text-[11px]">
                                            {kinds.map((k) => (
                                                <SelectItem key={k} value={k}>
                                                    {CALL_KIND_LABELS[k] ?? k}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                ) : isStatus ? (
                                    <MultiValuePicker
                                        options={statuses}
                                        selected={f.value
                                            .split(',')
                                            .filter(Boolean)}
                                        onChange={(next) =>
                                            update(f.id, {
                                                value: next.join(','),
                                            })
                                        }
                                        placeholder="Any status"
                                    />
                                ) : !opTakesValue(f.op) ? (
                                    <span className="px-1 text-[11px] text-gray-400 dark:text-gray-500">
                                        —
                                    </span>
                                ) : isRangeOp(f.op) ? (
                                    <div className="flex items-center gap-1">
                                        <input
                                            type={isDate ? 'date' : 'number'}
                                            min={isNumber ? 0 : undefined}
                                            placeholder={
                                                isDate ? undefined : 'Min'
                                            }
                                            value={f.value}
                                            onChange={(e) =>
                                                update(f.id, {
                                                    value: e.target.value,
                                                })
                                            }
                                            className={clsx(
                                                VALUE_INPUT_CLS,
                                                isDate ? 'w-[112px]' : 'w-20',
                                            )}
                                        />
                                        <span className="text-gray-400">–</span>
                                        <input
                                            type={isDate ? 'date' : 'number'}
                                            min={isNumber ? 0 : undefined}
                                            placeholder={
                                                isDate ? undefined : 'Max'
                                            }
                                            value={f.value2}
                                            onChange={(e) =>
                                                update(f.id, {
                                                    value2: e.target.value,
                                                })
                                            }
                                            className={clsx(
                                                VALUE_INPUT_CLS,
                                                isDate ? 'w-[112px]' : 'w-20',
                                            )}
                                        />
                                    </div>
                                ) : (
                                    <input
                                        type={
                                            isDate
                                                ? 'date'
                                                : isNumber
                                                  ? 'number'
                                                  : 'text'
                                        }
                                        min={isNumber ? 0 : undefined}
                                        max={isRts ? 100 : undefined}
                                        placeholder={
                                            isDate
                                                ? undefined
                                                : isNumber
                                                  ? 'Value'
                                                  : 'Value'
                                        }
                                        value={f.value}
                                        onChange={(e) =>
                                            update(f.id, {
                                                value: e.target.value,
                                            })
                                        }
                                        className={clsx(
                                            VALUE_INPUT_CLS,
                                            isDate ? 'w-[138px]' : 'w-28',
                                        )}
                                    />
                                )}

                                {/* The rate is a whole percent, as the badge
                                    on the row shows it. */}
                                {isRts && opTakesValue(f.op) && (
                                    <span className="text-[11px] text-gray-400">
                                        %
                                    </span>
                                )}

                                {/* Remove */}
                                <button
                                    type="button"
                                    onClick={() => remove(f.id)}
                                    className="ml-auto flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-500/10"
                                >
                                    <X className="h-3.5 w-3.5" />
                                </button>
                            </div>
                        );
                    })}
                </div>

                <DropdownMenuSeparator className="my-2" />

                <button
                    type="button"
                    onClick={add}
                    className="flex w-full items-center gap-1.5 rounded-lg px-2 py-1.5 text-[11px] text-emerald-600 transition-colors hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-500/10"
                >
                    <Plus className="h-3.5 w-3.5" />
                    Add Filter
                </button>

                <DropdownMenuSeparator className="my-2" />

                <div className="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        onClick={cancel}
                        className="h-8 rounded-lg border border-black/6 px-3 text-[11px] text-gray-500 transition-colors hover:border-black/12 hover:text-gray-700 dark:border-white/6 dark:text-gray-400 dark:hover:border-white/12 dark:hover:text-gray-200"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={apply}
                        className="h-8 rounded-lg bg-emerald-500 px-3 text-[11px] font-medium text-white transition-colors hover:bg-emerald-600"
                    >
                        Apply
                    </button>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
