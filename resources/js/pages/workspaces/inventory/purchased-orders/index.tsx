import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Plus } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import AlertError from '@/components/alert-error';
import PageHeader from '@/components/common/PageHeader';
import { AddItemDialog } from '@/components/inventory/add-item-dialog';
import { AddItemForm } from '@/components/inventory/add-item-dialog';
import {
    ADD_ITEM_FORM_INITIAL,
    DATE_DROPDOWN_TRIGGER_CLASS,
    DROPDOWN_OPTION_BASE_CLASS,
    DROPDOWN_PANEL_CLASS,
    MONO_FONT,
    MONTH_OPTIONS,
    SANS_FONT,
    STATUS_OPTIONS,
    formatDisplayDate,
    formatIssueDate,
    formatMoney,
    fromInputDate,
    statusBadgeClass,
    statusLabel,
    statusOptionTextClass,
    toInputDate,
} from '@/components/inventory/purchased-orders-config';
import { PurchasedOrdersPagination } from '@/components/inventory/purchased-orders-pagination';
import { DeleteItemDialog } from '@/components/inventory/delete-item-dialog';
import { PurchasedOrdersFilterBar } from '@/components/inventory/purchased-orders-filter-bar';
import { PurchasedOrdersTable } from '@/components/inventory/purchased-orders-table';
import { PaginatedResponse, PurchasedOrder, StatusId } from '@/components/inventory/purchased-orders-types';
import { Product } from '@/types/models/Product';

interface Props {
    workspace: Workspace;
}

