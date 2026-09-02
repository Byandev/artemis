"use client"

import {
    Column,
    ColumnDef,
    ColumnOrderState,
    ExpandedState,
    PaginationState,
    Row,
    RowData,
    RowSelectionState,
    SortingState,
    VisibilityState,
    flexRender,
    getCoreRowModel,
    getExpandedRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable
} from "@tanstack/react-table";
import { Fragment } from 'react';

import Pagination from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { CircleHelp, Inbox } from 'lucide-react';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { toBackendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { TriangleDownIcon, TriangleUpIcon } from '@radix-ui/react-icons';

// Allow columns to tighten/override their header & cell padding via meta.
declare module '@tanstack/react-table' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface ColumnMeta<TData extends RowData, TValue> {
        headerClassName?: string;
        cellClassName?: string;
    }
}
import { useEffect, useMemo, useRef, useState } from 'react';

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[]
    data: TData[]
    initialSorting?: SortingState,
    /**
     * Paginate (and sort) the rows already in hand instead of asking the
     * server. Off by default, so a table driven by `onFetch` + `meta` keeps
     * its server-side paging.
     */
    enableInternalPagination?: boolean
    /** Rows per page when paginating internally. Defaults to 25. */
    internalPageSize?: number
    /** Where internal paging starts — restore a remembered page here. */
    initialPagination?: Partial<PaginationState>
    /** Fires whenever the internal page or page size changes. */
    onInternalPaginationChange?: (state: PaginationState) => void
    /**
     * Fires whenever sorting changes, in either mode. `onFetch` already covers
     * the server-driven case; this one also fires when sorting is done here.
     */
    onSortChange?: (sorting: SortingState) => void
    onFetch?: (params?: { [key: string]: string | number | null }) => void,
    meta?: Omit<PaginatedData<TData>, 'data'>
    rowSelection?: RowSelectionState
    onRowSelectionChange?: (selection: RowSelectionState) => void
    getRowId?: (row: TData, index: number) => string
    onRowClick?: (row: TData) => void
    columnVisibility?: VisibilityState
    onColumnVisibilityChange?: (state: VisibilityState) => void
    columnOrder?: ColumnOrderState
    onColumnOrderChange?: (order: ColumnOrderState) => void
    /** When provided, rows become expandable and this renders the expanded panel. */
    renderSubRow?: (row: Row<TData>) => React.ReactNode
}

