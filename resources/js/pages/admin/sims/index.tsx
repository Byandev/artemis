import PageHeader from '@/components/common/PageHeader';
import RowActionsMenu from '@/components/common/RowActionsMenu';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import AdminSidebarLayout from '@/layouts/admin/admin-sidebar-layout';
import { PaginatedData } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { debounce, omit } from 'lodash';
import { Pencil, Plus, Search, Smartphone, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Option } from './partials/sim-form';

interface SimRow {
    id: number;
    workspace_id: number;
    workspace?: { id: number; name: string; slug: string } | null;
    phone_number: string;
    carrier: string;
    port_number: number | null;
    label: string | null;
    status: string;
    sms_messages_count: number;
}

interface Filters {
    search?: string;
    sort?: string;
    direction?: string;
    workspace_id?: string;
    status?: string;
    carrier?: string;
}

interface Props {
    sims: PaginatedData<SimRow>;
    workspaces: { id: number; name: string }[];
    statuses: Option[];
    carriers: Option[];
    filters: Filters;
}

const STATUS_STYLES: Record<string, string> = {
    active: 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400',
    pending_shipment:
        'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
    received: 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',
    suspended:
        'bg-orange-50 text-orange-700 dark:bg-orange-500/10 dark:text-orange-400',
    cancelled: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
};

const selectClass =
    'rounded-md border border-zinc-200 bg-white py-2 pr-8 pl-3 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-800 dark:bg-zinc-950';

