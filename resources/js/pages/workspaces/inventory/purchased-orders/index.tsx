import { Head } from '@inertiajs/react';
import { Calendar } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';

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
    per_page: number;
    total: number;
}

interface Props {
    workspace: Workspace;
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

const Index = ({ workspace }: Props) => {
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
    const startDateRef = useRef<HTMLInputElement | null>(null);
    const endDateRef = useRef<HTMLInputElement | null>(null);
    const requestSerialRef = useRef(0);
    const maxSelectableDate = toInputDate(new Date());

    const apiBase = useMemo(() => {
        if (typeof window === 'undefined') return '';
        const envBase = (import.meta.env.VITE_API_BASE_URL as string | undefined)?.trim();
        if (envBase) return envBase.replace(/\/$/, '');
        if (window.location.port === '5173') return 'http://localhost';
        return '';
    }, []);

    const csrfToken = useMemo(() => {
        if (typeof document === 'undefined') return '';

        const metaToken = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content');

        if (metaToken) return metaToken;

        const xsrfCookie = document.cookie
            .split('; ')
            .find((part) => part.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];

        return xsrfCookie ? decodeURIComponent(xsrfCookie) : '';
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

            const url = `${apiBase}/api/workspaces/${workspace.slug}/inventory/purchased-orders?${params.toString()}`;
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

    const openDatePicker = (ref: React.RefObject<HTMLInputElement | null>) => {
        const input = ref.current;
        if (!input) return;

        if (typeof input.showPicker === 'function') {
            input.showPicker();
            return;
        }

        input.focus();
    };

    const resetFilters = () => {
        const defaults = getDefaultDateRange();
        setQuery('');
        setStatusFilter('all');
        setStartDate(defaults.startDate);
        setEndDate(defaults.endDate);
    };

    const updateStatus = async (id: number, status: StatusId) => {
        try {
            const url = `${apiBase}/api/workspaces/${workspace.slug}/inventory/purchased-orders/${id}/status`;
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

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Inventory Purchased Orders`} />

            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <header className="mb-6">
                    <h1 className="text-2xl font-semibold tracking-tight">Inventory Purchased Orders</h1>
                    <p className="mt-1 text-sm text-zinc-500">Workspace-scoped list, filter, and status update demo.</p>
                </header>

                <section className="mb-6 grid grid-cols-1 gap-3 rounded-lg border border-zinc-200 bg-zinc-50 p-4 md:grid-cols-2 xl:grid-cols-4">
                    <div className="space-y-1">
                        <label className="text-xs font-medium text-zinc-600">Search</label>
                        <input
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Delivery No / PO / Control / Item"
                            className="h-10 w-full rounded-md border border-zinc-300 bg-white px-3 text-sm outline-none ring-0 focus:border-zinc-500"
                        />
                    </div>

                    <div className="space-y-1">
                        <label className="text-xs font-medium text-zinc-600">Status</label>
                        <select
                            value={statusFilter}
                            onChange={(e) => setStatusFilter(e.target.value)}
                            className="h-10 w-full rounded-md border border-zinc-300 bg-white px-3 text-sm outline-none focus:border-zinc-500"
                        >
                            <option value="all">All Status</option>
                            {STATUS_OPTIONS.map((opt) => (
                                <option key={opt.value} value={opt.value}>
                                    {opt.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="space-y-1">
                        <label className="text-xs font-medium text-zinc-600">Start Date</label>
                        <div className="relative">
                            <input
                                ref={startDateRef}
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
                                onKeyDown={(e) => e.preventDefault()}
                                onPaste={(e) => e.preventDefault()}
                                className="h-10 w-full rounded-md border border-zinc-300 bg-white px-3 pr-10 text-sm outline-none focus:border-zinc-500"
                            />
                            <button
                                type="button"
                                onClick={() => openDatePicker(startDateRef)}
                                className="absolute right-2 top-1/2 -translate-y-1/2 text-zinc-500 hover:text-zinc-700"
                                aria-label="Open start date picker"
                            >
                                <Calendar className="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div className="space-y-1">
                        <label className="text-xs font-medium text-zinc-600">End Date</label>
                        <div className="relative">
                            <input
                                ref={endDateRef}
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
                                onKeyDown={(e) => e.preventDefault()}
                                onPaste={(e) => e.preventDefault()}
                                className="h-10 w-full rounded-md border border-zinc-300 bg-white px-3 pr-10 text-sm outline-none focus:border-zinc-500"
                            />
                            <button
                                type="button"
                                onClick={() => openDatePicker(endDateRef)}
                                className="absolute right-2 top-1/2 -translate-y-1/2 text-zinc-500 hover:text-zinc-700"
                                aria-label="Open end date picker"
                            >
                                <Calendar className="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div className="space-y-1 md:col-span-2 xl:col-span-4">
                        <button
                            type="button"
                            onClick={resetFilters}
                            className="h-10 rounded-md border border-zinc-300 bg-white px-4 text-sm font-medium text-zinc-700 hover:bg-zinc-100"
                        >
                            Restore Default Filters
                        </button>
                    </div>
                </section>

                {error && <div className="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}

                <div className="overflow-x-auto rounded-lg border border-zinc-200 bg-white">
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
            </div>
        </AppLayout>
    );
};

export default Index;
