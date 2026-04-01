"use client"

import * as React from "react";
import {
    Column,
    ColumnDef,
    PaginationState,
    SortingState,
    flexRender,
    getCoreRowModel,
    getSortedRowModel,      
    getPaginationRowModel,
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
    initialSorting = [],
    meta,
    enableInternalPagination = true
}: DataTableProps<TData, TValue>) {
    const [sorting, setSorting] = useState<SortingState>(initialSorting);

    const pagination = useMemo<PaginationState>(() => ({
        pageIndex: meta?.current_page ? meta.current_page - 1 : 0,
        pageSize: meta?.per_page ?? 10
    }), [meta?.current_page, meta?.per_page]);

    const table = useReactTable({
        data,
        columns,
        state: {
            sorting,
            pagination,
        },
        onSortingChange: (updater) => {
            const next = typeof updater === "function" ? updater(sorting) : updater;
            setSorting(next);

            
            if (onFetch) {
                onFetch({ sort: toBackendSort(next), page: 1 });
            }
        },
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(), 
        getPaginationRowModel: getPaginationRowModel(),

        
        manualSorting: onFetch ? true : false,
        manualPagination: onFetch ? true : false,
    });

    return (
        <div className="w-full">
            <div className="max-w-full overflow-x-auto custom-scrollbar">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => (
                                    <TableHead key={header.id} className="px-4 py-2 h-10">
                                        {header.isPlaceholder
                                            ? null
                                            : flexRender(
                                                header.column.columnDef.header,
                                                header.getContext()
                                            )}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows?.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow key={row.id} className="hover:bg-emerald-500/[0.02] transition-colors border-b border-black/5 dark:border-white/5">
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id} className="px-4 py-3 text-[12px] align-top">
                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell colSpan={columns.length} className="py-20 text-center">
                                    <div className="flex flex-col items-center gap-2">
                                        <Inbox className="h-8 w-8 text-gray-300" />
                                        <p className="text-sm text-gray-400">No results found.</p>
                                    </div>
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            {meta && (
                <div className="py-3 px-4 flex items-center justify-between border-t border-black/5">
                    <p className="text-[11px] font-mono text-gray-400">
                        Showing {meta.from} to {meta.to} of {meta.total} entries
                    </p>
                    <Pagination
                        currentPage={meta.current_page}
                        totalPages={meta.last_page}
                        onPageChange={(page) => onFetch?.({ page, sort: toBackendSort(sorting) })}
                    />
                </div>
            )}
        </div>
    );
}

export function SortableHeader<TData>({ column, title, enabled = true, className = '' }: any) {
    const isSorted = column.getIsSorted();

    return (
        <div
            className={`flex items-center justify-between w-full group ${enabled ? 'cursor-pointer select-none' : ''} ${className}`}
            onClick={() => enabled && column.toggleSorting(isSorted === 'asc')}
        >
            {/* Title sa Kaliwa */}
            <span className="font-mono text-[10px] uppercase tracking-wider text-gray-400 group-hover:text-gray-600 transition-colors">
                {title}
            </span>

            {/* Icons sa Kanang Dulo */}
            {enabled && (
                <div className="flex flex-col ml-2 opacity-40 group-hover:opacity-100 transition-opacity">
                    <TriangleUpIcon
                        className={`h-3 w-3 -mb-1.5 ${isSorted === 'asc' ? 'text-emerald-500 opacity-100' : 'text-gray-400'}`}
                    />
                    <TriangleDownIcon
                        className={`h-3 w-3 ${isSorted === 'desc' ? 'text-emerald-500 opacity-100' : 'text-gray-400'}`}
                    />
                </div>
            )}
        </div>
    );
}