import PageHeader from '@/components/common/PageHeader';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import { Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface Employee {
    id: string;
    name: string;
    fb_id: string | null;
    email: string | null;
    phone_number: string | null;
}

interface Props {
    workspace: Workspace;
    employees: PaginatedData<Employee>;
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: { search?: string };
    };
}

export default function EmployeesIndex({ workspace, employees, query }: Props) {
    const initialSorting = useMemo(() => toFrontendSort(query?.sort ?? null), [query?.sort]);
    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');

    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(
                `/workspaces/${workspace.slug}/employees`,
                {
                    sort: query?.sort,
                    'filter[search]': searchValue || undefined,
                    page: searchValue ? 1 : query?.page ?? 1,
                },
                { preserveState: true, replace: true, preserveScroll: true, only: ['employees'] },
            );
        }, 500);
        return () => clearTimeout(timer);
    }, [searchValue]);

    const columns: ColumnDef<Employee>[] = [
        {
            accessorKey: 'name',
            enableSorting: true,
            size: 260,
            header: ({ column }) => (
                <SortableHeader column={column} title="Name" />
            ),
        },
        {
            accessorKey: 'email',
            enableSorting: true,
            size: 260,
            header: ({ column }) => (
                <SortableHeader column={column} title="Email" />
            ),
            cell: ({ row }) => row.original.email || '-',
        },
        {
            accessorKey: 'phone_number',
            enableSorting: true,
            size: 260,
            header: ({ column }) => (
                <SortableHeader column={column} title="Phone" />
            ),
            cell: ({ row }) => row.original.phone_number || '-',
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Employees`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader title="Employees" description="Pancake users connected to your workspace" />

                <div className="mb-3 flex items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 dark:border-white/6 bg-stone-100 dark:bg-zinc-800 pl-8 pr-3 font-mono! text-[12px]! text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-600 outline-none transition-all focus:border-emerald-500 dark:focus:border-emerald-400 focus:ring-2 focus:ring-emerald-500/15"
                            placeholder="Search by name, email or phone..."
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                        />
                    </div>
                </div>

                <div className="rounded-[14px] border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={employees.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(employees, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                `/workspaces/${workspace.slug}/employees`,
                                {
                                    sort: params?.sort,
                                    'filter[search]': searchValue || undefined,
                                    page: params?.page ?? 1,
                                },
                                { preserveState: true, replace: true, preserveScroll: true },
                            );
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
