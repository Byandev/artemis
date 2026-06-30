import PageHeader from '@/components/common/PageHeader';
import { DeleteUnitCodeDialog } from '@/components/gencys/delete-unit-code-dialog';
import {
    UnitCode,
    UnitCodeFormDialog,
} from '@/components/gencys/unit-code-form-dialog';
import Pagination from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router, usePage } from '@inertiajs/react';
import { debounce } from 'lodash';
import {
    ChevronDown,
    ChevronRight,
    Pencil,
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

function parseSort(sort?: string | null): { field: string; desc: boolean } {
    if (!sort) return { field: 'unit_code', desc: false };
    const desc = sort.startsWith('-');
    return { field: desc ? sort.slice(1) : sort, desc };
}

export default function UnitCodesIndex({ workspace, unitCodes, query }: Props) {
    const { flash } = usePage().props as {
        flash?: { success?: string; error?: string };
    };
    const canCreate = usePermission(PERMISSIONS.CreateUnitCode);
    const canEdit = usePermission(PERMISSIONS.EditUnitCode);
    const canDelete = usePermission(PERMISSIONS.DeleteUnitCode);

    const [searchValue, setSearchValue] = useState(query.filter?.search ?? '');
    const [expanded, setExpanded] = useState<number | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<UnitCodeRow | null>(null);
    const [deleting, setDeleting] = useState<UnitCodeRow | null>(null);
    const [syncing, setSyncing] = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    const sort = parseSort(query.sort);

    const fetchData = (overrides: Record<string, unknown> = {}) => {
        router.get(
            `/workspaces/${workspace.slug}/gencys/unit-codes`,
            {
                filter: { search: searchValue || undefined },
                sort: query.sort,
                per_page: query.perPage ?? unitCodes.per_page ?? undefined,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const toggleSort = (field: string) => {
        const desc = sort.field === field ? !sort.desc : false;
        fetchData({ sort: `${desc ? '-' : ''}${field}`, page: 1 });
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

    const openEdit = (row: UnitCodeRow) => {
        setEditing(row);
        setFormOpen(true);
    };

    const handleSync = () => {
        router.post(
            `/workspaces/${workspace.slug}/gencys/unit-codes/sync`,
            {},
            {
                preserveScroll: true,
                onStart: () => setSyncing(true),
                onFinish: () => setSyncing(false),
            },
        );
    };

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
                            {syncing ? 'Syncing…' : 'Sync from ERP'}
                        </button>
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

                <div className="overflow-x-auto rounded-xl border border-black/6 dark:border-white/6">
                    <table className="w-full border-collapse text-left text-[12px]">
                        <thead className="bg-stone-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-300">
                            <tr>
                                <th className="w-8 px-3 py-2" />
                                <SortHeader
                                    label="Unit Code"
                                    field="unit_code"
                                    sort={sort}
                                    onSort={toggleSort}
                                />
                                <SortHeader
                                    label="SKU"
                                    field="sku"
                                    sort={sort}
                                    onSort={toggleSort}
                                />
                                <SortHeader
                                    label="Total Amount"
                                    field="total_amount"
                                    align="right"
                                    sort={sort}
                                    onSort={toggleSort}
                                />
                                <th className="px-3 py-2 text-right font-medium">
                                    Items
                                </th>
                                {(canEdit || canDelete) && (
                                    <th className="w-20 px-3 py-2 text-right font-medium">
                                        Actions
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {unitCodes.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={canEdit || canDelete ? 6 : 5}
                                        className="px-3 py-8 text-center text-gray-400"
                                    >
                                        No unit codes found.
                                    </td>
                                </tr>
                            )}
                            {unitCodes.data.map((uc) => {
                                const isOpen = expanded === uc.id;
                                const items = uc.items ?? [];
                                return (
                                    <FragmentRow
                                        key={uc.id}
                                        uc={uc}
                                        items={items}
                                        isOpen={isOpen}
                                        canEdit={canEdit}
                                        canDelete={canDelete}
                                        onToggle={() =>
                                            setExpanded(isOpen ? null : uc.id)
                                        }
                                        onEdit={() => openEdit(uc)}
                                        onDelete={() => setDeleting(uc)}
                                    />
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                <div className="mt-4 flex flex-col items-center justify-between gap-3 sm:flex-row">
                    <div className="flex items-center gap-3">
                        <div className="flex items-center gap-2">
                            <span className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                Per page
                            </span>
                            <Select
                                value={String(unitCodes.per_page ?? 25)}
                                onValueChange={(v) =>
                                    fetchData({ per_page: Number(v), page: 1 })
                                }
                            >
                                <SelectTrigger className="h-7 w-[72px] rounded-lg border border-black/6 bg-stone-50 px-2.5 font-mono! text-[11px]! dark:border-white/6 dark:bg-zinc-800">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent className="min-w-[72px]">
                                    {[25, 50, 100, 200].map((n) => (
                                        <SelectItem
                                            key={n}
                                            value={String(n)}
                                            className="font-mono! text-[11px]!"
                                        >
                                            {n}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="h-4 w-px bg-black/6 dark:bg-white/6" />
                        <p className="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                            Showing {unitCodes.from ?? 0} to {unitCodes.to ?? 0}{' '}
                            of {(unitCodes.total ?? 0).toLocaleString()} entries
                        </p>
                    </div>
                    <Pagination
                        currentPage={unitCodes.current_page ?? 1}
                        totalPages={unitCodes.last_page ?? 1}
                        onPageChange={(page) => fetchData({ page })}
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

function SortHeader({
    label,
    field,
    align,
    sort,
    onSort,
}: {
    label: string;
    field: string;
    align?: 'left' | 'right';
    sort: { field: string; desc: boolean };
    onSort: (field: string) => void;
}) {
    const active = sort.field === field;
    return (
        <th
            className={cn(
                'px-3 py-2',
                align === 'right' ? 'text-right' : 'text-left',
            )}
        >
            <button
                onClick={() => onSort(field)}
                className={cn(
                    'group inline-flex items-center gap-1 font-medium transition-colors hover:text-gray-900 dark:hover:text-gray-100',
                    align === 'right' && 'flex-row-reverse',
                )}
            >
                {label}
                <ChevronDown
                    className={cn(
                        'h-3 w-3 transition-transform',
                        active ? 'text-emerald-500' : 'opacity-30',
                        active && !sort.desc && 'rotate-180',
                    )}
                />
            </button>
        </th>
    );
}

function FragmentRow({
    uc,
    items,
    isOpen,
    canEdit,
    canDelete,
    onToggle,
    onEdit,
    onDelete,
}: {
    uc: UnitCodeRow;
    items: UnitCodeItem[];
    isOpen: boolean;
    canEdit: boolean;
    canDelete: boolean;
    onToggle: () => void;
    onEdit: () => void;
    onDelete: () => void;
}) {
    const colSpan = canEdit || canDelete ? 6 : 5;
    return (
        <>
            <tr className="border-t border-black/6 dark:border-white/6">
                <td className="px-3 py-2">
                    {items.length > 0 && (
                        <button
                            onClick={onToggle}
                            className="flex h-5 w-5 items-center justify-center rounded hover:bg-black/5 dark:hover:bg-white/10"
                            aria-label="Toggle items"
                        >
                            <ChevronRight
                                className={`h-3.5 w-3.5 transition-transform ${isOpen ? 'rotate-90' : ''}`}
                            />
                        </button>
                    )}
                </td>
                <td className="px-3 py-2 font-mono">{uc.unit_code ?? '—'}</td>
                <td className="px-3 py-2">{uc.sku ?? '—'}</td>
                <td className="px-3 py-2 text-right">
                    {uc.total_amount ?? '—'}
                </td>
                <td className="px-3 py-2 text-right">{items.length}</td>
                {(canEdit || canDelete) && (
                    <td className="px-3 py-2">
                        <div className="flex items-center justify-end gap-1">
                            {canEdit && (
                                <button
                                    onClick={onEdit}
                                    className="flex h-7 w-7 items-center justify-center rounded text-gray-400 transition-colors hover:bg-black/5 hover:text-gray-700 dark:hover:bg-white/10 dark:hover:text-gray-200"
                                    aria-label="Edit unit code"
                                >
                                    <Pencil className="h-3.5 w-3.5" />
                                </button>
                            )}
                            {canDelete && (
                                <button
                                    onClick={onDelete}
                                    className="flex h-7 w-7 items-center justify-center rounded text-gray-400 transition-colors hover:bg-red-500/10 hover:text-red-500"
                                    aria-label="Delete unit code"
                                >
                                    <Trash2 className="h-3.5 w-3.5" />
                                </button>
                            )}
                        </div>
                    </td>
                )}
            </tr>
            {isOpen && items.length > 0 && (
                <tr className="bg-stone-50 dark:bg-zinc-900/40">
                    <td />
                    <td colSpan={colSpan - 1} className="px-3 py-2">
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
                    </td>
                </tr>
            )}
        </>
    );
}
