import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Plus } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import AlertError from '@/components/alert-error';
import PageHeader from '@/components/common/PageHeader';
import { AddItemDialog, AddItemForm } from '@/components/inventory/add-item-dialog';
import { PurchasedOrdersPagination } from '@/components/inventory/purchased-orders-pagination';
import { DeleteItemDialog } from '@/components/inventory/delete-item-dialog';
import { PurchasedOrdersFilterBar } from '@/components/inventory/purchased-orders-filter-bar';
import { PurchasedOrdersTable } from '@/components/inventory/purchased-orders-table';
import { PaginatedResponse, PurchasedOrder, StatusId } from '@/types/models/PurchasedOrder';
import { Product } from '@/types/models/Product';
import { request } from '@/utils/http';
import {
    buildEmptyState,
    buildItemOptions,
    computeStatusFilterLabel,
    computeTotals,
    parseAmount,
} from '@/utils/purchased-orders';
import {
    ADD_ITEM_FORM_INITIAL,
    MONO_FONT,
    SANS_FONT,
    STATUS_OPTIONS,
    formatIssueDate,
    formatMoney,
    normalizeStatusLabel,
    statusBadgeClass,
    statusLabel,
    statusOptionTextClass,
} from '@/components/inventory/purchased-orders-config';

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

    const [statusFilter, setStatusFilter] = useState<string>('all');
    const [query, setQuery] = useState('');
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');
    const requestSerialRef = useRef(0);
    const [addItemOpen, setAddItemOpen] = useState(false);
    const [editingRow, setEditingRow] = useState<PurchasedOrder | null>(null);
    const [addItemSubmitting, setAddItemSubmitting] = useState(false);
    const [deleteModalOpen, setDeleteModalOpen] = useState(false);
    const [deleteSubmitting, setDeleteSubmitting] = useState(false);
    const [rowToDelete, setRowToDelete] = useState<PurchasedOrder | null>(null);
    const [addItemForm, setAddItemForm] = useState<AddItemForm>(ADD_ITEM_FORM_INITIAL);
    const [addItemFieldErrors, setAddItemFieldErrors] = useState<Record<string, string>>({});
    const [products, setProducts] = useState<Product[]>([]);

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

    const doRequest = useCallback((method: string, url: string, body?: unknown) => {
        return request(method, url, { body, csrfToken });
    }, [csrfToken]);

    const fetchProducts = useCallback(async () => {
        try {
            const params = new URLSearchParams({ per_page: '200', sort: 'name' });
            const url = `${apiBase}/api/workspaces/${workspace.slug}/products?${params.toString()}`;
            const { res, json } = await doRequest('GET', url);
            if (!res.ok) throw new Error((json as { message?: string }).message || 'Failed to load products.');
            const payload = Array.isArray((json as { data?: unknown }).data) ? (json as { data: Product[] }).data : [];
            setProducts(payload as Product[]);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to load products.');
        }
    }, [apiBase, doRequest, workspace.slug]);

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
            const { res, json } = await doRequest('GET', url);
            if (!res.ok) throw new Error((json as { message?: string }).message || 'Failed to fetch purchased orders.');

            const payload = json as PaginatedResponse;
            const nextRows = Array.isArray(payload.data) ? payload.data : [];

            if (requestSerial !== requestSerialRef.current) {
                return;
            }

            const normalizedRows = nextRows.map((row) => ({
                ...row,
                status: normalizeStatusLabel(row.status),
            }));

            setRows(normalizedRows);
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
            setCurrentPage(1);
            setLastPage(1);
            setTotalRows(0);
            setError(err instanceof Error ? err.message : 'Failed to fetch purchased orders.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { void fetchRows(1); }, [statusFilter, query, startDate, endDate, doRequest]);
    useEffect(() => { void fetchProducts(); }, [fetchProducts]);

    const forceDebugEmptyState = useMemo(() => {
        if (typeof window === 'undefined') return false;
        const params = new URLSearchParams(window.location.search);

        return params.get('debugEmpty') === '1';
    }, []);
    const hasActiveFilters = query.trim().length > 0 || statusFilter !== 'all' || Boolean(startDate) || Boolean(endDate);
    const fromRow = useMemo(
        () => (totalRows === 0 ? 0 : Math.min((currentPage - 1) * perPage + 1, totalRows)),
        [currentPage, perPage, totalRows],
    );
    const toRow = useMemo(
        () => (totalRows === 0 ? 0 : Math.min(currentPage * perPage, totalRows)),
        [currentPage, perPage, totalRows],
    );

    const { showEmptyState, emptyStateTitle, emptyStateDescription, emptyStateButtonLabel } = useMemo(() => (
        buildEmptyState(rows.length, loading, hasActiveFilters, forceDebugEmptyState)
    ), [rows.length, loading, hasActiveFilters, forceDebugEmptyState]);

    const itemOptions = useMemo(() => buildItemOptions(products, addItemForm.item), [products, addItemForm.item]);

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

    useEffect(() => {
        const { displayTotal } = computeTotals(addItemForm.cog_amount, addItemForm.delivery_fee);

        if (addItemForm.total_amount !== displayTotal) {
            setAddItemForm((prev) => ({ ...prev, total_amount: displayTotal }));
        }
    }, [addItemForm.cog_amount, addItemForm.delivery_fee, addItemForm.total_amount, setAddItemForm]);

    const statusFilterLabel = useMemo(
        () => computeStatusFilterLabel(statusFilter, statusLabel),
        [statusFilter],
    );

    const updateStatus = async (id: number, status: StatusId) => {
        try {
            const url = `${apiBase}/api/workspaces/${workspace.slug}/inventory/purchased-orders/${id}/status`;
            const { res, json } = await doRequest('PATCH', url, { status });
            if (!res.ok) throw new Error((json as { message?: string }).message || 'Failed to update status.');

            setRows((prev) => prev.map((row) => (row.id === id ? { ...row, status } : row)));
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to update status.');
        }
    };

    const submitAddItem = async () => {
        setAddItemSubmitting(true);
        setAddItemFieldErrors({});
        setError(null);
        const isEdit = Boolean(editingRow);

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

            const url = isEdit
                ? `${apiBase}/api/workspaces/${workspace.slug}/inventory/purchased-orders/${editingRow?.id}`
                : `${apiBase}/api/workspaces/${workspace.slug}/inventory/purchased-orders`;
            const { res, json } = await doRequest(isEdit ? 'PUT' : 'POST', url, {
                issue_date: addItemForm.issue_date,
                delivery_no: addItemForm.delivery_no || null,
                cust_po_no: addItemForm.cust_po_no || null,
                control_no: addItemForm.control_no || null,
                item: addItemForm.item,
                cog_amount: numericCog,
                delivery_fee: numericDelivery,
                total_amount: numericTotal,
                status: addItemForm.status,
            });

            if (!res.ok) {
                const data = json as { message?: string; errors?: Record<string, string[]> };
                if (res.status === 422 && data.errors) {
                    const mapped: Record<string, string> = {};
                    Object.entries(data.errors).forEach(([key, value]) => {
                        mapped[key] = Array.isArray(value) ? value[0] : String(value);
                    });
                    setAddItemFieldErrors(mapped);
                    return;
                }

                throw new Error(data.message || 'Failed to create purchased order.');
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

    const confirmDelete = async () => {
        if (!rowToDelete) return;

        setDeleteSubmitting(true);
        setError(null);

        try {
            const url = `${apiBase}/api/workspaces/${workspace.slug}/inventory/purchased-orders/${rowToDelete.id}`;
            const { res, json } = await doRequest('DELETE', url);
            if (!res.ok) throw new Error((json as { message?: string }).message || 'Failed to delete purchased order.');

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
                    onResetFilters={() => {
                        setQuery('');
                        setStatusFilter('all');
                        setStartDate('');
                        setEndDate('');
                        void fetchRows(1);
                    }}
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
                            status: normalizeStatusLabel(row.status),
                        });
                        setAddItemOpen(true);
                    }}
                    onOpenDelete={(row) => {
                        setRowToDelete(row);
                        setDeleteModalOpen(true);
                    }}
                    footerSlot={(
                        <PurchasedOrdersPagination
                            variant="inline"
                            loading={loading}
                            fromRow={fromRow}
                            toRow={toRow}
                            totalRows={totalRows}
                            currentPage={currentPage}
                            lastPage={lastPage}
                            onFetchPage={(page) => void fetchRows(page)}
                        />
                    )}
                />
            </div>
        </AppLayout>
    );
};

export default Index;