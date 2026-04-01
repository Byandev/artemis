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
                <Table className="table-fixed">
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => {
                                    return (
                                        <TableHead
                                            key={header.id}
                                            className="px-4 py-2.5 text-[10px] font-mono font-medium uppercase tracking-wider text-gray-300 dark:text-gray-600 border-b border-black/6 dark:border-white/6 whitespace-nowrap"
                                            style={{ width: header.getSize() ? `${header.getSize()}px` : undefined }}
                                        >
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
                                        <TableCell
                                            key={cell.id}
                                            className='px-4 py-3 text-[12px] text-black dark:text-gray-400 border-b border-black/6 dark:border-white/6 align-top whitespace-nowrap'
                                            style={{ width: cell.column.getSize() ? `${cell.column.getSize()}px` : undefined }}
                                        >
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
                meta?.links?.length &&
                <div className=" border-black/6 dark:border-white/6 py-3 pl-[18px] pr-4">
                    <div className="flex flex-col xl:flex-row xl:items-center xl:justify-between">
                        <div className="">
                            <p className="font-mono text-xs font-light   text-center text-gray-400 border-b border-gray-100 dark:border-gray-800 dark:text-gray-400 xl:border-b-0 xl:pb-0 xl:text-left">
                                Showing {meta?.from} to {meta?.to} of {meta?.total} entries
                            </p>
                        </div>

                        <Pagination currentPage={meta.current_page} totalPages={meta.last_page} onPageChange={(page) => {
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
    const sorted = useMemo(() => column.getIsSorted(), [column])

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
