import PageHeader from '@/components/common/PageHeader';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { debounce, omit } from 'lodash';
import { useEffect, useMemo, useState } from 'react';
import DailyRecordFilters, { InternOption } from './daily-record-filters';

interface DailyRecord {
    id: number;
    record_date: string | null;
    sales: string | null;
    roas: string | null;
    ad_spent: string | null;
    rts_rate: string | null;
    rts_amount: string | null;
    intern_id: number | null;
    intern_name: string | null;
    intern_company: string | null;
    intern_username: string | null;
}

interface Props {
    workspace: Workspace;
    records: PaginatedData<DailyRecord>;
    interns: InternOption[];
    query: {
        sort: string;
        perPage: number | null;
        filter: {
            search?: string;
            gencys_intern_id?: string | string[];
            date_start?: string;
            date_end?: string;
        };
    };
}

const peso = (v: string | null) =>
    v === null || v === ''
        ? '—'
        : `₱${Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;

// ROAS and RTS rate arrive already as percentages — just append the sign.
const percent = (v: string | null) =>
    v === null || v === '' ? '—' : `${Number(v).toFixed(2)}%`;

const fmtDate = (d: string | null) => (d ? d.slice(0, 10) : '—');

const toArray = (v?: string | string[]): string[] =>
    v === undefined ? [] : Array.isArray(v) ? v : [v];

type FilterPayload = {
    search?: string;
    gencys_intern_id?: string[];
    date_start?: string;
    date_end?: string;
};

export default function InternDailyRecordsIndex({
    workspace,
    records,
    interns,
    query,
}: Props) {
    const [search, setSearch] = useState(query.filter?.search ?? '');
    const [internIds, setInternIds] = useState<string[]>(
        toArray(query.filter?.gencys_intern_id),
    );
    const [dateStart, setDateStart] = useState(query.filter?.date_start ?? '');
    const [dateEnd, setDateEnd] = useState(query.filter?.date_end ?? '');

    const baseUrl = `/workspaces/${workspace.slug}/gencys/intern-daily-records`;

    const initialSorting = useMemo(
        () => toFrontendSort(query.sort ?? null),
        [query.sort],
    );

    // Build the filter payload from the current control values.
    const buildFilter = (over: Partial<FilterPayload> = {}): FilterPayload => ({
        search: search || undefined,
        gencys_intern_id: internIds.length ? internIds : undefined,
        date_start: dateStart || undefined,
        date_end: dateEnd || undefined,
        ...over,
    });

    const fetchData = (
        overrides: {
            filter?: FilterPayload;
            page?: number;
            sort?: string;
        } = {},
    ) => {
        const { filter, ...rest } = overrides;
        router.get(
            baseUrl,
            {
                filter: filter ?? buildFilter(),
                sort: query.sort,
                per_page: query.perPage ?? records.per_page ?? undefined,
                ...rest,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const debouncedFetch = useMemo(
        () => debounce(() => fetchData({ page: 1 }), 400),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [search],
    );

    useEffect(() => {
        if ((query.filter?.search ?? '') !== search) debouncedFetch();
        return () => debouncedFetch.cancel();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const applyInterns = (ids: string[]) => {
        setInternIds(ids);
        fetchData({
            page: 1,
            filter: buildFilter({
                gencys_intern_id: ids.length ? ids : undefined,
            }),
        });
    };

    const applyDateRange = (start: string, end: string) => {
        setDateStart(start);
        setDateEnd(end);
        fetchData({
            page: 1,
            filter: buildFilter({
                date_start: start || undefined,
                date_end: end || undefined,
            }),
        });
    };

    const columns = useMemo<ColumnDef<DailyRecord>[]>(() => {
        const money = (
            key: 'sales' | 'ad_spent' | 'rts_amount',
            title: string,
        ): ColumnDef<DailyRecord> => ({
            accessorKey: key,
            enableSorting: true,
            meta: {
                headerClassName: 'text-right',
                cellClassName: 'text-right',
            },
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title={title}
                    className="justify-end"
                />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-gray-700 dark:text-gray-300">
                    {peso(row.original[key])}
                </span>
            ),
        });

        return [
            {
                accessorKey: 'record_date',
                enableSorting: true,
                meta: { headerClassName: 'w-28', cellClassName: 'w-28' },
                header: ({ column }) => (
                    <SortableHeader column={column} title="Date" />
                ),
                cell: ({ row }) => (
                    <span className="font-mono text-[12px] text-gray-600 dark:text-gray-400">
                        {fmtDate(row.original.record_date)}
                    </span>
                ),
            },
            {
                accessorKey: 'intern_name',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Intern" />
                ),
                cell: ({ row }) => (
                    <div className="flex flex-col">
                        <span className="text-[12px] font-medium text-gray-800 dark:text-gray-200">
                            {row.original.intern_name ?? '—'}
                        </span>
                        {row.original.intern_company && (
                            <span className="text-[11px] text-gray-400 dark:text-gray-500">
                                {row.original.intern_company}
                            </span>
                        )}
                    </div>
                ),
            },
            money('sales', 'Sales'),
            money('ad_spent', 'Ad Spent'),
            {
                accessorKey: 'roas',
                enableSorting: true,
                meta: {
                    headerClassName: 'text-right',
                    cellClassName: 'text-right',
                },
                header: ({ column }) => (
                    <SortableHeader
                        column={column}
                        title="ROAS"
                        className="justify-end"
                    />
                ),
                cell: ({ row }) => (
                    <span className="font-mono text-[12px] text-gray-700 dark:text-gray-300">
                        {percent(row.original.roas)}
                    </span>
                ),
            },
            {
                accessorKey: 'rts_rate',
                enableSorting: true,
                meta: {
                    headerClassName: 'text-right',
                    cellClassName: 'text-right',
                },
                header: ({ column }) => (
                    <SortableHeader
                        column={column}
                        title="RTS Rate"
                        className="justify-end"
                    />
                ),
                cell: ({ row }) => (
                    <span className="font-mono text-[12px] text-gray-700 dark:text-gray-300">
                        {percent(row.original.rts_rate)}
                    </span>
                ),
            },
            money('rts_amount', 'RTS Amount'),
        ];
    }, []);

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Intern Daily Records`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Intern Daily Records"
                    description="Per-intern daily sales, ad spend, ROAS and RTS from Gencys ERP."
                />

                <DailyRecordFilters
                    interns={interns}
                    search={search}
                    internIds={internIds}
                    dateStart={dateStart}
                    dateEnd={dateEnd}
                    onSearchChange={setSearch}
                    onInternsApply={applyInterns}
                    onDateRangeChange={applyDateRange}
                />

                <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={records.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(records, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                baseUrl,
                                {
                                    sort: params?.sort,
                                    filter: buildFilter(),
                                    page: params?.page ?? 1,
                                    per_page:
                                        params?.per_page ??
                                        query.perPage ??
                                        records.per_page,
                                },
                                {
                                    preserveState: true,
                                    replace: true,
                                    preserveScroll: true,
                                },
                            );
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
