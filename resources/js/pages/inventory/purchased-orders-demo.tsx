import { Head } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

type StatusId = 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8;

interface PurchasedOrder {
    id: number;
    issue_date: string;
    delivery_no: string | null;
    cust_po_no: string | null;
    control_no: string | null;
    item: string;
    cog_amount: number;
    delivery_fee: number;
    total_amount: number;
    status: StatusId;
}

interface PaginatedResponse {
    data: PurchasedOrder[];
    current_page: number;
    last_page: number;
    total: number;
}

const STATUS_OPTIONS: Array<{ value: StatusId; label: string }> = [
    { value: 1, label: 'For Approval' },
    { value: 2, label: 'Approved' },
    { value: 3, label: 'To Pay' },
    { value: 4, label: 'Paid' },
    { value: 5, label: 'For Purchase' },
    { value: 6, label: 'Waiting For Delivery' },
    { value: 7, label: 'Delivered' },
    { value: 8, label: 'Cancelled' },
];

const statusLabel = (value: number): string => {
    return STATUS_OPTIONS.find((s) => s.value === value)?.label ?? `Unknown (${value})`;
};

const formatMoney = (amount: number): string => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
    }).format(amount || 0);
};

const toInputDate = (date: Date): string => {
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 10);
};

const getDefaultDateRange = (): { startDate: string; endDate: string } => {
    const today = new Date();
    const oneWeekBefore = new Date(today);
    oneWeekBefore.setDate(today.getDate() - 7);

    return {
        startDate: toInputDate(oneWeekBefore),
        endDate: toInputDate(today),
    };
};

