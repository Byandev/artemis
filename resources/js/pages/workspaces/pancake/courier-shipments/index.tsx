import PageHeader from '@/components/common/PageHeader';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import DatePicker from '@/components/ui/date-picker';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router, useForm } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import flatpickr from 'flatpickr';
import DateOption = flatpickr.Options.DateOption;
import { debounce, omit } from 'lodash';
import { Search, Upload, X } from 'lucide-react';
import moment from 'moment';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

interface PancakeOrderRef {
    id: number;
    order_number: string | null;
    status: number | null;
    status_name: string | null;
    parcel_status: string | null;
    delivered_at: string | null;
    returned_at: string | null;
}

interface Shipment {
    id: number;
    courier: string;
    waybill_no: string;
    order_number: string | null;
    order_status: string | null;
    receiver: string | null;
    receiver_cellphone: string | null;
    cod: string | number | null;
    preferred_pickup_date: string | null;
    item_name: string | null;
    cod_fee: string | number | null;
    receivable_freight: string | number | null;
    total_shipping_cost: string | number | null;
    rts_reason: string | null;
    pancake_order_id: number | null;
    pancake_order: PancakeOrderRef | null;
}

interface Totals {
    count: number;
    matched_count: number;
    total_shipping_cost: number;
    cod_fee: number;
    receivable_freight: number;
    cod: number;
    matched_total_shipping_cost: number;
    matched_cod_fee: number;
}

interface Props {
    workspace: Workspace;
    shipments: PaginatedData<Shipment>;
    totals: Totals;
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: {
            search?: string;
            courier?: string;
            order_status?: string;
            matched?: string | boolean;
            date_from?: string;
            date_to?: string;
        };
    };
}