const Index = ({ workspace }: Props) => {
    const [rows, setRows] = useState<PurchasedOrder[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [currentPage, setCurrentPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [totalRows, setTotalRows] = useState(0);
    const [perPage, setPerPage] = useState(15);
    const [availableYears, setAvailableYears] = useState<number[]>([]);

    const [statusFilter, setStatusFilter] = useState<string>('all');
    const [query, setQuery] = useState('');
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');
    const requestSerialRef = useRef(0);
    const [addItemOpen, setAddItemOpen] = useState(false);
    const [editingRow, setEditingRow] = useState<PurchasedOrder | null>(null);
    const [addItemDatePickerOpen, setAddItemDatePickerOpen] = useState(false);
    const [addItemMonthListOpen, setAddItemMonthListOpen] = useState(false);
    const [addItemYearListOpen, setAddItemYearListOpen] = useState(false);
    const [addItemCalendarMonth, setAddItemCalendarMonth] = useState<Date>(new Date());
    const [addItemSubmitting, setAddItemSubmitting] = useState(false);
    const [deleteModalOpen, setDeleteModalOpen] = useState(false);
    const [deleteSubmitting, setDeleteSubmitting] = useState(false);
    const [rowToDelete, setRowToDelete] = useState<PurchasedOrder | null>(null);
    const [addItemForm, setAddItemForm] = useState<AddItemForm>(ADD_ITEM_FORM_INITIAL);
    const [addItemFieldErrors, setAddItemFieldErrors] = useState<Record<string, string>>({});
    const [products, setProducts] = useState<Product[]>([]);
    const addItemMonthDropdownRef = useRef<HTMLDivElement | null>(null);
    const addItemYearDropdownRef = useRef<HTMLDivElement | null>(null);
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

    const fetchProducts = useCallback(async () => {
        try {
            const params = new URLSearchParams({ per_page: '200', sort: 'name' });
            const url = `${apiBase}/api/workspaces/${workspace.slug}/products?${params.toString()}`;
            const res = await fetch(url, {
                credentials: 'include',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const text = await res.text();
            const json = text ? JSON.parse(text) : {};

            if (!res.ok) {
                throw new Error(json.message || 'Failed to load products.');
            }

            const payload = Array.isArray(json.data) ? json.data : [];
            setProducts(payload as Product[]);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to load products.');
        }
    }, [apiBase, workspace.slug]);

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
            params.set('per_page', String(perPage));

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
            const payloadPerPage = Number((payload as { per_page?: number }).per_page || perPage || 15);
            const payloadTotal = Number((payload as { total?: number }).total ?? nextRows.length);
            const payloadLastPage = Number((payload as { last_page?: number }).last_page || 0);
            const computedLastPage = Math.max(1, payloadLastPage || Math.ceil(payloadTotal / payloadPerPage) || 1);
            const requestedPage = Number(payload.current_page || page || 1) || 1;
            const clampedPage = Math.min(Math.max(requestedPage, 1), computedLastPage);

            setPerPage(payloadPerPage);
            setCurrentPage(clampedPage);
            setLastPage(computedLastPage);
            setTotalRows(payloadTotal);
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
        void fetchProducts();
    }, [fetchProducts]);

    useEffect(() => {
        if (!addItemDatePickerOpen) {
            setAddItemMonthListOpen(false);
            setAddItemYearListOpen(false);
            return;
        }

        const onDocumentMouseDown = (event: MouseEvent) => {
            const target = event.target as Node;

            if (addItemMonthDropdownRef.current && !addItemMonthDropdownRef.current.contains(target)) {
                setAddItemMonthListOpen(false);
            }

            if (addItemYearDropdownRef.current && !addItemYearDropdownRef.current.contains(target)) {
                setAddItemYearListOpen(false);
            }
        };

        document.addEventListener('mousedown', onDocumentMouseDown);

        return () => {
            document.removeEventListener('mousedown', onDocumentMouseDown);
        };
    }, [addItemDatePickerOpen]);

    const calendarFromYear = availableYears.length > 0 ? Math.min(...availableYears) : 2000;
    const calendarToYear = Math.min(availableYears.length > 0 ? Math.max(...availableYears) : new Date().getFullYear(), new Date().getFullYear());
    const currentYear = new Date().getFullYear();
    const currentMonthIndex = new Date().getMonth();
    const maxCalendarMonth = useMemo(() => new Date(currentYear, currentMonthIndex, 1), [currentYear, currentMonthIndex]);
    const selectableYears = (availableYears.length > 0
        ? availableYears.filter((year) => year <= currentYear)
        : Array.from({ length: currentYear - 1999 }, (_, i) => 2000 + i)
    ).sort((a, b) => a - b);
    const hasPrevious = currentPage > 1;
    const hasNext = currentPage < lastPage;
    const fromRow = totalRows === 0 ? 0 : Math.min((currentPage - 1) * perPage + 1, totalRows);
    const toRow = totalRows === 0 ? 0 : Math.min(currentPage * perPage, totalRows);
    const forceDebugEmptyState = useMemo(() => {
        if (typeof window === 'undefined') return false;
        const params = new URLSearchParams(window.location.search);

        return params.get('debugEmpty') === '1';
    }, []);
    const hasActiveFilters = query.trim().length > 0 || statusFilter !== 'all' || Boolean(startDate) || Boolean(endDate);
    const isEffectivelyEmpty = forceDebugEmptyState || rows.length === 0;
    const showEmptyState = !loading && isEffectivelyEmpty;
    const useFilteredEmptyCopy = hasActiveFilters;
    const emptyStateTitle = useFilteredEmptyCopy ? 'No PO Records found' : 'No records yet';
    const emptyStateDescription = useFilteredEmptyCopy
        ? 'Try adjusting your search or selected period'
        : 'There are no orders available yet. New records will appear here once created.';
    const emptyStateButtonLabel = useFilteredEmptyCopy ? 'Add Item' : 'Create Item';

    const paginationPages = useMemo(() => {
        const safeLast = Math.max(1, lastPage || 1);
        const safeCurrent = Math.min(Math.max(currentPage, 1), safeLast);

        if (safeLast <= 5) {
            return Array.from({ length: safeLast }, (_, i) => i + 1);
        }

        const start = Math.max(1, safeCurrent - 2);
        const end = Math.min(safeLast, start + 4);
        const adjustedStart = Math.max(1, end - 4);

        return Array.from({ length: end - adjustedStart + 1 }, (_, i) => adjustedStart + i);
    }, [currentPage, lastPage]);

    const setAddItemCalendarMonthByYear = (year: number) => {
        const nextMonth = year === currentYear
            ? Math.min(addItemCalendarMonth.getMonth(), currentMonthIndex)
            : addItemCalendarMonth.getMonth();

        setAddItemCalendarMonth(new Date(year, nextMonth, 1));
    };

    const itemOptions = useMemo(() => {
        const names = new Set<string>();

        products.forEach((product) => {
            const label = (product.name || product.title || '').trim();
            if (label) names.add(label);
        });

        const currentValue = addItemForm.item.trim();
        if (currentValue && !names.has(currentValue)) names.add(currentValue);

        return Array.from(names).sort((a, b) => a.localeCompare(b));
    }, [products, addItemForm.item]);

    const isAddItemFormComplete = useMemo(() => {
        return Boolean(
            addItemForm.issue_date &&
                addItemForm.delivery_no.trim() &&
                addItemForm.cust_po_no.trim() &&
                addItemForm.control_no.trim() &&
                addItemForm.item.trim() &&
                addItemForm.cog_amount !== '' &&
                addItemForm.delivery_fee !== '' &&
                addItemForm.total_amount !== '' &&
                addItemForm.status.trim(),
        );
    }, [addItemForm]);

    const parseAmount = (value: string): number => {
        const parsed = Number.parseFloat(value);
        return Number.isFinite(parsed) ? parsed : 0;
    };

    useEffect(() => {
        const cog = parseAmount(addItemForm.cog_amount);
        const fee = parseAmount(addItemForm.delivery_fee);
        const shouldDisplay = addItemForm.cog_amount !== '' || addItemForm.delivery_fee !== '';
        const nextTotal = shouldDisplay ? (cog + fee).toFixed(2) : '';

        if (addItemForm.total_amount !== nextTotal) {
            setAddItemForm((prev) => ({ ...prev, total_amount: nextTotal }));
        }
    }, [addItemForm.cog_amount, addItemForm.delivery_fee, addItemForm.total_amount, setAddItemForm]);

    const statusFilterLabel = statusFilter === 'all'
        ? 'All Status'
        : statusLabel(statusFilter);

    const handleAddItemCalendarMonthChange = (next: Date) => {
        const normalized = new Date(next.getFullYear(), next.getMonth(), 1);

        if (normalized > maxCalendarMonth) {
            return;
        }

        setAddItemCalendarMonth(normalized);
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
            const simpleErrors: Record<string, string> = {};
            if (!addItemForm.issue_date) simpleErrors.issue_date = 'Transaction date is required.';
            if (!addItemForm.delivery_no.trim()) simpleErrors.delivery_no = 'Delivery number is required.';
            if (!addItemForm.cust_po_no.trim()) simpleErrors.cust_po_no = 'Customer number is required.';
            if (!addItemForm.control_no.trim()) simpleErrors.control_no = 'Control number is required.';
            if (!addItemForm.item.trim()) simpleErrors.item = 'Item is required.';
            if (addItemForm.cog_amount === '') simpleErrors.cog_amount = 'COG amount is required.';
            if (addItemForm.delivery_fee === '') simpleErrors.delivery_fee = 'Delivery fee is required.';
            if (Object.keys(simpleErrors).length) {
                setAddItemFieldErrors(simpleErrors);
                return;
            }

            const numericCog = parseAmount(addItemForm.cog_amount);
            const numericDelivery = parseAmount(addItemForm.delivery_fee);
            const numericTotal = numericCog + numericDelivery;

            const isEdit = Boolean(editingRow);
            const url = isEdit
                ? `${apiBase}/api/workspaces/${workspace.slug}/inventory/purchased-orders/${editingRow?.id}`
                : `${apiBase}/api/workspaces/${workspace.slug}/inventory/purchased-orders`;
            const res = await fetch(url, {
                method: isEdit ? 'PUT' : 'POST',
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
                    cog_amount: numericCog,
                    delivery_fee: numericDelivery,
                    total_amount: numericTotal,
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
            setEditingRow(null);
            setAddItemForm(ADD_ITEM_FORM_INITIAL);
            void fetchRows(isEdit ? currentPage : 1);
        } catch (err) {
            setError(err instanceof Error ? err.message : isEdit ? 'Failed to update purchased order.' : 'Failed to create purchased order.');
        } finally {
            setAddItemSubmitting(false);
        }
    };

    useEffect(() => {
        if (!addItemOpen) return;

        const selected = fromInputDate(addItemForm.issue_date);
        if (selected) {
            setAddItemCalendarMonth(new Date(selected.getFullYear(), selected.getMonth(), 1));
            return;
        }

        setAddItemCalendarMonth(maxCalendarMonth);
    }, [addItemOpen, addItemForm.issue_date, maxCalendarMonth]);

    const confirmDelete = async () => {
        if (!rowToDelete) return;

        setDeleteSubmitting(true);
        setError(null);

        try {
            const url = `${apiBase}/api/workspaces/${workspace.slug}/inventory/purchased-orders/${rowToDelete.id}`;
            const res = await fetch(url, {
                method: 'DELETE',
                credentials: 'include',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
            });

            const text = await res.text();
            const json = text ? JSON.parse(text) : {};

            if (!res.ok) {
                throw new Error(json.message || 'Failed to delete purchased order.');
            }

            setDeleteModalOpen(false);
            setRowToDelete(null);
            void fetchRows(currentPage);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to delete purchased order.');
        } finally {
            setDeleteSubmitting(false);
        }
    };

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Inventory Purchased Orders`} />

            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6" style={{ fontFamily: SANS_FONT }}>
                <PageHeader
                    title="Purchased Orders"
                    description="Track incoming purchase orders, costs, and statuses."
                >
                    <button
                        onClick={() => {
                            setAddItemFieldErrors({});
                            setAddItemForm(ADD_ITEM_FORM_INITIAL);
                            setAddItemOpen(true);
                        }}
                        className="flex h-8 items-center rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                    >
                        <Plus className="mr-1.5 h-3.5 w-3.5" />
                        Add Order
                    </button>
                </PageHeader>

                <PurchasedOrdersFilterBar
                    startDate={startDate}
                    endDate={endDate}
                    onDateChange={(nextStart, nextEnd) => {
                        setStartDate(nextStart);
                        setEndDate(nextEnd);
                    }}
                    query={query}
                    setQuery={setQuery}
                    statusFilterLabel={statusFilterLabel}
                    statusOptions={STATUS_OPTIONS}
                    statusOptionTextClass={statusOptionTextClass}
                    setStatusFilter={setStatusFilter}
                    onLoad={() => void fetchRows(1)}
                    loading={loading}
                />

                {error && <AlertError errors={[error]} title="PO Management Request Failed" />}

                <AddItemDialog
                    open={addItemOpen}
                    onOpenChange={(open) => {
                        setAddItemOpen(open);
                        if (!open) setEditingRow(null);
                    }}
                    mode={editingRow ? 'edit' : 'add'}
                    addItemForm={addItemForm}
                    setAddItemForm={setAddItemForm}
                    itemOptions={itemOptions}
                    isFormComplete={isAddItemFormComplete}
                    addItemFieldErrors={addItemFieldErrors}
                    submitting={addItemSubmitting}
                    onSubmit={() => void submitAddItem()}
                    addItemDatePickerOpen={addItemDatePickerOpen}
                    setAddItemDatePickerOpen={setAddItemDatePickerOpen}
                    addItemCalendarMonth={addItemCalendarMonth}
                    setAddItemCalendarMonth={setAddItemCalendarMonth}
                    handleAddItemCalendarMonthChange={handleAddItemCalendarMonthChange}
                    calendarFromYear={calendarFromYear}
                    calendarToYear={calendarToYear}
                    maxCalendarMonth={maxCalendarMonth}
                    selectableYears={selectableYears}
                    currentYear={currentYear}
                    currentMonthIndex={currentMonthIndex}
                    addItemMonthListOpen={addItemMonthListOpen}
                    setAddItemMonthListOpen={setAddItemMonthListOpen}
                    addItemYearListOpen={addItemYearListOpen}
                    setAddItemYearListOpen={setAddItemYearListOpen}
                    addItemMonthDropdownRef={addItemMonthDropdownRef}
                    addItemYearDropdownRef={addItemYearDropdownRef}
                    setAddItemCalendarMonthByYear={setAddItemCalendarMonthByYear}
                    dateDropdownTriggerClass={DATE_DROPDOWN_TRIGGER_CLASS}
                    dropdownPanelClass={DROPDOWN_PANEL_CLASS}
                    dropdownOptionBaseClass={DROPDOWN_OPTION_BASE_CLASS}
                    monthOptions={MONTH_OPTIONS}
                    toInputDate={toInputDate}
                    fromInputDate={fromInputDate}
                    formatDisplayDate={formatDisplayDate}
                    maxSelectableDate={maxSelectableDate}
                    statusOptions={STATUS_OPTIONS}
                    statusBadgeClass={statusBadgeClass}
                    statusLabel={statusLabel}
                    statusOptionTextClass={statusOptionTextClass}
                    monoFont={MONO_FONT}
                    sansFont={SANS_FONT}
                />

                <DeleteItemDialog
                    open={deleteModalOpen}
                    onOpenChange={(open) => {
                        setDeleteModalOpen(open);
                        if (!open) setRowToDelete(null);
                    }}
                    rowToDelete={rowToDelete}
                    deleteSubmitting={deleteSubmitting}
                    onConfirmDelete={() => void confirmDelete()}
                    onCancel={() => {
                        setDeleteModalOpen(false);
                        setRowToDelete(null);
                    }}
                    sansFont={SANS_FONT}
                />

                <PurchasedOrdersTable
                    loading={loading}
                    showEmptyState={showEmptyState}
                    emptyStateTitle={emptyStateTitle}
                    emptyStateDescription={emptyStateDescription}
                    emptyStateButtonLabel={emptyStateButtonLabel}
                    onOpenAddItem={() => {
                        setAddItemFieldErrors({});
                        setAddItemForm(ADD_ITEM_FORM_INITIAL);
                        setEditingRow(null);
                        setAddItemOpen(true);
                    }}
                    rows={rows}
                    monoFont={MONO_FONT}
                    formatIssueDate={formatIssueDate}
                    formatMoney={formatMoney}
                    statusBadgeClass={statusBadgeClass}
                    statusLabel={statusLabel}
                    statusOptions={STATUS_OPTIONS}
                    statusOptionTextClass={statusOptionTextClass}
                    onUpdateStatus={(id, status) => void updateStatus(id, status)}
                    onOpenEdit={(row) => {
                        setAddItemFieldErrors({});
                        setEditingRow(row);
                        setAddItemForm({
                            issue_date: row.issue_date || '',
                            delivery_no: row.delivery_no || '',
                            cust_po_no: row.cust_po_no || '',
                            control_no: row.control_no || '',
                            item: row.item || '',
                            cog_amount: row.cog_amount != null ? String(row.cog_amount) : '',
                            delivery_fee: row.delivery_fee != null ? String(row.delivery_fee) : '',
                            total_amount: row.total_amount != null ? String(row.total_amount) : '',
                            status: String(row.status ?? 1),
                        });
                        setAddItemOpen(true);
                    }}
                    onOpenDelete={(row) => {
                        setRowToDelete(row);
                        setDeleteModalOpen(true);
                    }}
                />

                <PurchasedOrdersPagination
                    loading={loading}
                    hasPrevious={hasPrevious}
                    hasNext={hasNext}
                    fromRow={fromRow}
                    toRow={toRow}
                    totalRows={totalRows}
                    currentPage={currentPage}
                    lastPage={lastPage}
                    paginationPages={paginationPages}
                    onFetchPage={(page) => void fetchRows(page)}
                />
            </div>
        </AppLayout>
    );
};

export default Index;