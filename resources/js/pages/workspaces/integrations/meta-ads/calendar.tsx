import PageHeader from '@/components/common/PageHeader';
import { Button } from '@/components/ui/button';
import { MultiSelect } from '@/components/ui/multi-select';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface PageBreakdown {
    id: string;
    name: string;
    count: number;
}

interface PageOption {
    id: string;
    name: string;
}

interface DayData {
    total: number;
    pages: PageBreakdown[];
}

interface Props {
    workspace: { id: number; name: string; slug: string };
    month: string; // 'YYYY-MM'
    monthLabel: string; // 'June 2026'
    prevMonth: string;
    nextMonth: string;
    canGoNext: boolean;
    days: Record<string, DayData>;
    pageTotals: PageBreakdown[];
    pageOptions: PageOption[];
    selectedPages: string[];
    pageOwnerOptions: PageOption[];
    selectedPageOwners: string[];
}

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

/** A stable, color-blind-friendly palette assigned to pages by rank. */
const PAGE_COLORS = [
    'bg-emerald-500',
    'bg-sky-500',
    'bg-violet-500',
    'bg-amber-500',
    'bg-rose-500',
    'bg-teal-500',
    'bg-indigo-500',
    'bg-orange-500',
];

const formatInt = (n: number) => new Intl.NumberFormat().format(n);

const pad = (n: number) => String(n).padStart(2, '0');

