import PageHeader from '@/components/common/PageHeader';
import { FinanceDeleteDialog } from '@/components/finance/delete-dialog';
import { FinanceRemittance, RemittanceFormDialog } from '@/components/finance/remittance-form-dialog';
import { RemittanceItemFormDialog } from '@/components/finance/remittance-item-form-dialog';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { debounce, omit } from 'lodash';
import { AlertTriangle, ArrowLeft, ChevronDown, MoreHorizontal, Pencil, Search, Trash2, Upload } from 'lucide-react';
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';

interface RemittanceItem {
    id: number;
    waybill_number: string;
    order_number: string | null;
    shipping_date: string | null;
    sender_city: string | null;
    destination_city: string | null;
    package_billing_weight: number | string;
    item_value: number | string;
    value_added_fee: number | string;
    receivable_freight: number | string;
    total_shipping_cost: number | string;
    cod: number | string;
    cod_commission_rate: number | string;
    cod_commission: number | string;
    cod_commission_vat_fee: number | string;
    shipping_customer_code: string | null;
    signing_time: string | null;
}

interface Remittance extends FinanceRemittance {
    is_reconciled: boolean;
    transaction?: {
        id: number;
        date: string;
        amount: number | string;
        description?: string;
        account?: { id: number; name: string } | null;
    } | null;
}

interface TransactionOpt {
    id: number;
    account_id: number;
    date: string;
    description: string;
    amount: number | string;
    type: 'in' | 'out';
    account?: { id: number; name: string } | null;
}

interface Props {
    workspace: Workspace;
    remittance: Remittance;
    items: PaginatedData<RemittanceItem>;
    itemsQuery?: { sort?: string | null; perPage?: string | null; filter?: { search?: string } };
    transactions: TransactionOpt[];
}

