import { Head, Link } from '@inertiajs/react';
import { Calendar as CalendarIcon, ChevronDown, ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight, Filter, Pencil, Plus, Search, SquareX } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

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
        maximumFractionDigits: 0,
    }).format(amount || 0);
};

const formatIssueDate = (value: string): string => {
    if (!value) return '';

    const isoMatch = value.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (isoMatch) {
        const [, year, month, day] = isoMatch;
        return `${month}/${day}/${year}`;
    }

    const slashMatch = value.match(/^(\d{2})\/(\d{2})\/(\d{4})/);
    if (slashMatch) {
        const [, month, day, year] = slashMatch;
        return `${month}/${day}/${year}`;
    }

    return value;
};

const statusBadgeClass = (status: StatusId): string => {
    switch (status) {
        case 7:
            return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
        case 6:
            return 'bg-sky-500/10 text-sky-600 dark:text-sky-400';
        case 4:
            return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
        case 8:
            return 'bg-red-500/10 text-red-600 dark:text-red-400';
        case 3:
            return 'bg-amber-500/10 text-amber-700 dark:text-amber-400';
        case 5:
            return 'bg-violet-500/10 text-violet-600 dark:text-violet-400';
        default:
            return 'bg-zinc-500/10 text-zinc-500 dark:text-zinc-400';
    }
};

const statusOptionTextClass = (status: StatusId): string => {
    switch (status) {
        case 7:
            return 'text-emerald-600 dark:text-emerald-400';
        case 6:
            return 'text-sky-600 dark:text-sky-400';
        case 5:
            return 'text-violet-600 dark:text-violet-400';
        case 3:
            return 'text-amber-700 dark:text-amber-400';
        case 8:
            return 'text-red-600 dark:text-red-400';
        case 4:
            return 'text-emerald-600 dark:text-emerald-400';
        default:
            return 'text-gray-500 dark:text-gray-300';
    }
};

const toInputDate = (date: Date): string => {
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 10);
};

const getDefaultDateRange = (): { startDate: string; endDate: string } => ({
    startDate: '',
    endDate: '',
});

const formatDisplayDate = (value: string): string => {
    if (!value) return '';
    const [year, month, day] = value.split('-');
    if (!year || !month || !day) return value;
    return `${month} - ${day} - ${year}`;
};

