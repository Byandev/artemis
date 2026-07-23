import PageHeader from '@/components/common/PageHeader';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import DatePicker from '@/components/ui/date-picker';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { Flame, Search, Trophy } from 'lucide-react';
import moment from 'moment';
import { useMemo, useState } from 'react';

interface EscMember {
    id: number;
    name: string;
    current_streak: number;
    longest_streak: number;
    days_logged: number;
}

interface Props {
    workspace: Workspace;
    members: EscMember[];
    range: { from: string; to: string; days: number };
}

export default function EscTracker({ workspace, members, range }: Props) {
    const [search, setSearch] = useState('');

    // flatpickr fires onChange on each click of a range, so only navigate once
    // both ends are picked. Clearing it (empty array) drops the params and lets
    // the server fall back to its default — the current month.
    const applyRange = (dates: Date[]) => {
        if (dates.length === 1) return;

        const params =
            dates.length === 2
                ? {
                      from: moment(dates[0]).format('YYYY-MM-DD'),
                      to: moment(dates[1]).format('YYYY-MM-DD'),
                  }
                : {};

        router.get(`/workspaces/${workspace.slug}/esc-tracker`, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();
        if (!term) return members;
        return members.filter((member) =>
            member.name.toLowerCase().includes(term),
        );
    }, [members, search]);

    // The whole member list is sent down at once, so the table sorts client-side
    // (no onFetch/meta) — sorting is instant and costs no round trip.
    const columns: ColumnDef<EscMember>[] = [
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Name" />
            ),
            cell: ({ row }) => (
                <span className="text-[12px] font-medium text-gray-800 dark:text-gray-200">
                    {row.original.name}
                </span>
            ),
        },
        {
            accessorKey: 'days_logged',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Days Logged" />
            ),
            cell: ({ row }) => (
                <span
                    className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 font-mono text-[11px] font-medium ${
                        row.original.days_logged > 0
                            ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400'
                            : 'bg-stone-100 text-gray-400 dark:bg-zinc-800 dark:text-gray-600'
                    }`}
                >
                    {row.original.days_logged}
                    <span className="opacity-60">/ {range.days}</span>
                </span>
            ),
        },
        {
            accessorKey: 'current_streak',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Current Streak" />
            ),
            cell: ({ row }) => (
                <span className="inline-flex items-center gap-1 rounded-full bg-orange-50 px-2.5 py-1 font-mono text-[11px] font-medium text-orange-600 dark:bg-orange-500/10 dark:text-orange-400">
                    <Flame className="h-3.5 w-3.5" />
                    {row.original.current_streak}
                </span>
            ),
        },
        {
            accessorKey: 'longest_streak',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Longest Streak" />
            ),
            cell: ({ row }) => (
                <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 font-mono text-[11px] font-medium text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                    <Trophy className="h-3.5 w-3.5" />
                    {row.original.longest_streak}
                </span>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - ESC Tracker`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="ESC Tracker"
                    description="Extreme Self-Care streaks for everyone in this workspace"
                />

                <div className="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                            placeholder="Search members…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>

                    <DatePicker
                        id="esc-tracker-date-range"
                        mode="range"
                        placeholder="Filter by date range"
                        defaultDate={[range.from, range.to]}
                        onChange={applyRange}
                    />
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable columns={columns} data={filtered} />
                </div>

                <p className="mt-2 font-mono text-[10px] text-gray-400 dark:text-gray-500">
                    Days Logged covers {range.from} → {range.to}. Streaks are
                    all-time and not affected by the date range.
                </p>
            </div>
        </AppLayout>
    );
}
