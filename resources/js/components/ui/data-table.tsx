"use client"

import {
    ColumnDef,
    flexRender,
    getCoreRowModel,
    getPaginationRowModel,
    useReactTable,
    SortingState,
    getSortedRowModel,
    Column
} from "@tanstack/react-table"

import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table"
import { useMemo, useState } from 'react';
import { toBackendSort } from '@/lib/sort';
import { TriangleDownIcon, TriangleUpIcon } from '@radix-ui/react-icons';
import { PaginatedData } from '@/types';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[]
    data: TData[]
    initialSorting?: SortingState,
    enableInternalPagination?: boolean
    onFetch?: (params?: { sort?: string }) => void,
    meta?: Omit<PaginatedData<TData>, 'data'>
}

export function DataTable<TData, TValue>({
    columns,
    data,
    enableInternalPagination = false,
    onFetch,
    initialSorting,
    meta
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

            {
                enableInternalPagination &&
                <div className="border border-t-0 rounded-b-xl border-gray-100 py-4 pl-[18px] pr-4 dark:border-white/[0.05]">
                    <div className="flex flex-col xl:flex-row xl:items-center xl:justify-between">
                        <div className="pb-3 xl:pb-0">
                            <p className="pb-3 text-sm font-medium text-center text-gray-500 border-b border-gray-100 dark:border-gray-800 dark:text-gray-400 xl:border-b-0 xl:pb-0 xl:text-left">
                                Showing {meta?.from} to {meta?.to} of {meta?.total} entries
                            </p>
                        </div>

                        <div className="flex gap-x-2">

                            <div className="flex flex-wrap gap-2 justify-end">
                                {(meta?.links ?? []).map((link, idx) => {
                                    // Laravel labels can be "Previous", "Next", or page numbers (sometimes with HTML entities)
                                    const label = link.label
                                        .replace("&laquo;", "«")
                                        .replace("&raquo;", "»")
                                        .replace("Previous", "Prev")
                                        .replace("Next", "Next")

                                    return (
                                        <Button
                                            key={idx}
                                            asChild
                                            variant={link.active ? "default" : "outline"}
                                            disabled={!link.url}
                                            className="h-8 px-3 text-sm"
                                        >
                                            {link.url ? (
                                                <Link href={link.url} preserveState preserveScroll>
                                                    <span dangerouslySetInnerHTML={{ __html: label }} />
                                                </Link>
                                            ) : (
                                                <span dangerouslySetInnerHTML={{ __html: label }} />
                                            )}
                                        </Button>
                                    )
                                })}
                            </div>
                        </div>

                    </div>
                </div>
            }

            {/*{enableInternalPagination && (*/}
            {/*    <div className="flex items-center justify-end space-x-2 py-4">*/}
            {/*        <Button*/}
            {/*            variant="outline"*/}
            {/*            size="sm"*/}
            {/*            onClick={() => table.previousPage()}*/}
            {/*            disabled={!table.getCanPreviousPage()}*/}
            {/*        >*/}
            {/*            Previous*/}
            {/*        </Button>*/}
            {/*        <Button*/}
            {/*            variant="outline"*/}
            {/*            size="sm"*/}
            {/*            onClick={() => table.nextPage()}*/}
            {/*            disabled={!table.getCanNextPage()}*/}
            {/*        >*/}
            {/*            Next*/}
            {/*        </Button>*/}
            {/*    </div >*/}
            {/*)}*/}
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
