import PageHeader from '@/components/common/PageHeader';
import { DeleteUnitCodeDialog } from '@/components/gencys/delete-unit-code-dialog';
import {
    UnitCode,
    UnitCodeFormDialog,
} from '@/components/gencys/unit-code-form-dialog';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { cn } from '@/lib/utils';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { debounce, omit } from 'lodash';
import {
    ChevronRight,
    Pencil,
    Plus,
    RefreshCw,
    Search,
    Trash2,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

interface UnitCodeItem {
    id: number;
    unit_code: string | null;
    item_code: string | null;
    quantity: number | null;
}

interface UnitCodeRow extends UnitCode {
    items?: UnitCodeItem[];
}

interface Props {
    workspace: Workspace;
    unitCodes: PaginatedData<UnitCodeRow>;
    query: {
        sort: string;
        perPage: number | null;
        filter: { search?: string };
    };
}

function ItemBreakdown({ items }: { items: UnitCodeItem[] }) {
    if (items.length === 0) {
        return (
            <div className="px-6 py-3 font-mono text-[11px] text-gray-400 dark:text-gray-600">
                No items.
            </div>
        );
    }
    return (
        <div className="px-6 py-3">
            <table className="w-full text-[11px]">
                <thead className="text-gray-400">
                    <tr>
                        <th className="py-1 text-left font-medium">
                            Item Code
                        </th>
                        <th className="py-1 text-right font-medium">
                            Quantity
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {items.map((item) => (
                        <tr
                            key={item.id}
                            className="text-gray-600 dark:text-gray-300"
                        >
                            <td className="py-1 font-mono">
                                {item.item_code ?? '—'}
                            </td>
                            <td className="py-1 text-right">
                                {item.quantity ?? '—'}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function UnitCodesIndex({ workspace, unitCodes, query }: Props) {
    const { flash } = usePage().props as {
        flash?: { success?: string; error?: string };
    };
    const canCreate = usePermission(PERMISSIONS.CreateUnitCode);
    const canEdit = usePermission(PERMISSIONS.EditUnitCode);
    const canDelete = usePermission(PERMISSIONS.DeleteUnitCode);

    const [searchValue, setSearchValue] = useState(query.filter?.search ?? '');
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<UnitCodeRow | null>(null);
    const [deleting, setDeleting] = useState<UnitCodeRow | null>(null);
    const [syncing, setSyncing] = useState(false);

    const baseUrl = `/workspaces/${workspace.slug}/gencys/unit-codes`;

    const initialSorting = useMemo(
        () => toFrontendSort(query.sort ?? null),
        [query.sort],
    );

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    const fetchData = (overrides: Record<string, unknown> = {}) => {
        router.get(
            baseUrl,
            {
                filter: { search: searchValue || undefined },
                sort: query.sort,
                per_page: query.perPage ?? unitCodes.per_page ?? undefined,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const debouncedFetch = useMemo(
        () => debounce(() => fetchData({ page: 1 }), 400),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [searchValue],
    );

    useEffect(() => {
        if ((query.filter?.search ?? '') !== searchValue) debouncedFetch();
        return () => debouncedFetch.cancel();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchValue]);

    const handleSync = () => {
        router.post(
            `${baseUrl}/sync`,
            {},
            {
                preserveScroll: true,
                onStart: () => setSyncing(true),
                onFinish: () => setSyncing(false),
            },
        );
    };

    const columns = useMemo<ColumnDef<UnitCodeRow>[]>(() => {
        const cols: ColumnDef<UnitCodeRow>[] = [
            {
                id: 'expander',
                enableSorting: false,
                meta: {
                    headerClassName: 'w-0 px-0',
                    cellClassName: 'w-0 px-0',
                },
                header: () => null,
                cell: ({ row }) =>
                    (row.original.items?.length ?? 0) > 0 ? (
                        <button
                            type="button"
                            onClick={(e) => {
                                e.stopPropagation();
                                row.toggleExpanded();
                            }}
                            aria-label={
                                row.getIsExpanded()
                                    ? 'Collapse items'
                                    : 'Expand items'
                            }
                            className="flex h-6 w-5 items-center justify-center text-gray-400 transition-all hover:text-gray-600 dark:hover:text-gray-300"
                        >
                            <ChevronRight
                                className={cn(
                                    'h-3.5 w-3.5 transition-transform',
                                    row.getIsExpanded() && 'rotate-90',
                                )}
                            />
                        </button>
                    ) : null,
            },
            {
                accessorKey: 'unit_code',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Unit Code" />
                ),
                cell: ({ row }) => (
                    <span className="font-mono text-[12px] text-gray-700 dark:text-gray-300">
                        {row.original.unit_code ?? '—'}
                    </span>
                ),
            },
            {
                accessorKey: 'sku',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="SKU" />
                ),
                cell: ({ row }) => (
                    <span className="text-[12px] text-gray-600 dark:text-gray-400">
                        {row.original.sku ?? '—'}
                    </span>
                ),
            },
            {
                accessorKey: 'total_amount',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Total Amount" />
                ),
                cell: ({ row }) => (
                    <span className="font-mono text-[12px] text-gray-700 dark:text-gray-300">
                        {row.original.total_amount ?? '—'}
                    </span>
                ),
            },
            {
                id: 'items',
                enableSorting: false,
                header: () => (
                    <span className="font-mono text-[10px] tracking-wider text-gray-400 uppercase">
                        Items
                    </span>
                ),
                cell: ({ row }) => (
                    <span className="text-[12px] text-gray-500 dark:text-gray-400">
                        {row.original.items?.length ?? 0}
                    </span>
                ),
            },
        ];

        if (canEdit || canDelete) {
            cols.push({
                id: 'actions',
                enableSorting: false,
                meta: {
                    headerClassName: 'text-right',
                    cellClassName: 'text-right',
                },
                header: () => (
                    <span className="font-mono text-[10px] tracking-wider text-gray-400 uppercase">
                        Actions
                    </span>
                ),
                cell: ({ row }) => (
                    <div className="flex items-center justify-end gap-1">
                        {canEdit && (
                            <button
                                onClick={() => {
                                    setEditing(row.original);
                                    setFormOpen(true);
                                }}
                                className="flex h-7 w-7 items-center justify-center rounded text-gray-400 transition-colors hover:bg-black/5 hover:text-gray-700 dark:hover:bg-white/10 dark:hover:text-gray-200"
                                aria-label="Edit unit code"
                            >
                                <Pencil className="h-3.5 w-3.5" />
                            </button>
                        )}
                        {canDelete && (
                            <button
                                onClick={() => setDeleting(row.original)}
                                className="flex h-7 w-7 items-center justify-center rounded text-gray-400 transition-colors hover:bg-red-500/10 hover:text-red-500"
                                aria-label="Delete unit code"
                            >
                                <Trash2 className="h-3.5 w-3.5" />
                            </button>
                        )}
                    </div>
                ),
            });
        }

        return cols;
    }, [canEdit, canDelete]);

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Unit Code`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <PageHeader
                        title="Unit Code"
                        description="Unit codes and their inventory item breakdown."
                    />
                    {canCreate && (
                        <div className="flex items-center gap-2">
                            <button
                                onClick={() => {
                                    setEditing(null);
                                    setFormOpen(true);
                                }}
                                className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-black/8 bg-white px-4 font-mono! text-[12px]! font-medium text-gray-700 transition-all hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-200 dark:hover:bg-zinc-700"
                            >
                                <Plus className="h-4 w-4" />
                                Add Unit Code
                            </button>
                            <button
                                onClick={handleSync}
                                disabled={syncing}
                                className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <RefreshCw
                                    className={cn(
                                        'h-4 w-4',
                                        syncing && 'animate-spin',
                                    )}
                                />
                                {syncing ? 'Syncing…' : 'Sync from Gencys ERP'}
                            </button>
                        </div>
                    )}
                </div>

                <div className="mt-4 mb-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                    <div className="relative w-full sm:w-64">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                            placeholder="Search SKU or unit code…"
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                            aria-label="Search unit codes"
                        />
                    </div>
                </div>

                <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={unitCodes.data || []}
                        initialSorting={initialSorting}
                        renderSubRow={(row) => (
                            <ItemBreakdown items={row.original.items ?? []} />
                        )}
                        meta={{ ...omit(unitCodes, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                baseUrl,
                                {
                                    sort: params?.sort,
                                    filter: {
                                        search: searchValue || undefined,
                                    },
                                    page: params?.page ?? 1,
                                    per_page:
                                        params?.per_page ??
                                        query.perPage ??
                                        unitCodes.per_page,
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

            <UnitCodeFormDialog
                workspace={workspace}
                open={formOpen}
                onOpenChange={(open) => {
                    setFormOpen(open);
                    if (!open) setEditing(null);
                }}
                unitCode={editing}
            />

            <DeleteUnitCodeDialog
                workspace={workspace}
                unitCode={deleting}
                onClose={() => setDeleting(null)}
            />
        </AppLayout>
    );
}
