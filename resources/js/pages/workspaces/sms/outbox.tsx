import { Head, router, usePage, usePoll } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { debounce } from 'lodash';
import { RefreshCw, Search } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import { Card } from '@/components/ui/card';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { StatusBadge } from '@/components/ui/status-badge';
import AppLayout from '@/layouts/app-layout';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';

type Message = {
    id: number;
    to_number: string;
    message: string;
    segments: number;
    status: string;
    sent_at: string | null;
    created_at: string;
};

type Option = { value: string; label: string };

interface Filters {
    search?: string;
    sort?: string;
    direction?: string;
    status?: string;
}

interface Props {
    workspace: Workspace;
    messages: PaginatedData<Message>;
    statuses: Option[];
    filters: Filters;
}

const selectClass =
    'rounded-md border border-border bg-background py-2 pr-8 pl-3 text-sm outline-none focus:ring-2 focus:ring-brand-500/20';

/**
 * Statuses that the gateway is still working through. A message lands here as
 * `queued`, then moves to `sent`/`failed` when the gateway calls back — which
 * happens after this page has already rendered, so the row would otherwise sit
 * stale until a manual refresh.
 *
 * `sent` is deliberately excluded: it can still become `delivered`, but that
 * depends on a delivery receipt the carrier may never send, so polling on it
 * would never terminate.
 */
const IN_FLIGHT_STATUSES = new Set(['queued', 'sending']);

const POLL_INTERVAL_MS = 5000;

function formatTimestamp(value: string | null) {
    if (!value) return '—';
    return new Date(value).toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

export default function SmsOutbox({
    workspace,
    messages,
    statuses,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');

    // Sending redirects here with a flash message. Nothing renders flash
    // globally, so surface it as a toast — otherwise a send looks like it did
    // nothing at all.
    const { flash } = usePage().props as {
        flash?: { success?: string; error?: string };
    };

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    // Keep refreshing while the gateway still has messages in flight, so
    // "Queued" turns into "Sent" on its own. Only the `messages` prop is
    // refetched; reloads preserve scroll and local state by definition, so
    // typing in the search box is not disturbed. `keepAlive` stays false so
    // polling pauses while the tab is hidden.
    const hasInFlight = useMemo(
        () => messages.data.some((m) => IN_FLIGHT_STATUSES.has(m.status)),
        [messages.data],
    );

    const { start, stop } = usePoll(
        POLL_INTERVAL_MS,
        { only: ['messages'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (!hasInFlight) {
            stop();
            return;
        }

        start();

        return () => stop();
    }, [hasInFlight, start, stop]);

    const initialSorting = useMemo(() => {
        if (filters.sort) {
            return [{ id: filters.sort, desc: filters.direction === 'desc' }];
        }
        return [];
    }, [filters.sort, filters.direction]);

    const query = useCallback(
        (overrides: Record<string, string | number | undefined> = {}) => {
            router.get(
                `/workspaces/${workspace.slug}/sms/outbox`,
                {
                    search: search || undefined,
                    status: status || undefined,
                    page: 1,
                    ...overrides,
                },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        },
        [workspace.slug, search, status],
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

    const columns: ColumnDef<Message>[] = [
        {
            accessorKey: 'created_at',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Created" />
            ),
            cell: ({ row }) => (
                <span className="text-xs text-muted-foreground">
                    {formatTimestamp(row.original.created_at)}
                </span>
            ),
        },
        {
            accessorKey: 'to_number',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="To" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-xs">
                    {row.original.to_number}
                </span>
            ),
        },
        {
            accessorKey: 'message',
            enableSorting: false,
            header: () => <span>Message</span>,
            cell: ({ row }) => (
                <span className="block max-w-md truncate">
                    {row.original.message}
                </span>
            ),
        },
        {
            accessorKey: 'segments',
            enableSorting: false,
            header: () => <div className="text-center">Segments</div>,
            cell: ({ row }) => (
                <div className="text-center text-xs text-muted-foreground">
                    {row.original.segments}
                </div>
            ),
        },
        {
            accessorKey: 'status',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Status" />
            ),
            cell: ({ row }) => <StatusBadge status={row.original.status} />,
        },
        {
            accessorKey: 'sent_at',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Sent" />
            ),
            cell: ({ row }) => (
                <span className="text-xs text-muted-foreground">
                    {formatTimestamp(row.original.sent_at)}
                </span>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title="Outbox" />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">Outbox</h1>
                        <p className="text-sm text-muted-foreground">
                            Messages you've sent — newest first.
                        </p>
                        {hasInFlight && (
                            <p className="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                                <RefreshCw className="size-3 animate-spin" />
                                Updating automatically while messages are in
                                flight…
                            </p>
                        )}
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <div className="relative w-full sm:w-56">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                type="text"
                                placeholder="Search recipient or message…"
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
                    </div>
                </div>

                <Card className="overflow-hidden py-0">
                    <DataTable
                        columns={columns}
                        data={messages.data}
                        enableInternalPagination={false}
                        initialSorting={initialSorting}
                        meta={{
                            current_page: messages.current_page,
                            last_page: messages.last_page,
                            per_page: messages.per_page,
                            total: messages.total,
                            from: messages.from,
                            to: messages.to,
                            links: messages.links,
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
