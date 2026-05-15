import PageHeader from '@/components/common/PageHeader';
import Filters, { FilterValue } from '@/components/filters/Filters';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import DatePicker from '@/components/ui/date-picker';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { numberFormatter, percentageFormatter } from '@/lib/utils';
import { PaginatedData } from '@/types';
import { Sequence } from '@/types/models/Botcake/Sequence';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import { ExternalLink, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

const parseIds = (str?: string): number[] => {
    if (!str) return [];
    return str
        .split(',')
        .map((s) => Number(s.trim()))
        .filter((n) => Number.isFinite(n) && n > 0);
};

type Mode = 'overall' | 'historical';

interface Props {
    workspace: Workspace;
    sequences: PaginatedData<Sequence>;
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        mode?: Mode;
        from?: string;
        to?: string;
        filter?: {
            search?: string;
            page_ids?: string;
            shop_ids?: string;
            sent_min?: string;
        };
    };
}

const toIsoDate = (d: Date) => {
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
};

const defaultRange = (): [string, string] => {
    const to = new Date();
    const from = new Date();
    from.setDate(from.getDate() - 6);
    return [toIsoDate(from), toIsoDate(to)];
};

export default function Sequences({ workspace, sequences, query }: Props) {
    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );
    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [sentMinValue, setSentMinValue] = useState(
        query?.filter?.sent_min ?? '',
    );

    const initialFilterValue: FilterValue = useMemo(
        () => ({
            teamIds: [],
            productIds: [],
            shopIds: parseIds(query?.filter?.shop_ids),
            pageIds: parseIds(query?.filter?.page_ids),
            userIds: [],
        }),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [],
    );
    const [filter, setFilter] = useState<FilterValue>(initialFilterValue);

    const mode: Mode = query?.mode === 'historical' ? 'historical' : 'overall';
    const [defaultFrom, defaultTo] = defaultRange();
    const fromDate = query?.from ?? defaultFrom;
    const toDate = query?.to ?? defaultTo;

    const navigate = (
        overrides: Record<string, string | number | null | undefined> = {},
    ) => {
        router.get(
            `/workspaces/${workspace.slug}/botcake/sequences`,
            {
                sort: query?.sort ?? undefined,
                'filter[search]': searchValue || undefined,
                'filter[page_ids]': filter.pageIds.join(',') || undefined,
                'filter[shop_ids]': filter.shopIds.join(',') || undefined,
                'filter[sent_min]': sentMinValue || undefined,
                page: query?.page ?? 1,
                mode: mode === 'historical' ? 'historical' : undefined,
                from: mode === 'historical' ? fromDate : undefined,
                to: mode === 'historical' ? toDate : undefined,
                ...overrides,
            },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['sequences', 'query'],
            },
        );
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            navigate({ page: searchValue ? 1 : (query?.page ?? 1) });
        }, 500);
        return () => clearTimeout(timer);
    }, [searchValue]);

    useEffect(() => {
        const timer = setTimeout(() => {
            navigate({ page: 1 });
        }, 500);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [sentMinValue]);

    const switchMode = (next: Mode) => {
        if (next === mode) return;
        if (next === 'historical') {
            navigate({
                mode: 'historical',
                from: defaultFrom,
                to: defaultTo,
                page: 1,
            });
        } else {
            navigate({
                mode: undefined,
                from: undefined,
                to: undefined,
                page: 1,
            });
        }
    };

    const onRangeChange = (dates: Date[]) => {
        if (dates.length !== 2) return;
        navigate({
            mode: 'historical',
            from: toIsoDate(dates[0]),
            to: toIsoDate(dates[1]),
            page: 1,
        });
    };

    const columns: ColumnDef<Sequence>[] = [
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Name" />
            ),
            cell: ({ row }) => {
                const sequence = row.original;
                const previewUrl = `https://botcake.io/${sequence.page_id}/sequence/${sequence.id}`;

                return (
                    <div>
                        <a
                            href={previewUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="group inline-flex items-center gap-1.5 font-medium text-gray-900 hover:text-emerald-600 dark:text-gray-100 dark:hover:text-emerald-400"
                        >
                            {sequence.name}
                            <ExternalLink className="h-3 w-3 opacity-0 transition-opacity group-hover:opacity-100" />
                        </a>
                        <p className="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                            {sequence.page?.name ?? '-'}
                        </p>
                    </div>
                );
            },
        },
        {
            accessorKey: 'total_sent',
            header: ({ column }) => (
                <SortableHeader className="w-28" column={column} title="Sent" />
            ),
            cell: ({ row }) => numberFormatter(row.original.total_sent),
        },
        {
            accessorKey: 'total_phone_number',
            header: ({ column }) => (
                <SortableHeader
                    className="w-32"
                    column={column}
                    title="Phone Number"
                />
            ),
            cell: ({ row }) => numberFormatter(row.original.total_phone_number),
        },
        {
            accessorKey: 'success_rate',
            header: ({ column }) => (
                <SortableHeader
                    className="w-28"
                    column={column}
                    title="Success Rate"
                />
            ),
            cell: ({ row }) => percentageFormatter(row.original.success_rate),
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Botcake Sequences`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Sequences"
                    description="Schedule and manage automated message sequences"
                />

                <div className="mb-3 flex flex-wrap items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                            placeholder="Search sequences…"
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                        />
                    </div>

                    <div className="relative w-32">
                        <input
                            type="number"
                            min={0}
                            inputMode="numeric"
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 px-3 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                            placeholder="Min sent"
                            value={sentMinValue}
                            onChange={(e) => setSentMinValue(e.target.value)}
                        />
                    </div>

                    <div className="ml-auto flex items-center gap-2">
                        <Filters
                            workspace={workspace}
                            initialValue={filter}
                            onChange={(value) => {
                                setFilter(value);
                                navigate({
                                    'filter[page_ids]':
                                        value.pageIds.join(',') || undefined,
                                    'filter[shop_ids]':
                                        value.shopIds.join(',') || undefined,
                                    page: 1,
                                });
                            }}
                        />

                        {mode === 'historical' && (
                            <DatePicker
                                id="sequences-date-range"
                                mode="range"
                                defaultDate={
                                    [fromDate, toDate] as unknown as string
                                }
                                onChange={onRangeChange}
                                placeholder="Select range"
                            />
                        )}

                        <div className="inline-flex h-9 items-center rounded-[10px] border border-black/8 bg-white p-0.5 shadow-[0_1px_3px_rgba(0,0,0,0.06),0_1px_2px_rgba(0,0,0,0.04)] dark:border-white/8 dark:bg-zinc-900 dark:shadow-none">
                            <button
                                type="button"
                                onClick={() => switchMode('overall')}
                                className={`h-8 rounded-lg px-3 text-[12px]! font-medium transition-colors ${
                                    mode === 'overall'
                                        ? 'bg-emerald-500 text-white'
                                        : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100'
                                }`}
                            >
                                Overall
                            </button>
                            <button
                                type="button"
                                onClick={() => switchMode('historical')}
                                className={`h-8 rounded-lg px-3 text-[12px]! font-medium transition-colors ${
                                    mode === 'historical'
                                        ? 'bg-emerald-500 text-white'
                                        : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100'
                                }`}
                            >
                                Historical
                            </button>
                        </div>
                    </div>
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={sequences.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(sequences, ['data']) }}
                        onFetch={(params) => {
                            navigate({
                                sort: params?.sort,
                                page: params?.page ?? 1,
                                per_page: params?.per_page,
                            });
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