const Index = ({ workspace }: Props) => {
    const [rows, setRows] = useState<PurchasedOrder[]>([]);
    const [loading, setLoading] = useState(true);
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
    const [datePickerOpen, setDatePickerOpen] = useState(false);
    const [statusMenuRowId, setStatusMenuRowId] = useState<number | null>(null);
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

    const fetchRows = async (page = 1) => {
        const requestSerial = ++requestSerialRef.current;

        setLoading(true);
        setError(null);

        try {
            const params = new URLSearchParams();
            if (statusFilter !== 'all') params.set('status', statusFilter);
            if (query.trim()) params.set('q', query.trim());
            const hasDateFilter = Boolean(startDate && endDate);
            if (hasDateFilter) {
                params.set('start_date', startDate);
                params.set('end_date', endDate);
            }
            params.set('page', String(page));
            params.set('per_page', '15');

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

            setRows(nextRows);
            setCurrentPage(Number(payload.current_page || page));
            setLastPage(Number(payload.last_page || page));
            setTotalRows(Number(payload.total || nextRows.length));
        } catch (err) {
            if (requestSerial !== requestSerialRef.current) {
                return;
            }

            setRows([]);
            setCurrentPage(1);
            setLastPage(1);
            setTotalRows(0);
            setError(err instanceof Error ? err.message : 'Failed to fetch purchased orders.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        void fetchRows(1);
    }, [statusFilter, query, startDate, endDate]);

    const visibleRows = rows;
    const emptyRows = Math.max(0, 15 - visibleRows.length);
    const hasPrevious = currentPage > 1;
    const hasNext = currentPage < lastPage;
    const fromRow = totalRows === 0 ? 0 : (currentPage - 1) * 15 + 1;
    const toRow = totalRows === 0 ? 0 : Math.min(currentPage * 15, totalRows);

    const paginationPages = useMemo(() => {
        if (lastPage <= 5) {
            return Array.from({ length: lastPage }, (_, i) => i + 1);
        }

        const start = Math.max(1, currentPage - 2);
        const end = Math.min(lastPage, start + 4);
        const adjustedStart = Math.max(1, end - 4);

        return Array.from({ length: end - adjustedStart + 1 }, (_, i) => adjustedStart + i);
    }, [currentPage, lastPage]);

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
                <header className="mb-5">
                    <h1 className="text-[22px] font-semibold tracking-tight text-gray-900 dark:text-gray-100">PO Management</h1>
                </header>

                <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <Link
                        href={`/workspaces/${workspace.slug}/inventory/purchased-orders/create`}
                        className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-medium text-white transition-colors hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-400"
                    >
                        <Plus className="h-3.5 w-3.5" />
                        Add Item
                    </Link>

                    <div className="flex flex-wrap items-center gap-2">
                        <Popover open={datePickerOpen} onOpenChange={setDatePickerOpen}>
                            <PopoverTrigger asChild>
                                <button
                                    type="button"
                                    className="inline-flex h-9 min-w-[150px] items-center justify-center gap-1.5 rounded-[10px] border border-black/6 bg-white px-3 text-xs text-gray-400 outline-none transition-colors hover:border-emerald-600 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-300"
                                >
                                    <CalendarIcon className="h-3.5 w-3.5" />
                                    <span className="font-mono">{endDate ? formatDisplayDate(endDate) : 'Select Date'}</span>
                                </button>
                            </PopoverTrigger>
                            <PopoverContent
                                align="start"
                                className="w-auto rounded-xl border border-black/6 bg-white p-2 shadow-[0_8px_30px_rgba(0,0,0,0.08)] dark:border-white/10 dark:bg-zinc-900"
                            >
                                <Calendar
                                    mode="single"
                                    selected={endDate ? new Date(endDate) : undefined}
                                    onSelect={(date) => {
                                        if (!date) return;

                                        const nextValue = toInputDate(date) > maxSelectableDate ? maxSelectableDate : toInputDate(date);
                                        setEndDate(nextValue);
                                        setStartDate(nextValue);
                                        setDatePickerOpen(false);
                                    }}
                                    disabled={(date) => toInputDate(date) > maxSelectableDate}
                                    className="rounded-[10px] bg-white p-0 text-xs dark:bg-zinc-900"
                                />
                            </PopoverContent>
                        </Popover>

                        <div className="relative min-w-[220px]">
                            <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                            <input
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Search Orders.."
                                className="h-9 w-full rounded-[10px] border border-black/6 bg-white pl-8 pr-3 text-xs text-gray-500 outline-none transition-colors focus:border-emerald-600 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-300"
                            />
                        </div>

                        <div className="relative min-w-[120px]">
                            <Filter className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                            <select
                                value={statusFilter}
                                onChange={(e) => setStatusFilter(e.target.value)}
                                className="h-9 w-full appearance-none rounded-[10px] border border-black/6 bg-white pl-8 pr-7 text-xs text-gray-500 outline-none transition-colors focus:border-emerald-600 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-300"
                            >
                                <option value="all">Status</option>
                                {STATUS_OPTIONS.map((opt) => (
                                    <option key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </option>
                                ))}
                            </select>
                            <ChevronDown className="pointer-events-none absolute right-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                        </div>

                        <button
                            type="button"
                            onClick={resetFilters}
                            className="h-9 rounded-[10px] border border-black/6 bg-white px-3 text-xs font-medium text-gray-500 transition-colors hover:bg-black/2 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-300"
                        >
                            Restore
                        </button>
                    </div>
                </div>

                {error && <div className="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}

                <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <table className="min-w-full text-xs">
                        <thead className="bg-[#F7F7F5] dark:bg-zinc-800/80">
                            <tr className="text-left font-medium uppercase tracking-[0.06em] text-gray-400 dark:text-gray-500">
                                <th className="px-4 py-3">Issue Date</th>
                                <th className="px-4 py-3">Delivered No.</th>
                                <th className="px-4 py-3">Cust PO No.</th>
                                <th className="px-4 py-3">Control No.</th>
                                <th className="px-4 py-3">Item</th>
                                <th className="px-4 py-3">COG Amount</th>
                                <th className="px-4 py-3">Delivery Fee</th>
                                <th className="px-4 py-3">Total Amount</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-black/6 bg-white text-gray-500 dark:divide-white/6 dark:bg-zinc-900 dark:text-gray-300">
                            {loading ? (
                                <tr>
                                    <td colSpan={10} className="px-3 py-10 text-center text-zinc-500">
                                        Loading...
                                    </td>
                                </tr>
                            ) : rows.length === 0 ? (
                                <tr>
                                    <td colSpan={10} className="px-3 py-10 text-center text-zinc-500">
                                        No records found.
                                    </td>
                                </tr>
                            ) : (
                                <>
                                    {visibleRows.map((row) => (
                                        <tr key={row.id} className="hover:bg-emerald-500/4 dark:hover:bg-emerald-500/8">
                                            <td className="whitespace-nowrap px-4 py-2.5 font-mono text-[11px]">{formatIssueDate(row.issue_date)}</td>
                                            <td className="whitespace-nowrap px-4 py-2.5 font-mono text-[11px] text-gray-700 dark:text-gray-200">{row.delivery_no || '-'}</td>
                                            <td className="whitespace-nowrap px-4 py-2.5 font-mono text-[11px] text-gray-400">{row.cust_po_no || '-'}</td>
                                            <td className="whitespace-nowrap px-4 py-2.5 font-mono text-[11px] text-gray-400">{row.control_no || '-'}</td>
                                            <td className="whitespace-nowrap px-4 py-2.5 font-medium uppercase text-gray-700 dark:text-gray-200">{row.item}</td>
                                            <td className="whitespace-nowrap px-4 py-2.5 font-mono text-[11px]">{formatMoney(row.cog_amount)}</td>
                                            <td className="whitespace-nowrap px-4 py-2.5 font-mono text-[11px]">{formatMoney(row.delivery_fee)}</td>
                                            <td className="whitespace-nowrap px-4 py-2.5 font-mono text-[11px] text-gray-700 dark:text-gray-200">{formatMoney(row.total_amount)}</td>
                                            <td className="whitespace-nowrap px-4 py-2.5">
                                                <div className="inline-flex h-6 w-[172px] items-center rounded-2xl pl-1.5">
                                                    <span className={`inline-flex w-full items-center gap-1 rounded-2xl px-2 py-1 text-[11px] font-medium ${statusBadgeClass(row.status)}`}>
                                                        <span className="h-1.5 w-1.5 rounded-full bg-current/60" />
                                                        <span>{statusLabel(row.status)}</span>
                                                    </span>

                                                    <Popover
                                                        open={statusMenuRowId === row.id}
                                                        onOpenChange={(open) => setStatusMenuRowId(open ? row.id : null)}
                                                    >
                                                        <PopoverTrigger asChild>
                                                            <button
                                                                type="button"
                                                                className="ml-0.5 inline-flex h-6 w-5 items-center justify-center rounded-md text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-300"
                                                                aria-label={`Open status dropdown for ${row.delivery_no || row.item}`}
                                                            >
                                                                <ChevronDown className="h-3.5 w-3.5" />
                                                            </button>
                                                        </PopoverTrigger>
                                                        <PopoverContent
                                                            align="end"
                                                            sideOffset={6}
                                                            className="w-[140px] rounded-xl border border-black/6 bg-white p-1.5 shadow-[0_8px_30px_rgba(0,0,0,0.08)] dark:border-white/10 dark:bg-zinc-900"
                                                        >
                                                            <ul className="space-y-0.5">
                                                                {STATUS_OPTIONS.map((opt) => (
                                                                    <li key={opt.value}>
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => {
                                                                                setStatusMenuRowId(null);
                                                                                void updateStatus(row.id, opt.value);
                                                                            }}
                                                                            className={[
                                                                                'w-full rounded-md px-2 py-1.5 text-left text-[11px] transition-colors hover:bg-black/3 dark:hover:bg-white/5',
                                                                                statusOptionTextClass(opt.value),
                                                                            ].join(' ')}
                                                                        >
                                                                            {opt.label}
                                                                        </button>
                                                                    </li>
                                                                ))}
                                                            </ul>
                                                        </PopoverContent>
                                                    </Popover>
                                                </div>
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-2.5">
                                                <div className="inline-flex items-center text-[11px]">
                                                    <button
                                                        type="button"
                                                        className="inline-flex items-center px-1 text-sky-600 transition-colors hover:text-sky-700 dark:text-sky-400"
                                                    >
                                                        <Pencil className="h-3.5 w-3.5" />
                                                    </button>
                                                    <span className="mx-1 h-3.5 w-px bg-black/10 dark:bg-white/10" />
                                                    <button
                                                        type="button"
                                                        className="inline-flex items-center px-1 text-red-500 transition-colors hover:text-red-600 dark:text-red-400"
                                                    >
                                                        <SquareX className="h-3.5 w-3.5" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}

                                    {Array.from({ length: emptyRows }).map((_, index) => (
                                        <tr key={`empty-row-${index}`}>
                                            <td colSpan={10} className="h-[29px] px-4" />
                                        </tr>
                                    ))}
                                </>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="mt-3 grid grid-cols-3 items-center gap-3 px-1">
                    <p className="text-[11px] text-gray-400">Showing {fromRow} - {toRow} of {totalRows} results</p>

                    <div className="flex items-center justify-center gap-1 text-xs text-gray-500">
                        <button
                            type="button"
                            onClick={() => void fetchRows(1)}
                            disabled={loading || !hasPrevious}
                            className="rounded-md px-2 py-1.5 disabled:cursor-not-allowed disabled:opacity-40"
                            aria-label="First page"
                        >
                            <ChevronsLeft className="h-3.5 w-3.5" />
                        </button>
                        <button
                            type="button"
                            onClick={() => void fetchRows(currentPage - 1)}
                            disabled={loading || !hasPrevious}
                            className="rounded-md px-2 py-1.5 disabled:cursor-not-allowed disabled:opacity-40"
                            aria-label="Previous page"
                        >
                            <ChevronLeft className="h-3.5 w-3.5" />
                        </button>
                        {paginationPages.map((page) => (
                            <button
                                key={page}
                                type="button"
                                onClick={() => void fetchRows(page)}
                                disabled={loading}
                                className={[
                                    'inline-flex h-7 min-w-7 items-center justify-center rounded-md px-2 transition-colors',
                                    currentPage === page
                                        ? 'bg-emerald-600 text-white dark:bg-emerald-500'
                                        : 'text-gray-700 hover:bg-black/3 dark:text-gray-300 dark:hover:bg-white/5',
                                ].join(' ')}
                            >
                                {page}
                            </button>
                        ))}

                        <button
                            type="button"
                            onClick={() => void fetchRows(currentPage + 1)}
                            disabled={loading || !hasNext}
                            className="rounded-md px-2 py-1.5 disabled:cursor-not-allowed disabled:opacity-40"
                            aria-label="Next page"
                        >
                            <ChevronRight className="h-3.5 w-3.5" />
                        </button>
                        <button
                            type="button"
                            onClick={() => void fetchRows(lastPage)}
                            disabled={loading || !hasNext}
                            className="rounded-md px-2 py-1.5 disabled:cursor-not-allowed disabled:opacity-40"
                            aria-label="Last page"
                        >
                            <ChevronsRight className="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <div />
                </div>
            </div>
        </AppLayout>
    );
};

export default Index;
