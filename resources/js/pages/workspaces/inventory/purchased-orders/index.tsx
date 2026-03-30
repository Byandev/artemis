import { Head } from '@inertiajs/react';
import { Calendar as CalendarIcon, ChevronDown, ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight, Filter, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { DateRange } from 'react-day-picker';
import { Dialog, DialogContent } from '@/components/ui/dialog';

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
    available_years?: number[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    workspace: Workspace;
}

interface AddItemForm {
    issue_date: string;
    delivery_no: string;
    cust_po_no: string;
    control_no: string;
    item: string;
    cog_amount: string;
    delivery_fee: string;
    total_amount: string;
    status: string;
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

const MONTH_OPTIONS = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

const DROPDOWN_PANEL_CLASS = 'absolute left-0 top-[calc(100%+6px)] z-50 rounded-xl border border-black/6 bg-white p-1.5 shadow-[0_8px_30px_rgba(0,0,0,0.08)] dark:border-white/10 dark:bg-zinc-900';
const DROPDOWN_OPTION_BASE_CLASS = 'w-full rounded-md px-2 py-1.5 text-left text-[11px] transition-colors hover:bg-black/3 dark:hover:bg-white/5';
const DATE_DROPDOWN_TRIGGER_CLASS = 'inline-flex h-9 w-full items-center justify-between rounded-[10px] border border-black/6 bg-white px-3 text-xs text-gray-500 outline-none transition-colors hover:bg-black/2 focus:border-emerald-600 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-300 dark:hover:bg-white/5';
const SANS_FONT = "'DM Sans', system-ui, sans-serif";
const MONO_FONT = "'DM Mono', monospace";
const ADD_ITEM_FORM_INITIAL: AddItemForm = {
    issue_date: '',
    delivery_no: '',
    cust_po_no: '',
    control_no: '',
    item: '',
    cog_amount: '',
    delivery_fee: '',
    total_amount: '',
    status: '1',
};

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
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const fromInputDate = (value: string): Date | undefined => {
    if (!value) return undefined;
    const [year, month, day] = value.split('-').map(Number);
    if (!year || !month || !day) return undefined;
    return new Date(year, month - 1, day);
};

const normalizeDateRange = (first: string, second: string): { start: string; end: string } => {
    if (first <= second) {
        return { start: first, end: second };
    }

    return { start: second, end: first };
};

const formatDisplayDate = (start: string, end?: string): string => {
    if (!start) return '';

    const formatOne = (value: string): string => {
        const [year, month, day] = value.split('-');
        if (!year || !month || !day) return value;
        return `${month}/${day}/${year}`;
    };

    if (!end || start === end) {
        return formatOne(start);
    }

    return `${formatOne(start)} - ${formatOne(end)}`;
};

const Index = ({ workspace }: Props) => {
    const [rows, setRows] = useState<PurchasedOrder[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [currentPage, setCurrentPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [totalRows, setTotalRows] = useState(0);
    const [availableYears, setAvailableYears] = useState<number[]>([]);

    const [statusFilter, setStatusFilter] = useState<string>('all');
    const [query, setQuery] = useState('');
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');
    const requestSerialRef = useRef(0);
    const [datePickerOpen, setDatePickerOpen] = useState(false);
    const [statusMenuRowId, setStatusMenuRowId] = useState<number | null>(null);
    const [addItemOpen, setAddItemOpen] = useState(false);
    const [addItemSubmitting, setAddItemSubmitting] = useState(false);
    const [addItemForm, setAddItemForm] = useState<AddItemForm>(ADD_ITEM_FORM_INITIAL);
    const [addItemFieldErrors, setAddItemFieldErrors] = useState<Record<string, string>>({});
    const [statusFilterMenuOpen, setStatusFilterMenuOpen] = useState(false);
    const [monthListOpen, setMonthListOpen] = useState(false);
    const [yearListOpen, setYearListOpen] = useState(false);
    const [calendarMonth, setCalendarMonth] = useState<Date>(new Date());
    const monthDropdownRef = useRef<HTMLDivElement | null>(null);
    const yearDropdownRef = useRef<HTMLDivElement | null>(null);
    const maxSelectableDate = toInputDate(new Date());

    useEffect(() => {
        if (typeof document === 'undefined') return;
        if (document.querySelector('link[data-artemis-fonts="true"]')) return;

        const fontLink = document.createElement('link');
        fontLink.rel = 'stylesheet';
        fontLink.href = 'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Mono:wght@400;500&display=swap';
        fontLink.setAttribute('data-artemis-fonts', 'true');
        document.head.appendChild(fontLink);
    }, []);

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
            if (startDate) {
                params.set('start_date', startDate);
            }
            if (endDate) {
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
            setAvailableYears(Array.isArray(payload.available_years) ? payload.available_years : []);
            setCurrentPage(Number(payload.current_page || page));
            setLastPage(Number(payload.last_page || page));
            setTotalRows(Number(payload.total || nextRows.length));
        } catch (err) {
            if (requestSerial !== requestSerialRef.current) {
                return;
            }

            setRows([]);
            setAvailableYears([]);
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

    useEffect(() => {
        if (!datePickerOpen) return;

        const selected = fromInputDate(endDate) || fromInputDate(startDate);
        if (!selected) return;

        setCalendarMonth(new Date(selected.getFullYear(), selected.getMonth(), 1));
    }, [datePickerOpen, startDate, endDate]);

    useEffect(() => {
        if (!datePickerOpen) {
            setMonthListOpen(false);
            setYearListOpen(false);
            return;
        }

        const onDocumentMouseDown = (event: MouseEvent) => {
            const target = event.target as Node;

            if (monthDropdownRef.current && !monthDropdownRef.current.contains(target)) {
                setMonthListOpen(false);
            }

            if (yearDropdownRef.current && !yearDropdownRef.current.contains(target)) {
                setYearListOpen(false);
            }
        };

        document.addEventListener('mousedown', onDocumentMouseDown);

        return () => {
            document.removeEventListener('mousedown', onDocumentMouseDown);
        };
    }, [datePickerOpen]);

    const calendarFromYear = availableYears.length > 0 ? Math.min(...availableYears) : 2000;
    const calendarToYear = Math.min(availableYears.length > 0 ? Math.max(...availableYears) : new Date().getFullYear(), new Date().getFullYear());
    const currentYear = new Date().getFullYear();
    const currentMonthIndex = new Date().getMonth();
    const maxCalendarMonth = new Date(currentYear, currentMonthIndex, 1);
    const selectableYears = (availableYears.length > 0
        ? availableYears.filter((year) => year <= currentYear)
        : Array.from({ length: currentYear - 1999 }, (_, i) => 2000 + i)
    ).sort((a, b) => a - b);
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

    const setCalendarMonthByYear = (year: number) => {
        const nextMonth = year === currentYear
            ? Math.min(calendarMonth.getMonth(), currentMonthIndex)
            : calendarMonth.getMonth();

        setCalendarMonth(new Date(year, nextMonth, 1));
    };

    const statusFilterLabel = statusFilter === 'all'
        ? 'Status'
        : statusLabel(Number(statusFilter));

    const handleCalendarMonthChange = (next: Date) => {
        const normalized = new Date(next.getFullYear(), next.getMonth(), 1);

        if (normalized > maxCalendarMonth) {
            return;
        }

        setCalendarMonth(normalized);
    };

    const applyPickedDate = (picked: string) => {
        if (picked > maxSelectableDate) return;

        if (!startDate && !endDate) {
            setStartDate(picked);
            setEndDate(picked);
            return;
        }

        if (startDate && endDate) {
            if (picked > endDate) {
                setStartDate(endDate);
                setEndDate(picked);
                return;
            }

            if (picked < startDate) {
                setStartDate(picked);
                setEndDate(startDate);
                return;
            }

            const normalized = normalizeDateRange(startDate, picked);
            setStartDate(normalized.start);
            setEndDate(normalized.end);
            return;
        }

        const existing = startDate || endDate;
        const normalized = normalizeDateRange(existing, picked);
        setStartDate(normalized.start);
        setEndDate(normalized.end);
    };

    const resetFilters = () => {
        setQuery('');
        setStatusFilter('all');
        setStartDate('');
        setEndDate('');
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

    const submitAddItem = async () => {
        setAddItemSubmitting(true);
        setAddItemFieldErrors({});
        setError(null);

        try {
            const url = `${apiBase}/api/workspaces/${workspace.slug}/inventory/purchased-orders`;
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    issue_date: addItemForm.issue_date,
                    delivery_no: addItemForm.delivery_no || null,
                    cust_po_no: addItemForm.cust_po_no || null,
                    control_no: addItemForm.control_no || null,
                    item: addItemForm.item,
                    cog_amount: addItemForm.cog_amount || 0,
                    delivery_fee: addItemForm.delivery_fee || 0,
                    total_amount: addItemForm.total_amount || 0,
                    status: Number(addItemForm.status),
                }),
            });

            const text = await res.text();
            const json = text ? JSON.parse(text) : {};

            if (!res.ok) {
                if (res.status === 422 && json.errors) {
                    const mapped: Record<string, string> = {};
                    Object.entries(json.errors as Record<string, string[]>).forEach(([key, value]) => {
                        mapped[key] = Array.isArray(value) ? value[0] : String(value);
                    });
                    setAddItemFieldErrors(mapped);
                    return;
                }

                throw new Error(json.message || 'Failed to create purchased order.');
            }

            setAddItemOpen(false);
            setAddItemForm(ADD_ITEM_FORM_INITIAL);
            void fetchRows(1);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to create purchased order.');
        } finally {
            setAddItemSubmitting(false);
        }
    };

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Inventory Purchased Orders`} />

            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6" style={{ fontFamily: SANS_FONT }}>
                <header className="mb-5">
                    <h1 className="text-[22px] font-semibold tracking-tight text-gray-900 dark:text-gray-100">PO Management</h1>
                </header>

                <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <button
                        type="button"
                        onClick={() => {
                            setAddItemFieldErrors({});
                            setAddItemForm(ADD_ITEM_FORM_INITIAL);
                            setAddItemOpen(true);
                        }}
                        className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-medium text-white transition-colors hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-400"
                    >
                        <Plus className="h-3.5 w-3.5" />
                        Add Item
                    </button>

                    <div className="flex flex-wrap items-center gap-2">
                        <Popover open={datePickerOpen} onOpenChange={setDatePickerOpen}>
                            <PopoverTrigger asChild>
                                <button
                                    type="button"
                                    className="inline-flex h-9 min-w-[150px] items-center justify-center gap-1.5 rounded-[10px] border border-black/6 bg-white px-3 text-xs text-gray-400 outline-none transition-colors hover:border-emerald-600 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-300"
                                >
                                    <CalendarIcon className="h-3.5 w-3.5" />
                                    <span style={{ fontFamily: MONO_FONT }}>{startDate ? formatDisplayDate(startDate, endDate) : 'Select Date'}</span>
                                </button>
                            </PopoverTrigger>
                            <PopoverContent
                                align="start"
                                className="w-auto rounded-xl border border-black/6 bg-white p-2 shadow-[0_8px_30px_rgba(0,0,0,0.08)] dark:border-white/10 dark:bg-zinc-900"
                            >
                                <Calendar
                                    mode="range"
                                    month={calendarMonth}
                                    onMonthChange={handleCalendarMonthChange}
                                    selected={{
                                        from: fromInputDate(startDate),
                                        to: fromInputDate(endDate),
                                    }}
                                    captionLayout="label"
                                    fromYear={calendarFromYear}
                                    toYear={calendarToYear}
                                    toMonth={maxCalendarMonth}
                                    components={{
                                        CaptionLabel: (props) => (
                                            <div className={props.className}>
                                                <div className="flex w-full items-center gap-2">
                                                    <div ref={monthDropdownRef} className="relative flex-1">
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                setMonthListOpen((prev) => !prev);
                                                                setYearListOpen(false);
                                                            }}
                                                            className={DATE_DROPDOWN_TRIGGER_CLASS}
                                                        >
                                                            <span>{MONTH_OPTIONS[calendarMonth.getMonth()]}</span>
                                                            <ChevronDown className="h-3.5 w-3.5 text-gray-400" />
                                                        </button>

                                                        {monthListOpen && (
                                                            <div className={`${DROPDOWN_PANEL_CLASS} w-40`}>
                                                                <ul className="space-y-0.5">
                                                                    {MONTH_OPTIONS.map((monthLabel, monthIndex) => {
                                                                        const isDisabled = calendarMonth.getFullYear() === currentYear && monthIndex > currentMonthIndex;

                                                                        return (
                                                                            <li key={monthLabel}>
                                                                                <button
                                                                                    type="button"
                                                                                    disabled={isDisabled}
                                                                                    onClick={() => {
                                                                                        setMonthListOpen(false);
                                                                                        setCalendarMonth(new Date(calendarMonth.getFullYear(), monthIndex, 1));
                                                                                    }}
                                                                                    className={`${DROPDOWN_OPTION_BASE_CLASS} text-gray-500 disabled:cursor-not-allowed disabled:opacity-40 dark:text-gray-300`}
                                                                                >
                                                                                    {monthLabel}
                                                                                </button>
                                                                            </li>
                                                                        );
                                                                    })}
                                                                </ul>
                                                            </div>
                                                        )}
                                                    </div>

                                                    <div ref={yearDropdownRef} className="relative min-w-24">
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                setYearListOpen((prev) => !prev);
                                                                setMonthListOpen(false);
                                                            }}
                                                            className={DATE_DROPDOWN_TRIGGER_CLASS}
                                                        >
                                                            <span>{calendarMonth.getFullYear()}</span>
                                                            <ChevronDown className="h-3.5 w-3.5 text-gray-400" />
                                                        </button>

                                                        {yearListOpen && (
                                                            <div className={`${DROPDOWN_PANEL_CLASS} w-28`}>
                                                                <ul className="max-h-56 space-y-0.5 overflow-auto">
                                                                    {selectableYears.map((year) => (
                                                                        <li key={year}>
                                                                            <button
                                                                                type="button"
                                                                                onClick={() => {
                                                                                    setYearListOpen(false);
                                                                                    setCalendarMonthByYear(year);
                                                                                }}
                                                                                className={`${DROPDOWN_OPTION_BASE_CLASS} text-gray-500 dark:text-gray-300`}
                                                                            >
                                                                                {year}
                                                                            </button>
                                                                        </li>
                                                                    ))}
                                                                </ul>
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        ),
                                    }}
                                    onSelect={(range: DateRange | undefined) => {
                                        if (!range?.from) {
                                            setStartDate('');
                                            setEndDate('');
                                        }
                                    }}
                                    onDayClick={(day) => {
                                        applyPickedDate(toInputDate(day));
                                    }}
                                    disabled={(date) => toInputDate(date) > maxSelectableDate}
                                    classNames={{
                                        month_caption: 'flex h-(--cell-size) w-full items-center px-(--cell-size)',
                                        caption_label: 'w-full',
                                        range_start: 'rounded-md bg-emerald-600 text-white dark:bg-emerald-500 dark:text-zinc-950',
                                        range_end: 'rounded-md bg-emerald-600 text-white dark:bg-emerald-500 dark:text-zinc-950',
                                        range_middle: 'bg-emerald-500/14 text-emerald-800 dark:bg-emerald-400/18 dark:text-emerald-200',
                                        today: 'rounded-md ring-1 ring-emerald-500/30 text-emerald-700 dark:ring-emerald-400/35 dark:text-emerald-300',
                                    }}
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

                        <Popover open={statusFilterMenuOpen} onOpenChange={setStatusFilterMenuOpen}>
                            <PopoverTrigger asChild>
                                <button
                                    type="button"
                                    className="inline-flex h-9 min-w-[120px] items-center justify-between gap-2 rounded-[10px] border border-black/6 bg-white px-2.5 text-xs text-gray-500 outline-none transition-colors hover:bg-black/2 focus:border-emerald-600 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-300 dark:hover:bg-white/5"
                                >
                                    <span className="inline-flex items-center gap-1.5">
                                        <Filter className="h-3.5 w-3.5 text-gray-400" />
                                        <span>{statusFilterLabel}</span>
                                    </span>
                                    <ChevronDown className="h-3.5 w-3.5 text-gray-400" />
                                </button>
                            </PopoverTrigger>
                            <PopoverContent
                                align="start"
                                sideOffset={6}
                                className="w-[170px] rounded-xl border border-black/6 bg-white p-1.5 shadow-[0_8px_30px_rgba(0,0,0,0.08)] dark:border-white/10 dark:bg-zinc-900"
                            >
                                <ul className="space-y-0.5">
                                    <li>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setStatusFilterMenuOpen(false);
                                                setStatusFilter('all');
                                            }}
                                            className={[DROPDOWN_OPTION_BASE_CLASS, 'text-gray-500 dark:text-gray-300'].join(' ')}
                                        >
                                            Status
                                        </button>
                                    </li>
                                    {STATUS_OPTIONS.map((opt) => (
                                        <li key={opt.value}>
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setStatusFilterMenuOpen(false);
                                                    setStatusFilter(String(opt.value));
                                                }}
                                                className={[DROPDOWN_OPTION_BASE_CLASS, statusOptionTextClass(opt.value)].join(' ')}
                                            >
                                                {opt.label}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            </PopoverContent>
                        </Popover>

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

                <Dialog open={addItemOpen} onOpenChange={setAddItemOpen}>
                    <DialogContent className="max-w-[660px] rounded-2xl border border-black/6 p-0 shadow-[0_8px_30px_rgba(0,0,0,0.08)] dark:border-white/10" style={{ fontFamily: SANS_FONT }}>
                        <div className="px-10 py-6">
                            <h2 className="text-[28px] font-semibold tracking-tight text-gray-900 dark:text-gray-100">ADD ITEM</h2>
                            <p className="mt-1 text-xs text-gray-400">Fill in the details below to record a new item.</p>

                                <div className="mt-4 border-t border-black/6 pt-4 dark:border-white/6">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="mb-1.5 block text-xs font-medium text-gray-500">Date</label>
                                        <input
                                            type="date"
                                            value={addItemForm.issue_date}
                                            onChange={(e) => setAddItemForm((prev) => ({ ...prev, issue_date: e.target.value }))}
                                            className="h-9 w-full rounded-lg border border-black/6 bg-white px-2.5 text-xs text-gray-700 outline-none focus:border-emerald-600 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-200"
                                        />
                                        {addItemFieldErrors.issue_date && <p className="mt-1 text-[10px] text-red-500">{addItemFieldErrors.issue_date}</p>}
                                    </div>

                                    <div>
                                        <label className="mb-1.5 block text-xs font-medium text-gray-500">Delivered No.</label>
                                        <input
                                            value={addItemForm.delivery_no}
                                            onChange={(e) => setAddItemForm((prev) => ({ ...prev, delivery_no: e.target.value }))}
                                            placeholder="e.g. DN-1001"
                                            className="h-9 w-full rounded-lg border border-black/6 bg-white px-2.5 text-xs text-gray-700 outline-none focus:border-emerald-600 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-200"
                                        />
                                        {addItemFieldErrors.delivery_no && <p className="mt-1 text-[10px] text-red-500">{addItemFieldErrors.delivery_no}</p>}
                                    </div>
                                </div>

                                <div className="mt-4 border-t border-black/6 pt-4 dark:border-white/6">
                                    <p className="mb-2 text-sm font-medium text-gray-600 dark:text-gray-300">Purchase Order</p>
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <label className="mb-1.5 block text-xs font-medium text-gray-500">Custom Po No.</label>
                                            <input
                                                value={addItemForm.cust_po_no}
                                                onChange={(e) => setAddItemForm((prev) => ({ ...prev, cust_po_no: e.target.value }))}
                                                className="h-9 w-full rounded-lg border border-black/6 bg-white px-2.5 text-xs text-gray-700 outline-none focus:border-emerald-600 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-200"
                                            />
                                            {addItemFieldErrors.cust_po_no && <p className="mt-1 text-[10px] text-red-500">{addItemFieldErrors.cust_po_no}</p>}
                                        </div>
                                        <div>
                                            <label className="mb-1.5 block text-xs font-medium text-gray-500">Control No.</label>
                                            <input
                                                value={addItemForm.control_no}
                                                onChange={(e) => setAddItemForm((prev) => ({ ...prev, control_no: e.target.value }))}
                                                className="h-9 w-full rounded-lg border border-black/6 bg-white px-2.5 text-xs text-gray-700 outline-none focus:border-emerald-600 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-200"
                                            />
                                            {addItemFieldErrors.control_no && <p className="mt-1 text-[10px] text-red-500">{addItemFieldErrors.control_no}</p>}
                                        </div>
                                    </div>
                                </div>

                                <div className="mt-4 border-t border-black/6 pt-4 dark:border-white/6">
                                    <p className="mb-2 text-sm font-medium text-gray-600 dark:text-gray-300">Item</p>
                                    <div>
                                        <label className="mb-1.5 block text-xs font-medium text-gray-500">Item Name</label>
                                        <input
                                            value={addItemForm.item}
                                            onChange={(e) => setAddItemForm((prev) => ({ ...prev, item: e.target.value }))}
                                            className="h-9 w-full rounded-lg border border-black/6 bg-white px-2.5 text-xs text-gray-700 outline-none focus:border-emerald-600 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-200"
                                        />
                                        {addItemFieldErrors.item && <p className="mt-1 text-[10px] text-red-500">{addItemFieldErrors.item}</p>}
                                    </div>

                                    <div className="mt-3 grid grid-cols-3 gap-4">
                                        <div>
                                            <label className="mb-1.5 block text-xs font-medium text-gray-500">COG Amount</label>
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={addItemForm.cog_amount}
                                                onChange={(e) => setAddItemForm((prev) => ({ ...prev, cog_amount: e.target.value }))}
                                                className="h-9 w-full rounded-lg border border-black/6 bg-white px-2.5 text-xs text-gray-700 outline-none focus:border-emerald-600 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-200"
                                            />
                                            {addItemFieldErrors.cog_amount && <p className="mt-1 text-[10px] text-red-500">{addItemFieldErrors.cog_amount}</p>}
                                        </div>
                                        <div>
                                            <label className="mb-1.5 block text-xs font-medium text-gray-500">Delivery Fee</label>
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={addItemForm.delivery_fee}
                                                onChange={(e) => setAddItemForm((prev) => ({ ...prev, delivery_fee: e.target.value }))}
                                                className="h-9 w-full rounded-lg border border-black/6 bg-white px-2.5 text-xs text-gray-700 outline-none focus:border-emerald-600 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-200"
                                            />
                                            {addItemFieldErrors.delivery_fee && <p className="mt-1 text-[10px] text-red-500">{addItemFieldErrors.delivery_fee}</p>}
                                        </div>
                                        <div>
                                            <label className="mb-1.5 block text-xs font-medium text-gray-500">Total Amount</label>
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={addItemForm.total_amount}
                                                onChange={(e) => setAddItemForm((prev) => ({ ...prev, total_amount: e.target.value }))}
                                                className="h-9 w-full rounded-lg border border-black/6 bg-white px-2.5 text-xs text-gray-700 outline-none focus:border-emerald-600 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-200"
                                            />
                                            {addItemFieldErrors.total_amount && <p className="mt-1 text-[10px] text-red-500">{addItemFieldErrors.total_amount}</p>}
                                        </div>
                                    </div>
                                </div>

                                <div className="mt-4 border-t border-black/6 pt-4 dark:border-white/6">
                                    <label className="mb-1.5 block text-xs font-medium text-gray-500">Status</label>
                                    <select
                                        value={addItemForm.status}
                                        onChange={(e) => setAddItemForm((prev) => ({ ...prev, status: e.target.value }))}
                                        className="h-9 w-[180px] appearance-none rounded-lg border border-black/6 bg-white px-2.5 text-xs text-gray-700 outline-none focus:border-emerald-600 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-200"
                                    >
                                        {STATUS_OPTIONS.map((opt) => (
                                            <option key={opt.value} value={String(opt.value)}>
                                                {opt.label}
                                            </option>
                                        ))}
                                    </select>
                                    {addItemFieldErrors.status && <p className="mt-1 text-[10px] text-red-500">{addItemFieldErrors.status}</p>}
                                </div>

                                <div className="mt-5 flex items-center justify-end gap-3 border-t border-black/6 pt-5 dark:border-white/6">
                                    <button
                                        type="button"
                                        onClick={() => setAddItemOpen(false)}
                                        className="h-9 rounded-lg border border-black/6 bg-white px-6 text-xs font-medium text-gray-600 transition-colors hover:bg-black/2 dark:border-white/6 dark:bg-zinc-900 dark:text-gray-300 dark:hover:bg-white/5"
                                    >
                                        CANCEL
                                    </button>
                                    <button
                                        type="button"
                                        disabled={addItemSubmitting}
                                        onClick={() => void submitAddItem()}
                                        className="h-9 rounded-lg bg-emerald-600 px-6 text-xs font-medium text-white transition-colors hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-emerald-500 dark:hover:bg-emerald-400"
                                    >
                                        {addItemSubmitting ? 'ADDING...' : 'ADD ITEM'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </DialogContent>
                </Dialog>

                <div className="relative overflow-visible">
                    <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                        <table className="min-w-full text-xs">
                        <thead className="bg-[#F7F7F5] dark:bg-zinc-800/80">
                            <tr className="text-center font-medium uppercase tracking-[0.06em] text-gray-400 dark:text-gray-500">
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
                                rows.map((row) => (
                                    <tr key={row.id} className="hover:bg-emerald-500/4 dark:hover:bg-emerald-500/8">
                                        <td className="whitespace-nowrap px-4 py-2.5 text-[11px]" style={{ fontFamily: MONO_FONT }}>{formatIssueDate(row.issue_date)}</td>
                                        <td className="whitespace-nowrap px-4 py-2.5 text-[11px] text-gray-700 dark:text-gray-200" style={{ fontFamily: MONO_FONT }}>{row.delivery_no || '-'}</td>
                                        <td className="whitespace-nowrap px-4 py-2.5 text-[11px] text-gray-400" style={{ fontFamily: MONO_FONT }}>{row.cust_po_no || '-'}</td>
                                        <td className="whitespace-nowrap px-4 py-2.5 text-[11px] text-gray-400" style={{ fontFamily: MONO_FONT }}>{row.control_no || '-'}</td>
                                        <td className="whitespace-nowrap px-4 py-2.5 font-medium uppercase text-gray-700 dark:text-gray-200">{row.item}</td>
                                        <td className="whitespace-nowrap px-4 py-2.5 text-[11px]" style={{ fontFamily: MONO_FONT }}>{formatMoney(row.cog_amount)}</td>
                                        <td className="whitespace-nowrap px-4 py-2.5 text-[11px]" style={{ fontFamily: MONO_FONT }}>{formatMoney(row.delivery_fee)}</td>
                                        <td className="whitespace-nowrap px-4 py-2.5 text-[11px] text-gray-700 dark:text-gray-200" style={{ fontFamily: MONO_FONT }}>{formatMoney(row.total_amount)}</td>
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
                                                                            DROPDOWN_OPTION_BASE_CLASS,
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
                                                    className="group relative inline-flex items-center px-1 text-gray-400 transition-colors hover:text-sky-600 dark:text-gray-500 dark:hover:text-sky-400"
                                                >
                                                    <span className="pointer-events-none absolute right-full mr-1.5 rounded-md bg-sky-500 px-2 py-0.5 text-[10px] font-semibold tracking-[0.02em] text-white opacity-0 transition-all group-hover:translate-x-0 group-hover:opacity-100 dark:bg-sky-500 dark:text-white">
                                                        EDIT
                                                    </span>
                                                    <Pencil className="h-3.5 w-3.5" />
                                                </button>
                                                <span className="mx-1 h-3.5 w-px bg-black/10 dark:bg-white/10" />
                                                <button
                                                    type="button"
                                                    className="group relative inline-flex items-center px-1 text-gray-400 transition-colors hover:text-red-500 dark:text-gray-500 dark:hover:text-red-400"
                                                >
                                                    <span className="pointer-events-none absolute left-full top-1/2 z-20 ml-1.5 -translate-y-1/2 rounded-md bg-red-500 px-2 py-0.5 text-[10px] font-semibold tracking-[0.02em] text-white opacity-0 transition-all group-hover:opacity-100 dark:bg-red-500 dark:text-white">
                                                        DELETE
                                                    </span>
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                        </table>
                    </div>
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