const fmt = (v: number | string | null | undefined) =>
    v == null ? '—' : Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function CourierShipmentsIndex({ workspace, shipments, totals, query }: Props) {
    const baseUrl = `/workspaces/${workspace.slug}/pancake/courier-shipments`;
    const initialSorting = useMemo(() => toFrontendSort(query?.sort ?? '-preferred_pickup_date'), [query?.sort]);

    const [search, setSearch] = useState(query?.filter?.search ?? '');
    const [matched, setMatched] = useState<'' | 'true' | 'false'>(
        query?.filter?.matched === true || query?.filter?.matched === 'true'
            ? 'true'
            : query?.filter?.matched === false || query?.filter?.matched === 'false'
              ? 'false'
              : '',
    );
    const [dateFrom, setDateFrom] = useState<string>(query?.filter?.date_from ?? '');
    const [dateTo, setDateTo] = useState<string>(query?.filter?.date_to ?? '');
    const defaultDate = useMemo(
        () => (dateFrom && dateTo ? ([dateFrom, dateTo] as never as DateOption) : undefined),
        [],
    );

    const buildFilter = (s: string, m: string, df: string, dt: string) => ({
        search: s || undefined,
        matched: m || undefined,
        date_from: df || undefined,
        date_to: dt || undefined,
    });

    const reload = useCallback(
        debounce((s: string, m: string, df: string, dt: string) => {
            router.get(
                baseUrl,
                {
                    sort: query?.sort,
                    filter: buildFilter(s, m, df, dt),
                    page: 1,
                    per_page: query?.perPage ?? shipments.per_page,
                },
                { preserveState: true, replace: true, preserveScroll: true, only: ['shipments', 'totals', 'query'] },
            );
        }, 400),
        [baseUrl, query?.sort, query?.perPage, shipments.per_page],
    );

    const initialMount = useRef(true);
    useEffect(() => {
        if (initialMount.current) {
            initialMount.current = false;
            return;
        }
        reload(search, matched, dateFrom, dateTo);
        return () => reload.cancel();
    }, [search, matched, dateFrom, dateTo]);

    const importForm = useForm<{ file: File | null; courier: string }>({ file: null, courier: 'jt' });
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [importOpen, setImportOpen] = useState(false);

    const submitImport = (e: React.FormEvent) => {
        e.preventDefault();
        if (!importForm.data.file) return;
        importForm.post(`${baseUrl}/import`, {
            forceFormData: true,
            onSuccess: () => {
                setImportOpen(false);
                importForm.reset();
                if (fileInputRef.current) fileInputRef.current.value = '';
            },
        });
    };

    const columns: ColumnDef<Shipment>[] = [
        {
            accessorKey: 'preferred_pickup_date',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Pickup Date" />,
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                    {row.original.preferred_pickup_date ? row.original.preferred_pickup_date.slice(0, 10) : '—'}
                </span>
            ),
        },
        {
            accessorKey: 'waybill_no',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Waybill" />,
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-800 dark:text-gray-200">
                    {row.original.waybill_no}
                </span>
            ),
        },
        {
            accessorKey: 'order_number',
            enableSorting: false,
            header: () => (
                <span className="font-mono text-[10px] uppercase tracking-wider text-gray-400">Pancake Order</span>
            ),
            cell: ({ row }) => {
                const po = row.original.pancake_order;
                if (!po) {
                    return (
                        <span className="inline-flex items-center rounded-full bg-stone-100 px-2 py-0.5 font-mono text-[10px] text-gray-500 dark:bg-zinc-800 dark:text-gray-500">
                            unmatched
                        </span>
                    );
                }
                return (
                    <div className="flex flex-col">
                        <span className="font-mono text-[11px] font-medium text-emerald-700 dark:text-emerald-400">
                            #{po.order_number ?? po.id}
                        </span>
                        {po.parcel_status && (
                            <span className="font-mono text-[10px] text-gray-500 dark:text-gray-500">{po.parcel_status}</span>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'receiver',
            enableSorting: false,
            header: () => <span className="font-mono text-[10px] uppercase tracking-wider text-gray-400">Receiver</span>,
            cell: ({ row }) => (
                <div className="flex flex-col">
                    <span className="text-[12px] text-gray-800 dark:text-gray-200">{row.original.receiver ?? '—'}</span>
                    <span className="font-mono text-[10px] text-gray-500 dark:text-gray-500">
                        {row.original.receiver_cellphone ?? ''}
                    </span>
                </div>
            ),
        },
        {
            accessorKey: 'order_status',
            enableSorting: false,
            header: () => <span className="font-mono text-[10px] uppercase tracking-wider text-gray-400">JT Status</span>,
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-600 dark:text-gray-400">
                    {row.original.order_status ?? '—'}
                </span>
            ),
        },
        {
            accessorKey: 'cod',
            enableSorting: false,
            header: () => <span className="font-mono text-[10px] uppercase tracking-wider text-gray-400">COD</span>,
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-700 dark:text-gray-300">
                    ₱{fmt(row.original.cod)}
                </span>
            ),
        },
        {
            accessorKey: 'cod_fee',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="COD Fee" />,
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-700 dark:text-gray-300">
                    ₱{fmt(row.original.cod_fee)}
                </span>
            ),
        },
        {
            accessorKey: 'total_shipping_cost',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Total Shipping" />,
            cell: ({ row }) => (
                <span className="font-mono text-[12px] font-semibold text-gray-800 dark:text-gray-200">
                    ₱{fmt(row.original.total_shipping_cost)}
                </span>
            ),
        },
        {
            accessorKey: 'rts_reason',
            enableSorting: false,
            header: () => <span className="font-mono text-[10px] uppercase tracking-wider text-gray-400">RTS Reason</span>,
            cell: ({ row }) => (
                <span className="font-mono text-[10px] text-gray-500 dark:text-gray-500">
                    {row.original.rts_reason ?? '—'}
                </span>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Courier Shipments`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Courier Shipments"
                    description="Import courier shipping reports and match them to your Pancake orders."
                >
                    <button
                        onClick={() => setImportOpen((v) => !v)}
                        className="flex h-8 items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                    >
                        <Upload className="h-3.5 w-3.5" />
                        Import xlsx
                    </button>
                </PageHeader>

                {importOpen && (
                    <form
                        onSubmit={submitImport}
                        className="mb-3 flex flex-col gap-2 rounded-[14px] border border-emerald-200 bg-emerald-50/40 p-3 dark:border-emerald-900/40 dark:bg-emerald-950/20 md:flex-row md:items-center"
                    >
                        <select
                            value={importForm.data.courier}
                            onChange={(e) => importForm.setData('courier', e.target.value)}
                            className="h-9 rounded-[10px] border border-black/10 bg-white px-2 font-mono! text-[12px]! text-gray-800 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-100"
                        >
                            <option value="jt">J&amp;T</option>
                        </select>
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept=".xlsx,.xls"
                            onChange={(e) => importForm.setData('file', e.target.files?.[0] ?? null)}
                            className="h-9 flex-1 rounded-[10px] border border-black/10 bg-white px-2 font-mono! text-[12px]! text-gray-800 file:mr-3 file:rounded-md file:border-0 file:bg-stone-100 file:px-3 file:py-1 file:font-mono file:text-[11px] file:text-gray-700 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-100 dark:file:bg-zinc-800 dark:file:text-gray-300"
                        />
                        <button
                            type="submit"
                            disabled={!importForm.data.file || importForm.processing}
                            className="h-9 rounded-[10px] bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white disabled:opacity-50"
                        >
                            {importForm.processing ? 'Importing…' : 'Upload & Match'}
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                setImportOpen(false);
                                importForm.reset();
                                if (fileInputRef.current) fileInputRef.current.value = '';
                            }}
                            className="h-9 rounded-[10px] border border-black/10 bg-white px-3 font-mono! text-[12px]! text-gray-700 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-300"
                        >
                            Cancel
                        </button>
                    </form>
                )}

                <div className="mb-4 grid grid-cols-2 gap-2 md:grid-cols-4">
                    <TotalCard label="Shipments" value={totals.count.toLocaleString('en-PH')} sub={`${totals.matched_count.toLocaleString('en-PH')} matched`} />
                    <TotalCard
                        label="Total Shipping"
                        value={`₱${fmt(totals.total_shipping_cost)}`}
                        sub={`Matched: ₱${fmt(totals.matched_total_shipping_cost)}`}
                    />
                    <TotalCard
                        label="COD Fee"
                        value={`₱${fmt(totals.cod_fee)}`}
                        sub={`Matched: ₱${fmt(totals.matched_cod_fee)}`}
                    />
                    <TotalCard label="COD Collected" value={`₱${fmt(totals.cod)}`} sub={`Freight: ₱${fmt(totals.receivable_freight)}`} />
                </div>

                <div className="mb-3 flex flex-col items-stretch gap-2 md:flex-row md:items-center">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 outline-none transition-all placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600"
                            placeholder="Search waybill, order #, receiver, phone…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>
                    <select
                        value={matched}
                        onChange={(e) => setMatched(e.target.value as '' | 'true' | 'false')}
                        className="h-9 rounded-[10px] border border-black/10 bg-white px-2 font-mono! text-[12px]! text-gray-800 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-100"
                    >
                        <option value="">All shipments</option>
                        <option value="true">Matched only</option>
                        <option value="false">Unmatched only</option>
                    </select>
                    <DatePicker
                        id="courier-shipments-date-range"
                        mode="range"
                        placeholder="Filter by pickup date"
                        defaultDate={defaultDate}
                        onChange={(dates) => {
                            if (dates.length === 2) {
                                setDateFrom(moment(dates[0]).format('YYYY-MM-DD'));
                                setDateTo(moment(dates[1]).format('YYYY-MM-DD'));
                            } else if (dates.length === 0) {
                                setDateFrom('');
                                setDateTo('');
                            }
                        }}
                    />
                    {(search || matched || dateFrom || dateTo) && (
                        <button
                            onClick={() => {
                                setSearch('');
                                setMatched('');
                                setDateFrom('');
                                setDateTo('');
                            }}
                            className="flex h-9 items-center gap-1 rounded-[10px] border border-black/10 bg-white px-3 font-mono! text-[12px]! text-gray-700 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-300"
                        >
                            <X className="h-3.5 w-3.5" />
                            Clear
                        </button>
                    )}
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={shipments.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(shipments, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                baseUrl,
                                {
                                    sort: params?.sort,
                                    filter: buildFilter(search, matched, dateFrom, dateTo),
                                    page: params?.page ?? 1,
                                    per_page: params?.per_page ?? query?.perPage ?? shipments.per_page,
                                },
                                { preserveState: true, replace: true, preserveScroll: true },
                            );
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    );
}

function TotalCard({ label, value, sub }: { label: string; value: string; sub?: string }) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-3 dark:border-white/6 dark:bg-zinc-900">
            <div className="font-mono text-[10px] uppercase tracking-wider text-gray-400">{label}</div>
            <div className="mt-1 font-mono text-[15px] font-semibold text-gray-800 dark:text-gray-100">{value}</div>
            {sub && <div className="mt-0.5 font-mono text-[10px] text-gray-500 dark:text-gray-500">{sub}</div>}
        </div>
    );
}
