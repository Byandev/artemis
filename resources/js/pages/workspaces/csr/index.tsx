import PageHeader from '@/components/common/PageHeader';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import { Search, MoreHorizontal, Pencil, User as UserIcon } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger
} from '@/components/ui/dropdown-menu';
import { EmployeeFormDialog } from '@/pages/workspaces/employees/components/employee-form-dialog';
import { User } from '@/types/models/Pancake/User';

interface Props {
    workspace: Workspace;
    employees: PaginatedData<User>;
    systemUsers: { id: string; name: string }[];
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: { search?: string };
    };
}

export default function EmployeesIndex({ workspace, employees, systemUsers, query }: Props) {
    const initialSorting = useMemo(() => toFrontendSort(query?.sort ?? null), [query?.sort]);
    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [editingEmployee, setEditingEmployee] = useState<User | null>(null);

    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(
                `/workspaces/${workspace.slug}/csr/management`,
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

    const columns = useMemo<ColumnDef<User>[]>(
        () => [
            {
                accessorKey: 'name',
                enableSorting: true,
                size: 240,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Name" />
                ),
            },
            {
                accessorKey: 'email',
                enableSorting: true,
                size: 240,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Email" />
                ),
                cell: ({ row }) => row.original.email || '-',
            },
            {
                accessorKey: 'phone_number',
                enableSorting: true,
                size: 160,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Phone" />
                ),
                cell: ({ row }) => row.original.phone_number || '-',
            },
            {
                accessorKey: 'system_user',
                enableSorting: true,
                size: 200,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Assigned User" />
                ),
                cell: ({ row }) => {
                    const name = row.original.system_user?.name;
                    if (!name)
                        return (
                            <span className="text-gray-400 italic">
                                Unassigned
                            </span>
                        );

                    return (
                        <div className="flex items-center gap-2">
                            <div className="flex h-5 w-5 items-center justify-center rounded-full bg-stone-100 dark:bg-zinc-800">
                                <UserIcon className="h-3 w-3 text-gray-500" />
                            </div>
                            <span className="font-medium text-gray-700 dark:text-gray-300">
                                {name}
                            </span>
                        </div>
                    );
                },
            },
            {
                accessorKey: 'status',
                header: ({ column }) => (
                    <SortableHeader column={column} title="Status" />
                ),
                size: 120,
                cell: ({ row }) => {
                    const status = (
                        row.original.status || 'ACTIVE'
                    ).toUpperCase();
                    const isActive = status === 'ACTIVE';
                    return (
                        <span
                            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold ${
                                isActive
                                    ? 'bg-[#E6F9F1] text-[#10B981]'
                                    : 'bg-[#FFF1F2] text-[#F43F5E]'
                            }`}
                        >
                            <span
                                className={`mr-1.5 h-1.5 w-1.5 rounded-full ${isActive ? 'bg-[#10B981]' : 'bg-[#F43F5E]'}`}
                            ></span>
                            {status}
                        </span>
                    );
                },
            },
            {
                id: 'actions',
                cell: ({ row }) => (
                    <div className="flex justify-end">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 transition-all hover:bg-stone-100 dark:border-white/6 dark:bg-zinc-800">
                                    <MoreHorizontal className="h-3.5 w-3.5" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-40">
                                <DropdownMenuItem
                                    onClick={() =>
                                        setEditingEmployee(row.original)
                                    }
                                >
                                    <Pencil className="mr-2 h-3.5 w-3.5" />
                                    Edit Settings
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                ),
            },
        ],
        [],
    );

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Employees`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader title="Employees" description="Manage Pancake users connected to your workspace" />

                <div className="mb-3 flex items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pl-8 pr-3 font-mono! text-[12px]! text-gray-800 outline-none transition-all focus:border-emerald-500 dark:bg-zinc-800 dark:text-white"
                            placeholder="Search employees..."
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                        />
                    </div>
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={employees.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(employees, ['data']) }}
                        onFetch={(params) => {
                            router.get(`/workspaces/${workspace.slug}/employees`, {
                                sort: params?.sort,
                                'filter[search]': searchValue || undefined,
                                page: params?.page ?? 1,
                            }, { preserveState: true, replace: true, preserveScroll: true });
                        }}
                    />
                </div>

                <EmployeeFormDialog
                    open={editingEmployee !== null}
                    onOpenChange={(open) => {
                        if (!open) setEditingEmployee(null);
                    }}
                    employee={editingEmployee}
                    workspace={workspace}
                    systemUsers={systemUsers}
                />
            </div>
        </AppLayout>
    );
}
