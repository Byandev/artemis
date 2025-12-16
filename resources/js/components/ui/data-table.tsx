"use client"

import {
    ColumnDef,
    flexRender,
    getCoreRowModel,
    getPaginationRowModel,
    useReactTable,
    SortingState,
    getSortedRowModel, HeaderContext, Column
} from "@tanstack/react-table"

import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table"
import { Button } from "./button"
import { useMemo, useState } from 'react';
import { toBackendSort } from '@/lib/sort';
import { TriangleDownIcon, TriangleUpIcon } from '@radix-ui/react-icons';

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[]
    data: TData[]
    initialSorting?: SortingState,
    enableInternalPagination?: boolean
    onFetch?: (params?: { sort?: string }) => void
}



export function DataTable<TData, TValue>({
    columns,
    data,
    enableInternalPagination = false,
    onFetch,
    initialSorting
}: DataTableProps<TData, TValue>) {
    const [sorting, setSorting] = useState<SortingState>(initialSorting ?? [])

    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        getPaginationRowModel: enableInternalPagination ? getPaginationRowModel() : undefined,
        initialState: enableInternalPagination ? { pagination: { pageSize: 5 } } : undefined,
        onSortingChange: (updater) => {
            const next = typeof updater === "function" ? updater(sorting) : updater
            setSorting(next)

            if (onFetch) onFetch({ sort: toBackendSort(next) })
        },
        getSortedRowModel: getSortedRowModel(),
        state: { sorting },
        manualSorting: true,
    })


    return (
        <>
            <div className="max-w-full overflow-x-auto custom-scrollbar">
                <Table>
                    <TableHeader className="border-t border-gray-100 dark:border-white/[0.05]">
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => {
                                    return (
                                        <TableHead key={header.id} className="px-4 py-3 border border-gray-100 dark:border-white/[0.05]">
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
                                >
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id} className='px-4 py-3 border border-gray-100 dark:border-white/[0.05]text-gray-700 text-theme-xs dark:text-gray-400'>
                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell colSpan={columns.length} className="h-24 text-center">
                                    No results.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            {enableInternalPagination && (
                <div className="flex items-center justify-end space-x-2 py-4">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => table.previousPage()}
                        disabled={!table.getCanPreviousPage()}
                    >
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => table.nextPage()}
                        disabled={!table.getCanNextPage()}
                    >
                        Next
                    </Button>
                </div >
            )}
        </>
    )
}


type SortableHeaderProps<TData> = {
    column: Column<TData, unknown>
    title: string
}

export function SortableHeader<TData>({ column, title }: SortableHeaderProps<TData>) {
    const sorted = useMemo(() => column.getIsSorted(), [column])

    return (
        <div
            className="flex items-center justify-between cursor-pointer"
            onClick={() => column.toggleSorting(sorted === 'asc')}
        >
            <p className="font-medium text-gray-700 text-theme-xs dark:text-gray-400">
                {title}
            </p>
            <button className="flex flex-col">
                <TriangleUpIcon className={`-mb-1 ${sorted === 'asc' ? 'text-brand-500': 'text-gray-300'}`}/>
                <TriangleDownIcon className={`-mt-1 ${sorted === 'desc' ? 'text-brand-500': 'text-gray-300'}`}/>
            </button>
        </div>
    )
}