export function DataTable<TData, TValue>({
                                             columns,
                                             data,
                                             onFetch,
                                             enableInternalPagination = false,
                                             internalPageSize = 25,
                                             initialPagination,
                                             onInternalPaginationChange,
                                             onSortChange,
                                             initialSorting,
                                             meta,
                                             rowSelection,
                                             onRowSelectionChange,
                                             getRowId,
                                             onRowClick,
                                             columnVisibility,
                                             onColumnVisibilityChange,
                                             columnOrder,
                                             onColumnOrderChange,
                                             renderSubRow,
                                         }: DataTableProps<TData, TValue>) {
    const [sorting, setSorting] = useState<SortingState>(initialSorting ?? [])
    const [expanded, setExpanded] = useState<ExpandedState>({})

    const hasPaginationMeta = Boolean(
        meta
        && typeof meta.current_page === 'number'
        && typeof meta.last_page === 'number'
        && typeof meta.per_page === 'number'
        && typeof meta.total === 'number'
    )
    const footerMeta = hasPaginationMeta ? meta : null

    const [internalPagination, setInternalPagination] = useState<PaginationState>({
        pageIndex: initialPagination?.pageIndex ?? 0,
        pageSize: initialPagination?.pageSize ?? internalPageSize,
    })

    // Held in a ref so reporting the state back does not need the caller to
    // memoize the callback.
    const reportPagination = useRef(onInternalPaginationChange)
    reportPagination.current = onInternalPaginationChange

    const isFirstRender = useRef(true)

    useEffect(() => {
        if (!enableInternalPagination || isFirstRender.current) return

        reportPagination.current?.(internalPagination)
    }, [enableInternalPagination, internalPagination])

    const serverPagination = useMemo<PaginationState>(() => ({
        pageIndex: meta?.current_page ? meta.current_page - 1 : 0,
        pageSize: meta?.per_page ?? 10
    }), [meta?.current_page, meta?.per_page])

    const pagination = enableInternalPagination ? internalPagination : serverPagination

    // Filtering upstream changes the row count under our feet; land back on the
    // first page rather than on a page that no longer exists. Skipped on mount,
    // so a restored page survives the first render.
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false

            return
        }

        if (!enableInternalPagination) return

        setInternalPagination((prev) => (prev.pageIndex === 0 ? prev : { ...prev, pageIndex: 0 }))
    }, [enableInternalPagination, data.length])

    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        onSortingChange: (updater) => {
            const next = typeof updater === "function" ? updater(sorting) : updater
            setSorting(next)
            onSortChange?.(next)

            if (onFetch) onFetch({ sort: toBackendSort(next), page: 1, per_page: meta?.per_page ?? null })
        },
        getSortedRowModel: getSortedRowModel(),
        getExpandedRowModel: getExpandedRowModel(),
        onExpandedChange: setExpanded,
        getRowCanExpand: () => Boolean(renderSubRow),
        getRowId,
        enableRowSelection: !!onRowSelectionChange,
        onRowSelectionChange: onRowSelectionChange
            ? (updater) => {
                const next = typeof updater === 'function' ? updater(rowSelection ?? {}) : updater
                onRowSelectionChange(next)
            }
            : undefined,
        onColumnVisibilityChange: onColumnVisibilityChange
            ? (updater) => {
                const next = typeof updater === 'function' ? updater(columnVisibility ?? {}) : updater
                onColumnVisibilityChange(next)
            }
            : undefined,
        onColumnOrderChange: onColumnOrderChange
            ? (updater) => {
                const next = typeof updater === 'function' ? updater(columnOrder ?? []) : updater
                onColumnOrderChange(next)
            }
            : undefined,
        getPaginationRowModel: getPaginationRowModel(),
        manualPagination: !enableInternalPagination,
        onPaginationChange: enableInternalPagination ? setInternalPagination : undefined,
        state: {
            sorting,
            pagination,
            ...(rowSelection !== undefined ? { rowSelection } : {}),
            ...(columnVisibility !== undefined ? { columnVisibility } : {}),
            ...(columnOrder !== undefined ? { columnOrder } : {}),
            ...(renderSubRow ? { expanded } : {}),
        },
        // Server-driven when there is a fetcher to ask; otherwise sort the rows
        // we already hold, so the click does something without a round trip.
        manualSorting: Boolean(onFetch),
    })


    const showFooter = hasPaginationMeta || (enableInternalPagination && data.length > 0)

    // One shape for both modes: the server's meta, or what the table itself
    // knows about the rows it is holding.
    const footer = enableInternalPagination
        ? {
            perPage: internalPagination.pageSize,
            currentPage: internalPagination.pageIndex + 1,
            lastPage: Math.max(table.getPageCount(), 1),
            total: data.length,
            from: data.length === 0 ? 0 : internalPagination.pageIndex * internalPagination.pageSize + 1,
            to: Math.min((internalPagination.pageIndex + 1) * internalPagination.pageSize, data.length),
        }
        : {
            perPage: footerMeta?.per_page ?? 10,
            currentPage: footerMeta?.current_page ?? 1,
            lastPage: footerMeta?.last_page ?? 1,
            total: footerMeta?.total ?? 0,
            from: footerMeta?.from ?? 0,
            to: footerMeta?.to ?? 0,
        }

    return (
        <>
            <div className="max-w-full overflow-x-auto custom-scrollbar">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => {
                                    return (
                                        <TableHead key={header.id} className={cn("px-4 py-2.5 text-[10px] font-mono font-medium uppercase tracking-wider text-gray-300 dark:text-gray-600 border-b border-black/6 dark:border-white/6 [&:has([role=checkbox])]:pr-0", header.column.columnDef.meta?.headerClassName)}>
                                            {header.isPlaceholder
                                                ? null
                                                : flexRender(
                                                    header.column.columnDef.header,
                                                    header.getContext()
                                                )}
                                        </TableHead>
                                    )
                                })}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows?.length ? (
                            table.getRowModel().rows.map((row) => (
                                <Fragment key={row.id}>
                                    <TableRow
                                        data-state={row.getIsSelected() && "selected"}
                                        className={[
                                            'transition-colors hover:bg-emerald-500/3',
                                            onRowClick ? 'cursor-pointer' : '',
                                        ].join(' ')}
                                        onClick={() => onRowClick?.(row.original)}
                                    >
                                        {row.getVisibleCells().map((cell) => (
                                            <TableCell key={cell.id} className={cn('px-4 py-3  text-[12px] text-black dark:text-gray-400 border-b border-black/6 dark:border-white/6 align-top', cell.column.columnDef.meta?.cellClassName)}>
                                                {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                    {renderSubRow && row.getIsExpanded() && (
                                        <TableRow className="hover:bg-transparent">
                                            <TableCell colSpan={row.getVisibleCells().length} className="border-b border-black/6 bg-stone-50/60 p-0 dark:border-white/6 dark:bg-zinc-900/40">
                                                {renderSubRow(row)}
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </Fragment>
                            ))
                        ) : (
                            <TableRow className="hover:bg-transparent">
                                <TableCell colSpan={columns.length} className="py-16 text-center">
                                    <div className="flex flex-col items-center gap-3">
                                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-stone-100 dark:bg-zinc-800">
                                            <Inbox className="h-5 w-5 text-gray-400 dark:text-gray-500" />
                                        </div>
                                        <div className="space-y-1">
                                            <p className="font-mono text-[12px] font-medium text-gray-600 dark:text-gray-400">No results found</p>
                                            <p className="font-mono text-[11px] text-gray-400 dark:text-gray-600">Try adjusting your search or filters</p>
                                        </div>
                                    </div>
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            {
                showFooter &&
                <div className="border-t border-black/6 dark:border-white/6 px-4 py-3">
                    <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex items-center gap-2">
                                <span className="font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                    Rows
                                </span>
                                <Select
                                    value={String(footer.perPage)}
                                    onValueChange={(val) => {
                                        if (enableInternalPagination) {
                                            setInternalPagination({ pageIndex: 0, pageSize: Number(val) })

                                            return
                                        }

                                        if (onFetch) onFetch({ per_page: Number(val), page: 1, sort: toBackendSort(sorting) })
                                    }}
                                >
                                    <SelectTrigger className="h-7 w-[72px] rounded-lg border border-black/6 bg-stone-50 px-2.5 font-mono! text-[11px]! dark:border-white/6 dark:bg-zinc-800">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent className="min-w-[72px]">
                                        {[10, 25, 50, 100, 500].map((n) => (
                                            <SelectItem key={n} value={String(n)} className="font-mono! text-[11px]!">
                                                {n}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="h-4 w-px bg-black/6 dark:bg-white/6" />
                            <p className="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                Showing {footer.from} to {footer.to} of {footer.total.toLocaleString()} entries
                            </p>
                        </div>

                        <Pagination currentPage={footer.currentPage} totalPages={footer.lastPage} onPageChange={(page) => {
                            if (enableInternalPagination) {
                                setInternalPagination((prev) => ({ ...prev, pageIndex: page - 1 }))

                                return
                            }

                            if (onFetch) onFetch({ page, sort: toBackendSort(sorting), per_page: footerMeta?.per_page ?? null })
                        }} />
                    </div>
                </div>
            }
        </>
    )
}


type SortableHeaderProps<TData> = {
    column: Column<TData, unknown>
    title: string
    enabled?: boolean;
    className?: string
    /** What the column means, behind a question mark next to its title. */
    help?: React.ReactNode
}

export function SortableHeader<TData>({ column, title, enabled = true, className = '', help }: SortableHeaderProps<TData>) {
    const sorted = column.getIsSorted();

    return (
        <div
            className={`flex items-center justify-between ${enabled ? 'cursor-pointer' : ''} ${className}`}
            onClick={() => {
                if (enabled) {
                    column.toggleSorting(sorted === 'asc')
                }
            }}
        >
            <p className="font-mono font-medium text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">
                {title}
            </p>
            {help && <ColumnHelp>{help}</ColumnHelp>}
            {enabled && <button className="flex flex-col">
                <TriangleUpIcon className={`-mb-1 ${sorted === 'asc' ? 'text-brand-500' : 'text-gray-300'}`} />
                <TriangleDownIcon className={`-mt-1 ${sorted === 'desc' ? 'text-brand-500' : 'text-gray-300'}`} />
            </button>}
        </div>
    )
}


/**
 * A question mark beside a column title, explaining what the column counts.
 *
 * Stops the click reaching the header: on a sortable column the whole header is
 * the sort control, so asking what a column means would otherwise re-sort the
 * table underneath the answer.
 */
export function ColumnHelp({ children }: { children: React.ReactNode }) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <button
                    type="button"
                    // Explanatory, not actionable — so it takes an aria-label
                    // rather than visible text, and stays reachable by keyboard
                    // because Radix opens the tooltip on focus as well as hover.
                    aria-label="What this column means"
                    onClick={(e) => e.stopPropagation()}
                    className="ml-1 inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full text-gray-300 transition-colors hover:text-gray-500 focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:outline-none dark:text-gray-600 dark:hover:text-gray-300"
                >
                    <CircleHelp className="h-3 w-3" />
                </button>
            </TooltipTrigger>
            <TooltipContent
                side="bottom"
                align="start"
                className="max-w-[320px] px-3.5 py-2.5 text-[12px] leading-relaxed font-normal normal-case"
            >
                {children}
            </TooltipContent>
        </Tooltip>
    );
}