export default function Index({
    sims,
    workspaces,
    statuses,
    carriers,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [workspaceId, setWorkspaceId] = useState(filters.workspace_id || '');
    const [status, setStatus] = useState(filters.status || '');
    const [carrier, setCarrier] = useState(filters.carrier || '');

    const initialSorting = useMemo(() => {
        if (filters.sort) {
            return [{ id: filters.sort, desc: filters.direction === 'desc' }];
        }
        return [];
    }, [filters.sort, filters.direction]);

    const query = useCallback(
        (overrides: Record<string, string | number | undefined> = {}) => {
            router.get(
                '/admin/sims',
                {
                    search: search || undefined,
                    workspace_id: workspaceId || undefined,
                    status: status || undefined,
                    carrier: carrier || undefined,
                    page: 1,
                    ...overrides,
                },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        },
        [search, workspaceId, status, carrier],
    );

    const debouncedSearch = useCallback(
        debounce((s: string) => query({ search: s || undefined }), 400),
        [query],
    );

    useEffect(() => {
        if (search !== (filters.search || '')) {
            debouncedSearch(search);
        }
        return () => debouncedSearch.cancel();
    }, [search]);

    function handleDelete(sim: SimRow) {
        if (
            confirm(
                `Delete SIM ${sim.phone_number}? Its message history is kept but it can no longer send or receive.`,
            )
        ) {
            router.delete(`/admin/sims/${sim.id}`);
        }
    }

    const columns: ColumnDef<SimRow>[] = [
        {
            accessorKey: 'phone_number',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="SIM" />
            ),
            cell: ({ row }) => (
                <div className="flex items-center gap-3">
                    <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                        <Smartphone className="h-4 w-4" />
                    </div>
                    <div>
                        <div className="font-semibold text-zinc-900 dark:text-zinc-100">
                            {row.original.phone_number}
                        </div>
                        {row.original.label ? (
                            <div className="text-xs text-zinc-500">
                                {row.original.label}
                            </div>
                        ) : null}
                    </div>
                </div>
            ),
        },
        {
            id: 'workspace',
            enableSorting: false,
            header: () => (
                <div className="text-[11px] font-bold tracking-wider text-zinc-500 uppercase">
                    Workspace
                </div>
            ),
            cell: ({ row }) => (
                <span className="text-sm text-zinc-700 dark:text-zinc-300">
                    {row.original.workspace?.name ?? '—'}
                </span>
            ),
        },
        {
            accessorKey: 'carrier',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Carrier" />
            ),
            cell: ({ row }) => (
                <span className="text-sm text-zinc-600 capitalize dark:text-zinc-400">
                    {row.original.carrier}
                </span>
            ),
        },
        {
            accessorKey: 'port_number',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Port"
                    className="justify-center"
                />
            ),
            cell: ({ row }) => (
                <div className="text-center font-mono text-sm text-zinc-600 dark:text-zinc-400">
                    {row.original.port_number ?? '—'}
                </div>
            ),
        },
        {
            accessorKey: 'sms_messages_count',
            enableSorting: false,
            header: () => (
                <div className="text-center text-[11px] font-bold tracking-wider text-zinc-500 uppercase">
                    Messages
                </div>
            ),
            cell: ({ row }) => (
                <div className="text-center text-sm text-zinc-600 dark:text-zinc-400">
                    {row.original.sms_messages_count.toLocaleString()}
                </div>
            ),
        },
        {
            accessorKey: 'status',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Status"
                    className="justify-center"
                />
            ),
            cell: ({ row }) => (
                <div className="text-center">
                    <span
                        className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ${
                            STATUS_STYLES[row.original.status] ??
                            'bg-zinc-100 text-zinc-500 dark:bg-zinc-800'
                        }`}
                    >
                        {row.original.status.replace(/_/g, ' ')}
                    </span>
                </div>
            ),
        },
        {
            id: 'actions',
            enableSorting: false,
            header: () => (
                <div className="text-right text-[11px] font-bold tracking-wider text-zinc-500 uppercase">
                    Actions
                </div>
            ),
            cell: ({ row }) => (
                <RowActionsMenu
                    width="w-36"
                    actions={[
                        {
                            label: 'Edit',
                            icon: <Pencil />,
                            href: `/admin/sims/${row.original.id}/edit`,
                        },
                        {
                            label: 'Delete',
                            icon: <Trash2 />,
                            onSelect: () => handleDelete(row.original),
                            destructive: true,
                            separatorBefore: true,
                        },
                    ]}
                />
            ),
        },
    ];

    return (
        <AdminSidebarLayout>
            <Head title="Admin | Workspace SIMs" />

            <div className="p-4 md:p-6">
                <PageHeader
                    title="Workspace SIMs"
                    description="Provision SIMs and assign them to workspaces."
                >
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="relative w-full sm:w-56">
                            <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                            <input
                                type="text"
                                placeholder="Search number or label…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="w-full rounded-md border border-zinc-200 bg-white py-2 pr-4 pl-10 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-800 dark:bg-zinc-950"
                            />
                        </div>
                        <select
                            value={workspaceId}
                            onChange={(e) => {
                                setWorkspaceId(e.target.value);
                                query({
                                    workspace_id: e.target.value || undefined,
                                });
                            }}
                            className={selectClass}
                        >
                            <option value="">All workspaces</option>
                            {workspaces.map((w) => (
                                <option key={w.id} value={w.id.toString()}>
                                    {w.name}
                                </option>
                            ))}
                        </select>
                        <select
                            value={status}
                            onChange={(e) => {
                                setStatus(e.target.value);
                                query({ status: e.target.value || undefined });
                            }}
                            className={selectClass}
                        >
                            <option value="">All statuses</option>
                            {statuses.map((s) => (
                                <option key={s.value} value={s.value}>
                                    {s.label}
                                </option>
                            ))}
                        </select>
                        <select
                            value={carrier}
                            onChange={(e) => {
                                setCarrier(e.target.value);
                                query({ carrier: e.target.value || undefined });
                            }}
                            className={selectClass}
                        >
                            <option value="">All carriers</option>
                            {carriers.map((c) => (
                                <option key={c.value} value={c.value}>
                                    {c.label}
                                </option>
                            ))}
                        </select>
                        <Link
                            href="/admin/sims/create"
                            className="inline-flex items-center gap-1.5 rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-700"
                        >
                            <Plus className="h-4 w-4" />
                            Add SIM
                        </Link>
                    </div>
                </PageHeader>

                <div className="mt-6 rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={sims.data || []}
                        enableInternalPagination={false}
                        initialSorting={initialSorting}
                        meta={{ ...omit(sims, ['data']) }}
                        onFetch={(params) => {
                            const sortStr =
                                params?.sort && params.sort !== null
                                    ? String(params.sort)
                                    : null;
                            query({
                                sort: sortStr
                                    ? sortStr.replace(/^-/, '')
                                    : undefined,
                                direction: sortStr
                                    ? sortStr.startsWith('-')
                                        ? 'desc'
                                        : 'asc'
                                    : undefined,
                                page: params?.page ?? 1,
                                per_page: params?.per_page ?? undefined,
                            });
                        }}
                    />
                </div>
            </div>
        </AdminSidebarLayout>
    );
}
