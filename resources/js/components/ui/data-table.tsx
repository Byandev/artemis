"use client"

import {
    Column,
    ColumnDef,
    PaginationState,
    SortingState,
    flexRender,
    getCoreRowModel,
    getSortedRowModel,
    useReactTable
} from "@tanstack/react-table";

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
import { Inbox } from 'lucide-react';
import { toBackendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { TriangleDownIcon, TriangleUpIcon } from '@radix-ui/react-icons';
import { useMemo, useState } from 'react';

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[]
    data: TData[]
    initialSorting?: SortingState,
    enableInternalPagination?: boolean
    onFetch?: (params?: { [key: string]: string | number | null }) => void,
    meta?: Omit<PaginatedData<TData>, 'data'>
}

export function DataTable<TData, TValue>({
    columns,
    data,
    onFetch,
    initialSorting,
    meta,
}: DataTableProps<TData, TValue>) {
    const [sorting, setSorting] = useState<SortingState>(initialSorting ?? [])

    const hasPaginationMeta = Boolean(
        meta
        && typeof meta.current_page === 'number'
        && typeof meta.last_page === 'number'
        && typeof meta.per_page === 'number'
        && typeof meta.total === 'number'
    )
    const footerMeta = hasPaginationMeta ? meta : null

    const pagination = useMemo<PaginationState>(() => ({
        pageIndex: meta?.current_page ? meta.current_page - 1 : 0,
        pageSize: meta?.per_page ?? 10
    }), [meta?.current_page, meta?.per_page])

    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        onSortingChange: (updater) => {
            const next = typeof updater === "function" ? updater(sorting) : updater
            setSorting(next)

            if (onFetch) onFetch({ sort: toBackendSort(next), page: 1 })
        },
        getSortedRowModel: getSortedRowModel(),
        state: {
            sorting,
            pagination
        },
        manualSorting: true,
    })


    return (
        <>
            <div className="max-w-full overflow-x-auto custom-scrollbar">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => {
                                    return (
                                        <TableHead key={header.id} className="px-4 py-2.5 text-[10px] font-mono font-medium uppercase tracking-wider text-gray-300 dark:text-gray-600 border-b border-black/6 dark:border-white/6">
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
                                <TableRow
                                    key={row.id}
                                    data-state={row.getIsSelected() && "selected"}
                                    className="hover:bg-emerald-500/[0.03] transition-colors"
                                >
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id} className='px-4 py-3  text-[12px] text-black dark:text-gray-400 border-b border-black/6 dark:border-white/6 align-top'>
                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                        </TableCell>
                                    ))}
                                </TableRow>
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
                hasPaginationMeta &&
                <div className="border-t border-black/6 dark:border-white/6 px-4 py-3">
                    <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex items-center gap-2">
                                <span className="font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                    Rows
                                </span>
                                <Select
                                    value={String(footerMeta?.per_page ?? 15)}
                                    onValueChange={(val) => {
                                        if (onFetch) onFetch({ per_page: Number(val), page: 1, sort: toBackendSort(sorting) })
                                    }}
                                >
                                    <SelectTrigger className="h-7 w-[72px] rounded-[8px] border border-black/6 bg-stone-50 px-2.5 font-mono! text-[11px]! dark:border-white/6 dark:bg-zinc-800">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent className="min-w-[72px]">
                                        {[10, 25, 50, 100].map((n) => (
                                            <SelectItem key={n} value={String(n)} className="font-mono! text-[11px]!">
                                                {n}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="h-4 w-px bg-black/6 dark:bg-white/6" />
                            <p className="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                Showing {footerMeta?.from} to {footerMeta?.to} of {footerMeta?.total?.toLocaleString()} entries
                            </p>
                        </div>

                        <Pagination currentPage={footerMeta?.current_page ?? 1} totalPages={footerMeta?.last_page ?? 1} onPageChange={(page) => {
                            if (onFetch) onFetch({ page, sort: toBackendSort(sorting) })
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
}

export function SortableHeader<TData>({ column, title, enabled = true, className = '' }: SortableHeaderProps<TData>) {
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
            {enabled && <button className="flex flex-col">
                <TriangleUpIcon className={`-mb-1 ${sorted === 'asc' ? 'text-brand-500' : 'text-gray-300'}`} />
                <TriangleDownIcon className={`-mt-1 ${sorted === 'desc' ? 'text-brand-500' : 'text-gray-300'}`} />
            </button>}
        </div>
    )
}
