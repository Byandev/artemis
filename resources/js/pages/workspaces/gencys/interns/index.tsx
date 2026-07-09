import PageHeader from '@/components/common/PageHeader';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { cn } from '@/lib/utils';
import {
    InlineOwner,
    OwnerOption,
} from '@/pages/workspaces/integrations/components/inline-owner';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import axios from 'axios';
import { debounce, omit } from 'lodash';
import { RefreshCw, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

interface Intern {
    id: number;
    intern_id: number | null;
    full_name: string | null;
    username: string | null;
    contact_number: string | null;
    email: string | null;
    user_id: number | null;
    user?: OwnerOption | null;
    is_active: boolean;
}

interface Props {
    workspace: Workspace;
    interns: PaginatedData<Intern>;
    users: OwnerOption[];
    query: {
        sort: string;
        perPage: number | null;
        filter: { search?: string; user?: string; status?: string };
    };
}

export default function GencysInternsIndex({
    workspace,
    interns,
    users,
    query,
}: Props) {
    const { flash } = usePage().props as {
        flash?: { success?: string; error?: string };
    };
    const canManage = usePermission(PERMISSIONS.ViewGencysInterns);

    const [searchValue, setSearchValue] = useState(query.filter?.search ?? '');
    const [userFilter, setUserFilter] = useState(query.filter?.user ?? '');
    const [statusFilter, setStatusFilter] = useState(
        query.filter?.status ?? '',
    );
    const [syncing, setSyncing] = useState(false);

    // Optimistic per-row state so pickers/toggles feel instant.
    const [userMap, setUserMap] = useState<Record<number, OwnerOption | null>>(
        () =>
            Object.fromEntries(interns.data.map((i) => [i.id, i.user ?? null])),
    );
    const [userSaving, setUserSaving] = useState<Record<number, boolean>>({});
    const [activeMap, setActiveMap] = useState<Record<number, boolean>>(() =>
        Object.fromEntries(interns.data.map((i) => [i.id, i.is_active])),
    );

    const baseUrl = `/workspaces/${workspace.slug}/gencys/interns`;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    useEffect(() => {
        setUserMap(
            Object.fromEntries(interns.data.map((i) => [i.id, i.user ?? null])),
        );
        setActiveMap(
            Object.fromEntries(interns.data.map((i) => [i.id, i.is_active])),
        );
    }, [interns.data]);

    const handleSync = () => {
        router.post(
            `${baseUrl}/sync`,
            {},
            {
                preserveScroll: true,
                onStart: () => setSyncing(true),
                onFinish: () => setSyncing(false),
            },
        );
    };

    const assignUser = (intern: Intern, userId: number | null) => {
        const prev = userMap[intern.id] ?? null;
        const next = userId
            ? (users.find((u) => u.id === userId) ?? null)
            : null;
        setUserMap((m) => ({ ...m, [intern.id]: next }));
        setUserSaving((s) => ({ ...s, [intern.id]: true }));
        axios
            .patch(`${baseUrl}/${intern.id}/user`, { user_id: userId })
            .catch(() => {
                setUserMap((m) => ({ ...m, [intern.id]: prev }));
                toast.error('Could not update the linked user.');
            })
            .finally(() => {
                setUserSaving((s) => ({ ...s, [intern.id]: false }));
            });
    };

    const toggleActive = (intern: Intern) => {
        const next = !(activeMap[intern.id] ?? intern.is_active);
        setActiveMap((m) => ({ ...m, [intern.id]: next }));
        axios.patch(`${baseUrl}/${intern.id}/toggle-active`).catch(() => {
            setActiveMap((m) => ({ ...m, [intern.id]: !next }));
            toast.error('Could not update the status.');
        });
    };

    const initialSorting = useMemo(
        () => toFrontendSort(query.sort ?? null),
        [query.sort],
    );

    // Single source of truth for the request the table/filters issue.
    const fetchData = (
        overrides: Record<string, unknown> = {},
        filterOverrides: Record<string, string | undefined> = {},
    ) => {
        router.get(
            baseUrl,
            {
                filter: {
                    search: searchValue || undefined,
                    user: userFilter || undefined,
                    status: statusFilter || undefined,
                    ...filterOverrides,
                },
                sort: query.sort,
                per_page: query.perPage ?? interns.per_page ?? undefined,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const debouncedFetch = useMemo(
        () => debounce(() => fetchData({ page: 1 }), 400),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [searchValue],
    );

    useEffect(() => {
        if ((query.filter?.search ?? '') !== searchValue) debouncedFetch();
        return () => debouncedFetch.cancel();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchValue]);

    const handleUserFilter = (value: string) => {
        const next = value === 'all' ? '' : value;
        setUserFilter(next);
        fetchData({ page: 1 }, { user: next || undefined });
    };

    const handleStatusFilter = (value: string) => {
        const next = value === 'all' ? '' : value;
        setStatusFilter(next);
        fetchData({ page: 1 }, { status: next || undefined });
    };

    const columns = useMemo<ColumnDef<Intern>[]>(
        () => [
            {
                accessorKey: 'is_active',
                enableSorting: true,
                meta: { headerClassName: 'w-20', cellClassName: 'w-20' },
                header: ({ column }) => (
                    <SortableHeader column={column} title="Status" />
                ),
                cell: ({ row }) => {
                    const active =
                        activeMap[row.original.id] ?? row.original.is_active;
                    if (!canManage) {
                        return (
                            <span
                                className={cn(
                                    'inline-flex items-center gap-1.5 font-mono text-[11px]',
                                    active
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-gray-400 dark:text-gray-600',
                                )}
                            >
                                <span
                                    className={cn(
                                        'h-1.5 w-1.5 rounded-full',
                                        active
                                            ? 'bg-emerald-500'
                                            : 'bg-stone-300 dark:bg-zinc-600',
                                    )}
                                />
                                {active ? 'Active' : 'Inactive'}
                            </span>
                        );
                    }
                    return (
                        <button
                            type="button"
                            role="switch"
                            aria-checked={active}
                            onClick={() => toggleActive(row.original)}
                            title={active ? 'Active' : 'Inactive'}
                            className={cn(
                                'relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 focus-visible:outline-none',
                                active
                                    ? 'bg-emerald-500'
                                    : 'bg-stone-200 dark:bg-zinc-700',
                            )}
                        >
                            <span
                                className={cn(
                                    'pointer-events-none inline-block h-3.5 w-3.5 rounded-full bg-white shadow-sm transition-transform',
                                    active
                                        ? 'translate-x-4'
                                        : 'translate-x-0.5',
                                )}
                            />
                        </button>
                    );
                },
            },
            {
                accessorKey: 'intern_id',
                enableSorting: true,
                meta: { headerClassName: 'w-24', cellClassName: 'w-24' },
                header: ({ column }) => (
                    <SortableHeader column={column} title="Intern ID" />
                ),
                cell: ({ row }) => (
                    <span className="font-mono text-[12px] text-gray-500 dark:text-gray-400">
                        {row.original.intern_id ?? '—'}
                    </span>
                ),
            },
            {
                accessorKey: 'full_name',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Full Name" />
                ),
                cell: ({ row }) => (
                    <span className="text-[12px] font-medium text-gray-800 dark:text-gray-200">
                        {row.original.full_name ?? '—'}
                    </span>
                ),
            },
            {
                accessorKey: 'username',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Username" />
                ),
                cell: ({ row }) => (
                    <span className="font-mono text-[12px] text-gray-600 dark:text-gray-400">
                        {row.original.username ?? '—'}
                    </span>
                ),
            },
            {
                accessorKey: 'contact_number',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Contact Number" />
                ),
                cell: ({ row }) => (
                    <span className="font-mono text-[12px] text-gray-600 dark:text-gray-400">
                        {row.original.contact_number ?? '—'}
                    </span>
                ),
            },
            {
                accessorKey: 'email',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Email" />
                ),
                cell: ({ row }) => (
                    <span className="text-[12px] text-gray-600 dark:text-gray-400">
                        {row.original.email ?? '—'}
                    </span>
                ),
            },
            {
                id: 'user',
                header: ({ column }) => (
                    <SortableHeader column={column} title="User" enabled={false} />
                ),
                cell: ({ row }) => (
                    <InlineOwner
                        owner={
                            row.original.id in userMap
                                ? userMap[row.original.id]
                                : (row.original.user ?? null)
                        }
                        users={users}
                        canEdit={canManage}
                        saving={userSaving[row.original.id] ?? false}
                        onAssign={(userId) => assignUser(row.original, userId)}
                    />
                ),
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [userMap, userSaving, activeMap, users, canManage],
    );

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Interns`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Interns"
                    description="Gencys ERP interns and their contact details."
                >
                    {canManage && (
                        <button
                            onClick={handleSync}
                            disabled={syncing}
                            className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <RefreshCw
                                className={cn(
                                    'h-4 w-4',
                                    syncing && 'animate-spin',
                                )}
                            />
                            {syncing ? 'Syncing…' : 'Sync from Gencys ERP'}
                        </button>
                    )}
                </PageHeader>

                <div className="mt-4 mb-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                    <div className="relative w-full sm:w-72">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                            placeholder="Search name, username or contact…"
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                            aria-label="Search interns"
                        />
                    </div>

                    <Select
                        value={userFilter || 'all'}
                        onValueChange={handleUserFilter}
                    >
                        <SelectTrigger className="h-9 w-[200px] font-mono text-[11px]">
                            <SelectValue placeholder="All users" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All users</SelectItem>
                            {users.map((u) => (
                                <SelectItem key={u.id} value={String(u.id)}>
                                    {u.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={statusFilter || 'all'}
                        onValueChange={handleStatusFilter}
                    >
                        <SelectTrigger className="h-9 w-[160px] font-mono text-[11px]">
                            <SelectValue placeholder="All statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            <SelectItem value="1">Active</SelectItem>
                            <SelectItem value="0">Inactive</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={interns.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(interns, ['data']) }}
                        onFetch={(params) => {
                            fetchData({
                                sort: params?.sort,
                                page: params?.page ?? 1,
                                per_page:
                                    params?.per_page ??
                                    query.perPage ??
                                    interns.per_page,
                            });
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
