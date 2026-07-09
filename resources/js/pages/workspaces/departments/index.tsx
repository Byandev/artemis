import PageHeader from '@/components/common/PageHeader';
import { DeleteDepartmentDialog } from '@/components/departments/delete-department-dialog';
import {
    Department,
    DepartmentFormDialog,
} from '@/components/departments/department-form-dialog';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import { MoreHorizontal, Pencil, Search, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface DepartmentRow extends Department {
    users_count: number;
    created_at: string;
}

interface Props {
    workspace: Workspace;
    departments: PaginatedData<DepartmentRow>;
    query?: {
        sort?: string | null;
        per_page?: number | string;
        page?: number | string;
        filter?: { search?: string };
    };
}

export default function DepartmentsIndex({
    workspace,
    departments,
    query,
}: Props) {
    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );

    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const [editing, setEditing] = useState<DepartmentRow | null>(null);
    const [toDelete, setToDelete] = useState<DepartmentRow | null>(null);
    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');

    const canCreate = usePermission(PERMISSIONS.CreateDepartments);
    const canEdit = usePermission(PERMISSIONS.EditDepartments);
    const canDelete = usePermission(PERMISSIONS.DeleteDepartments);
    const showActions = canEdit || canDelete;

    const baseUrl = `/workspaces/${workspace.slug}/departments`;

    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(
                baseUrl,
                {
                    sort: query?.sort,
                    'filter[search]': searchValue || undefined,
                    page: searchValue ? 1 : (query?.page ?? 1),
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['departments'],
                },
            );
        }, 500);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchValue]);

    const columns: ColumnDef<DepartmentRow>[] = [
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Name" />
            ),
            cell: ({ row }) => (
                <div className="space-y-0.5">
                    <span className="text-[12px] font-medium text-gray-800 dark:text-gray-200">
                        {row.original.name}
                    </span>
                    {row.original.description && (
                        <p className="max-w-[280px] truncate font-mono text-[11px] text-gray-400 dark:text-gray-500">
                            {row.original.description}
                        </p>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'code',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Code" />
            ),
            cell: ({ row }) =>
                row.original.code ? (
                    <span className="rounded-md bg-stone-100 px-2 py-0.5 font-mono text-[11px] text-gray-600 dark:bg-zinc-800 dark:text-gray-400">
                        {row.original.code}
                    </span>
                ) : (
                    <span className="text-[11px] text-gray-300 dark:text-gray-600">
                        —
                    </span>
                ),
        },
        {
            accessorKey: 'users_count',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Members" />
            ),
            cell: ({ row }) => (
                <span className="inline-flex items-center gap-1.5 rounded-full bg-stone-100 px-2.5 py-1 font-mono text-[11px] font-medium text-gray-600 dark:bg-zinc-800 dark:text-gray-400">
                    {row.original.users_count}{' '}
                    {row.original.users_count === 1 ? 'member' : 'members'}
                </span>
            ),
        },
        {
            accessorKey: 'is_active',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Status" />
            ),
            cell: ({ row }) => (
                <span
                    className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-mono text-[11px] font-medium ${
                        row.original.is_active
                            ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400'
                            : 'bg-stone-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400'
                    }`}
                >
                    {row.original.is_active ? 'Active' : 'Inactive'}
                </span>
            ),
        },
        ...(showActions
            ? [
                  {
                      id: 'actions',
                      cell: ({ row }) => {
                          const department = row.original;
                          return (
                              <div className="flex justify-end">
                                  <DropdownMenu>
                                      <DropdownMenuTrigger asChild>
                                          <button className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 transition-all hover:border-black/12 hover:bg-stone-100 hover:text-gray-600 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-500 dark:hover:border-white/12 dark:hover:bg-zinc-700 dark:hover:text-gray-300">
                                              <MoreHorizontal className="h-3.5 w-3.5" />
                                          </button>
                                      </DropdownMenuTrigger>
                                      <DropdownMenuContent
                                          align="end"
                                          className="w-36"
                                      >
                                          {canEdit && (
                                              <DropdownMenuItem
                                                  onClick={() =>
                                                      setEditing(department)
                                                  }
                                              >
                                                  <Pencil />
                                                  Edit
                                              </DropdownMenuItem>
                                          )}
                                          {canEdit && canDelete && (
                                              <DropdownMenuSeparator />
                                          )}
                                          {canDelete && (
                                              <DropdownMenuItem
                                                  variant="destructive"
                                                  onClick={() =>
                                                      setToDelete(department)
                                                  }
                                              >
                                                  <Trash2 />
                                                  Delete
                                              </DropdownMenuItem>
                                          )}
                                      </DropdownMenuContent>
                                  </DropdownMenu>
                              </div>
                          );
                      },
                  } as ColumnDef<DepartmentRow>,
              ]
            : []),
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Departments`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Departments"
                    description="Organize your workspace into departments"
                >
                    {canCreate && (
                        <button
                            onClick={() => setCreateDialogOpen(true)}
                            className="flex h-8 items-center rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                        >
                            Create Department
                        </button>
                    )}
                </PageHeader>

                <div className="mb-3 flex items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                            placeholder="Search departments…"
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                        />
                    </div>
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={departments.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(departments, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                baseUrl,
                                {
                                    sort: params?.sort,
                                    'filter[search]': searchValue || undefined,
                                    page: params?.page ?? 1,
                                    per_page: params?.per_page,
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

                {(canCreate || canEdit) && (
                    <DepartmentFormDialog
                        open={createDialogOpen || editing !== null}
                        onOpenChange={(open) => {
                            if (!open) {
                                setCreateDialogOpen(false);
                                setEditing(null);
                            }
                        }}
                        department={editing}
                        workspace={workspace}
                    />
                )}

                {canDelete && (
                    <DeleteDepartmentDialog
                        department={toDelete}
                        workspace={workspace}
                        onClose={() => setToDelete(null)}
                    />
                )}
            </div>
        </AppLayout>
    );
}