const peso = (v: number | string) => `₱${Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

export default function RemittanceShow({ workspace, remittance, items, itemsQuery, transactions }: Props) {
    const [editOpen, setEditOpen] = useState(false);
    const [importing, setImporting] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const base = `/workspaces/${workspace.slug}/finance`;
    const showUrl = `${base}/remittances/${remittance.id}`;
    const [editingItem, setEditingItem] = useState<RemittanceItem | null>(null);
    const [toDelete, setToDelete] = useState<RemittanceItem | null>(null);

    const initialSorting = useMemo(() => toFrontendSort(itemsQuery?.sort ?? null), [itemsQuery?.sort]);
    const [search, setSearch] = useState(itemsQuery?.filter?.search ?? '');

    const performSearch = useCallback(
        debounce((s: string) => {
            router.get(showUrl,
                { sort: itemsQuery?.sort, 'filter[search]': s || undefined, perPage: itemsQuery?.perPage, page: 1 },
                { preserveState: true, replace: true, preserveScroll: true, only: ['items', 'itemsQuery'] });
        }, 400),
        [showUrl, itemsQuery?.sort, itemsQuery?.perPage]
    );

    useEffect(() => { performSearch(search); return () => performSearch.cancel(); }, [search, performSearch]);

    const columns: ColumnDef<RemittanceItem>[] = [
        {
            accessorKey: 'waybill_number', enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Waybill" />,
            cell: ({ row }) => <span className="font-mono text-[12px] font-medium text-gray-800 dark:text-gray-100">{row.original.waybill_number}</span>,
        },
        {
            accessorKey: 'order_number', enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Order No." />,
            cell: ({ row }) => <span className="text-gray-500">{row.original.order_number ?? '—'}</span>,
        },
        {
            accessorKey: 'shipping_date', enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Ship Date" />,
            cell: ({ row }) => <span className="text-gray-500">{row.original.shipping_date ? String(row.original.shipping_date).slice(0, 10) : '—'}</span>,
        },
        {
            accessorKey: 'sender_city', enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="From" />,
            cell: ({ row }) => <span className="text-gray-500">{row.original.sender_city ?? '—'}</span>,
        },
        {
            accessorKey: 'destination_city', enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="To" />,
            cell: ({ row }) => <span className="text-gray-500">{row.original.destination_city ?? '—'}</span>,
        },
        {
            id: 'package_billing_weight',
            header: () => <div className="text-right font-mono text-[10px] uppercase tracking-wider text-gray-300">Weight</div>,
            cell: ({ row }) => <div className="text-right text-gray-500">{Number(row.original.package_billing_weight).toFixed(2)}</div>,
        },
        {
            accessorKey: 'item_value', enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Item Value" className="justify-end" />,
            cell: ({ row }) => <div className="text-right text-gray-700 dark:text-gray-200">{peso(row.original.item_value)}</div>,
        },
        {
            accessorKey: 'total_shipping_cost', enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Shipping" className="justify-end" />,
            cell: ({ row }) => <div className="text-right text-gray-500">{peso(row.original.total_shipping_cost)}</div>,
        },
        {
            accessorKey: 'cod', enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="COD" className="justify-end" />,
            cell: ({ row }) => <div className="text-right text-gray-700 dark:text-gray-200">{peso(row.original.cod)}</div>,
        },
        {
            accessorKey: 'cod_commission', enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Commission" className="justify-end" />,
            cell: ({ row }) => <div className="text-right text-rose-600 dark:text-rose-400">{peso(row.original.cod_commission)}</div>,
        },
        {
            id: 'cod_commission_vat_fee',
            header: () => <div className="text-right font-mono text-[10px] uppercase tracking-wider text-gray-300">VAT</div>,
            cell: ({ row }) => <div className="text-right text-rose-600 dark:text-rose-400">{peso(row.original.cod_commission_vat_fee)}</div>,
        },
        {
            accessorKey: 'signing_time', enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Signed" />,
            cell: ({ row }) => <span className="text-gray-500">{row.original.signing_time ? String(row.original.signing_time).slice(0, 10) : '—'}</span>,
        },
        {
            id: 'actions',
            header: () => <div className="text-center font-mono text-[10px] uppercase tracking-wider text-gray-300">Actions</div>,
            cell: ({ row }) => (
                <div className="flex justify-center">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 hover:bg-stone-100 dark:border-white/6 dark:bg-zinc-800">
                                <MoreHorizontal className="h-3.5 w-3.5" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-36">
                            <DropdownMenuItem onClick={() => setEditingItem(row.original)}>
                                <Pencil className="mr-2 h-3.5 w-3.5" /> Edit
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem className="text-red-600 focus:text-red-600" onClick={() => setToDelete(row.original)}>
                                <Trash2 className="mr-2 h-3.5 w-3.5" /> Delete
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - SOA ${remittance.soa_number}`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <Link href={`${base}/remittances`} className="mb-3 inline-flex items-center gap-1 text-[12px] text-gray-500 hover:text-gray-800 dark:text-gray-400">
                    <ArrowLeft className="h-3.5 w-3.5" /> Back to Remittances
                </Link>

                <PageHeader
                    title={`SOA ${remittance.soa_number}`}
                    description={`${remittance.courier} · ${String(remittance.billing_date_from).slice(0, 10)} → ${String(remittance.billing_date_to).slice(0, 10)}`}
                >
                    <div className="flex items-center gap-2">
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            className="hidden"
                            onChange={(e) => {
                                const file = e.target.files?.[0];
                                if (!file) return;
                                setImporting(true);
                                router.post(`${base}/remittances/${remittance.id}/import-items`, { file }, {
                                    forceFormData: true,
                                    onFinish: () => {
                                        setImporting(false);
                                        if (fileInputRef.current) fileInputRef.current.value = '';
                                    },
                                });
                            }}
                        />
                        <button
                            onClick={() => setEditOpen(true)}
                            className="flex h-8 items-center gap-1.5 rounded-lg border border-black/8 bg-white px-3.5 font-mono! text-[12px]! font-medium text-gray-700 hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-200"
                        >
                            <Pencil className="h-3.5 w-3.5" /> Edit
                        </button>
                        <button
                            onClick={() => fileInputRef.current?.click()}
                            disabled={importing}
                            className="flex h-8 items-center gap-1.5 rounded-lg border border-black/8 bg-white px-3.5 font-mono! text-[12px]! font-medium text-gray-700 hover:bg-stone-50 disabled:opacity-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-200"
                        >
                            <Upload className="h-3.5 w-3.5" />
                            {importing ? 'Importing...' : 'Import CSV'}
                        </button>
                    </div>
                </PageHeader>

                {!remittance.is_reconciled && (
                    <div className="mb-4 flex items-center gap-2 rounded-[10px] border border-amber-200 bg-amber-50 px-4 py-2.5 text-[12px] text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/5 dark:text-amber-300">
                        <AlertTriangle className="h-3.5 w-3.5" />
                        This remittance is not yet linked to a transaction.
                    </div>
                )}

                <Collapsible defaultOpen={false} className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <CollapsibleTrigger className="flex w-full items-center justify-between px-6 py-4">
                        <div className="flex items-center gap-3">
                            <h3 className="font-mono text-[10px] uppercase tracking-wider text-gray-400">Breakdown</h3>
                            <span className="font-mono text-[13px] font-semibold text-gray-900 dark:text-gray-100">{peso(remittance.net_amount)}</span>
                        </div>
                        <ChevronDown className="h-4 w-4 text-gray-400 transition-transform [[data-state=open]>&]:rotate-180" />
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <div className="border-t border-black/6 px-6 pb-6 pt-4 dark:border-white/6">
                            <dl className="space-y-2 font-mono text-[13px]">
                                <LineItem label="Gross COD" value={peso(remittance.gross_cod)} />
                                <LineItem label="Less COD Fee" value={`-${peso(remittance.cod_fee)}`} negative />
                                <LineItem label="Less COD Fee VAT" value={`-${peso(remittance.cod_fee_vat)}`} negative />
                                <LineItem label="Less Shipping Fee" value={`-${peso(remittance.shipping_fee)}`} negative />
                                <LineItem label="Less Return Shipping" value={`-${peso(remittance.return_shipping)}`} negative />
                                <div className="my-2 border-t border-dashed border-black/10 dark:border-white/10" />
                                <LineItem label="Net Remittance" value={peso(remittance.net_amount)} bold />
                            </dl>

                            <div className="mt-6 grid grid-cols-2 gap-4 border-t border-black/6 pt-4 text-[13px] dark:border-white/6">
                                <Meta label="Status">
                                    <span className={`inline-flex items-center rounded-full px-2 py-0.5 font-mono text-[10px] uppercase ${remittance.status === 'remitted' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400'}`}>
                                        {remittance.status}
                                    </span>
                                </Meta>
                                <Meta label="Linked Transaction">
                                    {remittance.transaction ? (
                                        <Link href={`${base}/accounts/${remittance.transaction.account?.id}`} className="text-emerald-600 hover:underline">
                                            #{remittance.transaction.id} · {remittance.transaction.account?.name ?? '—'} · {peso(remittance.transaction.amount)}
                                        </Link>
                                    ) : (
                                        <span className="text-gray-400">Not yet linked</span>
                                    )}
                                </Meta>
                                {remittance.notes && (
                                    <Meta label="Notes" fullWidth>
                                        <p className="text-gray-700 dark:text-gray-200">{remittance.notes}</p>
                                    </Meta>
                                )}
                            </div>
                        </div>
                    </CollapsibleContent>
                </Collapsible>

                <div className="mt-4">
                    <div className="mb-3 flex items-center gap-2">
                        <h3 className="font-mono text-[10px] uppercase tracking-wider text-gray-400">
                            Items ({items.total})
                        </h3>
                        <div className="ml-auto relative w-full max-w-xs">
                            <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                            <input
                                className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pl-8 pr-3 font-mono! text-[12px]! text-gray-800 outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100"
                                placeholder="Search waybill, order, city..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </div>
                    </div>

                    <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                        <DataTable
                            columns={columns}
                            enableInternalPagination={false}
                            data={items.data || []}
                            initialSorting={initialSorting}
                            meta={{ ...omit(items, ['data']) }}
                            onFetch={(params) => {
                                router.get(showUrl,
                                    { sort: params?.sort, 'filter[search]': search || undefined, perPage: params?.per_page ?? itemsQuery?.perPage, page: params?.page ?? 1 },
                                    { preserveState: true, replace: true, preserveScroll: true, only: ['items', 'itemsQuery'] });
                            }}
                        />
                    </div>
                </div>

                <RemittanceFormDialog
                    open={editOpen}
                    onOpenChange={setEditOpen}
                    remittance={remittance}
                    workspaceSlug={workspace.slug}
                    transactions={transactions}
                />

                <RemittanceItemFormDialog
                    open={editingItem !== null}
                    onOpenChange={(o) => { if (!o) setEditingItem(null); }}
                    item={editingItem}
                    workspaceSlug={workspace.slug}
                    remittanceId={remittance.id}
                />

                <FinanceDeleteDialog
                    open={!!toDelete}
                    onClose={() => setToDelete(null)}
                    title="Delete Item?"
                    description={`Delete waybill ${toDelete?.waybill_number ?? ''}?`}
                    url={toDelete ? `${showUrl}/items/${toDelete.id}` : ''}
                    successMessage="Item deleted"
                />
            </div>
        </AppLayout>
    );
}

function LineItem({ label, value, bold, negative }: { label: string; value: string; bold?: boolean; negative?: boolean }) {
    return (
        <div className="flex items-center justify-between">
            <dt className={bold ? 'font-semibold text-gray-900 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400'}>{label}</dt>
            <dd className={`${bold ? 'text-[16px] font-semibold text-gray-900 dark:text-gray-100' : negative ? 'text-rose-600 dark:text-rose-400' : 'text-gray-700 dark:text-gray-200'}`}>{value}</dd>
        </div>
    );
}

function Meta({ label, children, fullWidth }: { label: string; children: React.ReactNode; fullWidth?: boolean }) {
    return (
        <div className={fullWidth ? 'col-span-2' : ''}>
            <dt className="font-mono text-[10px] uppercase tracking-wider text-gray-400">{label}</dt>
            <dd className="mt-0.5">{children}</dd>
        </div>
    );
}
