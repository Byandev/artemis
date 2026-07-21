import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { debounce } from 'lodash';
import { Search, Smartphone } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';

import { Card } from '@/components/ui/card';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import AppLayout from '@/layouts/app-layout';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';

type Sim = {
    id: number;
    phone_number: string;
    label: string | null;
    carrier: string;
    port_number: number | null;
    status: string;
    inbound_count: number;
    outbound_count: number;
    last_activity_at: string | null;
};

type Option = { value: string; label: string };

interface Filters {
    search?: string;
    sort?: string;
    direction?: string;
    status?: string;
    carrier?: string;
}

interface Props {
    workspace: Workspace;
    sims: PaginatedData<Sim>;
    statuses: Option[];
    carriers: Option[];
    filters: Filters;
}

const STATUS_STYLES: Record<string, string> = {
    active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    pending_shipment:
        'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    received:
        'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    suspended:
        'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
};

const selectClass =
    'rounded-md border border-border bg-background py-2 pr-8 pl-3 text-sm outline-none focus:ring-2 focus:ring-brand-500/20';

function formatTimestamp(value: string | null) {
    if (!value) return '—';
    return new Date(value).toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

export default function Sims({
    workspace,
    sims,
    statuses,
    carriers,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search || '');
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
                `/workspaces/${workspace.slug}/sms/sims`,
                {
                    search: search || undefined,
                    status: status || undefined,
                    carrier: carrier || undefined,
                    page: 1,
                    ...overrides,
                },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        },
        [workspace.slug, search, status, carrier],
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

    const columns: ColumnDef<Sim>[] = [
        {
            accessorKey: 'phone_number',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="SIM" />
            ),
            cell: ({ row }) => (
                <div className="flex items-center gap-3">
                    <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                        <Smartphone className="size-4" />
                    </div>
                    <div>
                        <div className="font-medium">
                            {row.original.phone_number}
                        </div>
                        {row.original.label ? (
                            <div className="text-xs text-muted-foreground">
                                {row.original.label}
                            </div>
                        ) : null}
                    </div>
                </div>
            ),
        },
        {
            accessorKey: 'carrier',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Carrier" />
            ),
            cell: ({ row }) => (
                <span className="capitalize">{row.original.carrier}</span>
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
                <div className="text-center font-mono text-sm">
                    {row.original.port_number ?? '—'}
                </div>
            ),
        },
        {
            accessorKey: 'inbound_count',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Received"
                    className="justify-center"
                />
            ),
            cell: ({ row }) => (
                <div className="text-center">
                    {row.original.inbound_count.toLocaleString()}
                </div>
            ),
        },
        {
            accessorKey: 'outbound_count',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Sent"
                    className="justify-center"
                />
            ),
            cell: ({ row }) => (
                <div className="text-center">
                    {row.original.outbound_count.toLocaleString()}
                </div>
            ),
        },
        {
            accessorKey: 'last_activity_at',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Last activity" />
            ),
            cell: ({ row }) => (
                <span className="text-xs text-muted-foreground">
                    {formatTimestamp(row.original.last_activity_at)}
                </span>
            ),
        },
        {
            accessorKey: 'status',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Status" />
            ),
            cell: ({ row }) => (
                <span
                    className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ${
                        STATUS_STYLES[row.original.status] ??
                        'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400'
                    }`}
                >
                    {row.original.status.replace(/_/g, ' ')}
                </span>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title="SIMs" />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">SIMs</h1>
                        <p className="text-sm text-muted-foreground">
                            The SIMs assigned to this workspace. Select one to
                            see its message history.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <div className="relative w-full sm:w-56">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                type="text"
                                placeholder="Search number or label…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="w-full rounded-md border border-border bg-background py-2 pr-4 pl-10 text-sm outline-none focus:ring-2 focus:ring-brand-500/20"
                            />
                        </div>
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
                    </div>
                </div>

                <Card className="overflow-hidden py-0">
                    <DataTable
                        columns={columns}
                        data={sims.data}
                        enableInternalPagination={false}
                        initialSorting={initialSorting}
                        meta={{
                            current_page: sims.current_page,
                            last_page: sims.last_page,
                            per_page: sims.per_page,
                            total: sims.total,
                            from: sims.from,
                            to: sims.to,
                            links: sims.links,
                        }}
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
                </Card>
            </div>
        </AppLayout>
    );
}