export default function PurchasedOrdersDemo() {
    const [rows, setRows] = useState<PurchasedOrder[]>([]);
    const [loading, setLoading] = useState(true);
    const [loadingMore, setLoadingMore] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [currentPage, setCurrentPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [totalRows, setTotalRows] = useState(0);

    const defaultDateRangeRef = useRef(getDefaultDateRange());
    const [statusFilter, setStatusFilter] = useState<string>('all');
    const [query, setQuery] = useState('');
    const [startDate, setStartDate] = useState(defaultDateRangeRef.current.startDate);
    const [endDate, setEndDate] = useState(defaultDateRangeRef.current.endDate);
    const requestSerialRef = useRef(0);
    const maxSelectableDate = toInputDate(new Date());

    const apiBase = useMemo(() => {
        if (typeof window === 'undefined') return '';
        const envBase = (import.meta.env.VITE_API_BASE_URL as string | undefined)?.trim();
        if (envBase) return envBase.replace(/\/$/, '');
        if (window.location.port === '5173') return 'http://localhost';
        return '';
    }, []);

    const workspaceSlug = useMemo(() => {
        if (typeof window === 'undefined') return '';
        const match = window.location.pathname.match(/^\/workspaces\/([^/]+)/);
        return match?.[1] ?? '';
    }, []);

    const csrfToken = useMemo(() => {
        if (typeof document === 'undefined') return '';
        return document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') || '';
    }, []);

    const fetchRows = async (page = 1, append = false) => {
        const requestSerial = ++requestSerialRef.current;

        if (append) {
            setLoadingMore(true);
        } else {
            setLoading(true);
            setError(null);
        }

        try {
            const params = new URLSearchParams();
            if (statusFilter !== 'all') params.set('status', statusFilter);
            if (query.trim()) params.set('q', query.trim());
            if (startDate) params.set('start_date', startDate);
            if (endDate) params.set('end_date', endDate);
            params.set('page', String(page));
            params.set('per_page', '50');
            params.set('show_all', '1');

            if (!workspaceSlug) {
                throw new Error('Workspace context is missing in URL.');
            }

            const url = `${apiBase}/api/workspaces/${workspaceSlug}/inventory/purchased-orders?${params.toString()}`;
            const res = await fetch(url, {
                credentials: 'include',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const text = await res.text();
            const json: PaginatedResponse | { message?: string } = text ? JSON.parse(text) : { data: [] };

            if (!res.ok) {
                throw new Error((json as { message?: string }).message || 'Failed to fetch purchased orders.');
            }

            const payload = json as PaginatedResponse;
            const nextRows = Array.isArray(payload.data) ? payload.data : [];

            if (requestSerial !== requestSerialRef.current) {
                return;
            }

            setRows((prev) => (append ? [...prev, ...nextRows] : nextRows));
            setCurrentPage(Number(payload.current_page || page));
            setLastPage(Number(payload.last_page || page));
            setTotalRows(Number(payload.total || nextRows.length));
        } catch (err) {
            if (requestSerial !== requestSerialRef.current) {
                return;
            }

            if (!append) {
                setRows([]);
                setCurrentPage(1);
                setLastPage(1);
                setTotalRows(0);
            }
            setError(err instanceof Error ? err.message : 'Failed to fetch purchased orders.');
        } finally {
            if (append) {
                setLoadingMore(false);
            } else {
                setLoading(false);
            }
        }
    };

    useEffect(() => {
        if (!startDate || !endDate) {
            return;
        }

        void fetchRows(1, false);
    }, [statusFilter, query, startDate, endDate]);

    const hasMore = rows.length < totalRows && currentPage < lastPage;

    const updateStatus = async (id: number, status: StatusId) => {
        try {
            if (!workspaceSlug) {
                throw new Error('Workspace context is missing in URL.');
            }

            const url = `${apiBase}/api/workspaces/${workspaceSlug}/inventory/purchased-orders/${id}/status`;
            const res = await fetch(url, {
                method: 'PATCH',
                credentials: 'include',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ status }),
            });

            const text = await res.text();
            const json: { message?: string } = text ? JSON.parse(text) : {};
            if (!res.ok) {
                throw new Error(json.message || 'Failed to update status.');
            }

            setRows((prev) => prev.map((row) => (row.id === id ? { ...row, status } : row)));
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to update status.');
        }
    };

    const resetFilters = () => {
        const defaults = getDefaultDateRange();
        setQuery('');
        setStatusFilter('all');
        setStartDate(defaults.startDate);
        setEndDate(defaults.endDate);
    };

    return (
        <div className="min-h-screen bg-white text-zinc-900">
            <Head title="Inventory Purchased Orders" />

            <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <header className="mb-6">
                    <h1 className="text-2xl font-semibold tracking-tight">Inventory Purchased Orders</h1>
                    <p className="mt-1 text-sm text-zinc-500">Minimal demo page to validate backend list, filters, and status updates.</p>
                </header>

                <section className="mb-6 grid grid-cols-1 gap-3 rounded-lg border border-zinc-200 bg-zinc-50 p-4 md:grid-cols-4">
                    <input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search Delivery No / PO / Control / Item"
                        className="h-10 rounded-md border border-zinc-300 bg-white px-3 text-sm outline-none ring-0 focus:border-zinc-500"
                    />

                    <select
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value)}
                        className="h-10 rounded-md border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-zinc-500"
                    >
                        <option value="all">All Status</option>
                        {STATUS_OPTIONS.map((opt) => (
                            <option key={opt.value} value={opt.value}>
                                {opt.label}
                            </option>
                        ))}
                    </select>

                    <input
                        type="date"
                        value={startDate}
                        max={maxSelectableDate}
                        onChange={(e) => {
                            const nextValue = e.target.value > maxSelectableDate ? maxSelectableDate : e.target.value;
                            setStartDate(nextValue);
                            if (endDate && nextValue > endDate) {
                                setEndDate(nextValue);
                            }
                        }}
                        className="h-10 rounded-md border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-zinc-500"
                    />

                    <input
                        type="date"
                        value={endDate}
                        max={maxSelectableDate}
                        onChange={(e) => {
                            const nextValue = e.target.value > maxSelectableDate ? maxSelectableDate : e.target.value;
                            setEndDate(nextValue);
                            if (startDate && startDate > nextValue) {
                                setStartDate(nextValue);
                            }
                        }}
                        className="h-10 rounded-md border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-zinc-500"
                    />

                    <button
                        type="button"
                        onClick={resetFilters}
                        className="h-10 rounded-md border border-zinc-300 bg-white px-4 text-sm font-medium text-zinc-700 hover:bg-zinc-100"
                    >
                        Restore Default Filters
                    </button>
                </section>

                {error && <div className="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}

                <div className="overflow-x-auto rounded-lg border border-zinc-200">
                    <table className="min-w-full divide-y divide-zinc-200 text-sm">
                        <thead className="bg-zinc-50">
                            <tr className="text-left text-xs font-medium uppercase tracking-wide text-zinc-500">
                                <th className="px-3 py-3">Issue Date</th>
                                <th className="px-3 py-3">Delivery No.</th>
                                <th className="px-3 py-3">Cust. PO No.</th>
                                <th className="px-3 py-3">Control No.</th>
                                <th className="px-3 py-3">Item</th>
                                <th className="px-3 py-3">COG Amount</th>
                                <th className="px-3 py-3">Delivery Fee</th>
                                <th className="px-3 py-3">Total Amount</th>
                                <th className="px-3 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-zinc-100 bg-white">
                            {loading ? (
                                <tr>
                                    <td colSpan={9} className="px-3 py-10 text-center text-zinc-500">
                                        Loading...
                                    </td>
                                </tr>
                            ) : rows.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="px-3 py-10 text-center text-zinc-500">
                                        No records found.
                                    </td>
                                </tr>
                            ) : (
                                rows.map((row) => (
                                    <tr key={row.id} className="hover:bg-zinc-50">
                                        <td className="whitespace-nowrap px-3 py-2">{row.issue_date}</td>
                                        <td className="whitespace-nowrap px-3 py-2">{row.delivery_no || '-'}</td>
                                        <td className="whitespace-nowrap px-3 py-2">{row.cust_po_no || '-'}</td>
                                        <td className="whitespace-nowrap px-3 py-2">{row.control_no || '-'}</td>
                                        <td className="whitespace-nowrap px-3 py-2">{row.item}</td>
                                        <td className="whitespace-nowrap px-3 py-2">{formatMoney(row.cog_amount)}</td>
                                        <td className="whitespace-nowrap px-3 py-2">{formatMoney(row.delivery_fee)}</td>
                                        <td className="whitespace-nowrap px-3 py-2 font-medium">{formatMoney(row.total_amount)}</td>
                                        <td className="whitespace-nowrap px-3 py-2">
                                            <select
                                                value={row.status}
                                                onChange={(e) => void updateStatus(row.id, Number(e.target.value) as StatusId)}
                                                className="h-9 rounded-md border border-zinc-300 bg-white px-2 text-sm outline-none focus:border-zinc-500"
                                            >
                                                {STATUS_OPTIONS.map((opt) => (
                                                    <option key={opt.value} value={opt.value}>
                                                        {opt.label}
                                                    </option>
                                                ))}
                                            </select>
                                            <span className="ml-2 text-xs text-zinc-500">{statusLabel(row.status)}</span>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                        {!loading && rows.length > 0 && (
                            <tfoot className="border-t border-zinc-200 bg-zinc-50 text-sm text-zinc-600">
                                <tr>
                                    <td colSpan={9} className="px-3 py-3">
                                        {hasMore ? (
                                            <button
                                                type="button"
                                                onClick={() => void fetchRows(currentPage + 1, true)}
                                                disabled={loadingMore}
                                                className="cursor-pointer font-medium text-zinc-800 underline underline-offset-2 disabled:cursor-not-allowed disabled:text-zinc-400"
                                            >
                                                {loadingMore
                                                    ? 'Loading more...'
                                                    : `Showed ${rows.length} of ${totalRows} Datas.. Click here to show more`}
                                            </button>
                                        ) : (
                                            <span>{`Showed ${rows.length} of ${totalRows} Datas.`}</span>
                                        )}
                                    </td>
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>
            </main>
        </div>
    );
}