export default function AdsCalendar({
    workspace,
    month,
    monthLabel,
    prevMonth,
    nextMonth,
    canGoNext,
    days,
    pageTotals,
    pageOptions,
    selectedPages,
    pageOwnerOptions,
    selectedPageOwners,
}: Props) {
    const base = `/workspaces/${workspace.slug}/integrations/meta/ads-calendar`;

    // Local selection drives the dropdown for instant feedback; the server visit
    // is debounced so toggling several pages doesn't reload (and close) the menu
    // on every click. Re-sync if the server selection changes (e.g. month nav).
    const [pending, setPending] = useState<string[]>(selectedPages);
    useEffect(() => setPending(selectedPages), [selectedPages]);

    const [pendingOwners, setPendingOwners] =
        useState<string[]>(selectedPageOwners);
    useEffect(() => setPendingOwners(selectedPageOwners), [selectedPageOwners]);

    const navTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    // Build a calendar URL preserving the active page filter (omit month to land
    // on the current month — used by the "Today" button).
    const urlFor = (targetMonth?: string) => {
        const qs = new URLSearchParams();
        if (targetMonth) qs.set('month', targetMonth);
        pending.forEach((p) => qs.append('pages[]', p));
        pendingOwners.forEach((o) => qs.append('page_owners[]', o));
        const s = qs.toString();
        return s ? `${base}?${s}` : base;
    };

    // Apply a new page selection, keeping the current month. `preserveState`
    // keeps the dropdown open across the visit; `replace` avoids stacking a
    // history entry per filter change.
    // Both filters travel together — dropping the other one on every change
    // would silently reset it the moment you touched either menu.
    const visit = (pages: string[], owners: string[]) => {
        if (navTimer.current) clearTimeout(navTimer.current);
        navTimer.current = setTimeout(() => {
            router.get(
                base,
                {
                    month,
                    ...(pages.length ? { pages } : {}),
                    ...(owners.length ? { page_owners: owners } : {}),
                },
                { preserveScroll: true, preserveState: true, replace: true },
            );
        }, 350);
    };

    const onPagesChange = (values: string[]) => {
        setPending(values);
        visit(values, pendingOwners);
    };

    const onPageOwnersChange = (values: string[]) => {
        setPendingOwners(values);
        visit(pending, values);
    };

    // Map page id → palette colour by month-wide rank, reused in every day cell.
    const colorById = new Map<string, string>(
        pageTotals.map((p, i) => [p.id, PAGE_COLORS[i % PAGE_COLORS.length]]),
    );

    const [year, monthNum] = month.split('-').map(Number);
    const firstWeekday = new Date(year, monthNum - 1, 1).getDay();
    const daysInMonth = new Date(year, monthNum, 0).getDate();
    const today = `${new Date().getFullYear()}-${pad(new Date().getMonth() + 1)}-${pad(new Date().getDate())}`;

    // Leading blanks for the first week, then one cell per calendar day.
    const cells: (string | null)[] = [
        ...Array.from({ length: firstWeekday }, () => null),
        ...Array.from(
            { length: daysInMonth },
            (_, i) => `${month}-${pad(i + 1)}`,
        ),
    ];

    return (
        <AppLayout>
            <Head title={`Meta Ads · Ads Calendar · ${monthLabel}`} />

            <div className="w-full space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Ads Calendar"
                    description="Campaigns created each day, broken down per Facebook page."
                />

                {/* Page filter + month navigation */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">
                            {monthLabel}
                        </h2>
                        <MultiSelect
                            compact
                            className="w-56"
                            placeholder="All pages"
                            options={pageOptions.map((p) => ({
                                value: p.id,
                                label: p.name,
                            }))}
                            selected={pending}
                            onChange={onPagesChange}
                        />
                        <MultiSelect
                            compact
                            className="w-56"
                            placeholder="All page owners"
                            options={pageOwnerOptions.map((o) => ({
                                value: o.id,
                                label: o.name,
                            }))}
                            selected={pendingOwners}
                            onChange={onPageOwnersChange}
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        <Button asChild variant="outline" size="sm">
                            <Link href={urlFor(prevMonth)} preserveScroll>
                                <ChevronLeft className="h-4 w-4" />
                                Prev
                            </Link>
                        </Button>
                        <Button asChild variant="outline" size="sm">
                            <Link href={urlFor()} preserveScroll>
                                Today
                            </Link>
                        </Button>
                        {canGoNext ? (
                            <Button asChild variant="outline" size="sm">
                                <Link href={urlFor(nextMonth)} preserveScroll>
                                    Next
                                    <ChevronRight className="h-4 w-4" />
                                </Link>
                            </Button>
                        ) : (
                            <Button
                                variant="outline"
                                size="sm"
                                disabled
                                className="opacity-50"
                            >
                                Next
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                        )}
                    </div>
                </div>

                {/* Calendar grid */}
                <div className="overflow-hidden rounded-xl border border-black/6 dark:border-white/8">
                    <div className="grid grid-cols-7 border-b border-black/6 bg-stone-50 dark:border-white/8 dark:bg-zinc-900/60">
                        {WEEKDAYS.map((w) => (
                            <div
                                key={w}
                                className="px-2 py-2 text-center text-[11px] font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                            >
                                {w}
                            </div>
                        ))}
                    </div>
                    <div className="grid grid-cols-7">
                        {cells.map((date, idx) => {
                            if (!date) {
                                return (
                                    <div
                                        key={`blank-${idx}`}
                                        className="min-h-28 border-r border-b border-black/4 bg-stone-50/40 dark:border-white/6 dark:bg-zinc-900/30"
                                    />
                                );
                            }

                            const data = days[date];
                            const dayNum = Number(date.slice(-2));
                            const isToday = date === today;

                            return (
                                <div
                                    key={date}
                                    className={cn(
                                        'min-h-28 border-r border-b border-black/4 p-1.5 dark:border-white/6',
                                        data
                                            ? 'bg-white dark:bg-zinc-900'
                                            : 'bg-white/60 dark:bg-zinc-900/40',
                                    )}
                                >
                                    <div className="mb-1 flex items-center justify-between">
                                        <span
                                            className={cn(
                                                'inline-flex h-6 min-w-6 items-center justify-center rounded-full px-1.5 text-[12px] font-medium',
                                                isToday
                                                    ? 'bg-emerald-500 text-white'
                                                    : 'text-gray-500 dark:text-gray-400',
                                            )}
                                        >
                                            {dayNum}
                                        </span>
                                        {data && (
                                            <span className="rounded-md bg-emerald-50 px-1.5 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                                                {formatInt(data.total)}
                                            </span>
                                        )}
                                    </div>

                                    {data && (
                                        <DayBreakdown
                                            data={data}
                                            colorById={colorById}
                                        />
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Per-page legend / month totals */}
                {pageTotals.length > 0 && (
                    <div className="rounded-xl border border-black/6 bg-white p-4 dark:border-white/8 dark:bg-zinc-900">
                        <p className="mb-3 text-[11px] font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">
                            Campaigns by page · {monthLabel}
                        </p>
                        <div className="flex flex-wrap gap-x-6 gap-y-2">
                            {pageTotals.map((p) => (
                                <div
                                    key={p.id}
                                    className="flex items-center gap-2"
                                >
                                    <span
                                        className={cn(
                                            'h-2.5 w-2.5 shrink-0 rounded-full',
                                            colorById.get(p.id) ??
                                                'bg-gray-400',
                                        )}
                                    />
                                    <span className="text-[13px] text-gray-700 dark:text-gray-300">
                                        {p.name}
                                    </span>
                                    <span className="text-[13px] font-semibold text-gray-900 dark:text-gray-100">
                                        {formatInt(p.count)}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

/** Per-day page list: shows the top 3 pages inline, the rest behind a popover. */
function DayBreakdown({
    data,
    colorById,
}: {
    data: DayData;
    colorById: Map<string, string>;
}) {
    const visible = data.pages.slice(0, 3);
    const hidden = data.pages.slice(3);

    return (
        <div className="space-y-0.5">
            {visible.map((p) => (
                <PageRow key={p.id} page={p} colorById={colorById} />
            ))}
            {hidden.length > 0 && (
                <Popover>
                    <PopoverTrigger asChild>
                        <button
                            type="button"
                            className="w-full rounded px-1 py-0.5 text-left text-[11px] text-gray-400 hover:bg-stone-100 hover:text-gray-600 dark:hover:bg-zinc-800 dark:hover:text-gray-300"
                        >
                            +{hidden.length} more
                        </button>
                    </PopoverTrigger>
                    <PopoverContent className="w-60 p-2" align="start">
                        <p className="mb-1.5 px-1 text-[11px] font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">
                            All pages
                        </p>
                        <div className="space-y-0.5">
                            {data.pages.map((p) => (
                                <PageRow
                                    key={p.id}
                                    page={p}
                                    colorById={colorById}
                                />
                            ))}
                        </div>
                    </PopoverContent>
                </Popover>
            )}
        </div>
    );
}

function PageRow({
    page,
    colorById,
}: {
    page: PageBreakdown;
    colorById: Map<string, string>;
}) {
    return (
        <div className="flex items-center gap-1.5 px-1">
            <span
                className={cn(
                    'h-2 w-2 shrink-0 rounded-full',
                    colorById.get(page.id) ?? 'bg-gray-400',
                )}
            />
            <span
                className="flex-1 truncate text-[11px] text-gray-600 dark:text-gray-400"
                title={page.name}
            >
                {page.name}
            </span>
            <span className="text-[11px] font-medium text-gray-900 dark:text-gray-200">
                {page.count}
            </span>
        </div>
    );
}
