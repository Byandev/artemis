import PageHeader from '@/components/common/PageHeader';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { Switch } from '@/components/ui/switch';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { cn } from '@/lib/utils';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
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
    active: boolean;
}

interface Props {
    workspace: Workspace;
    interns: PaginatedData<Intern>;
    companies: string[];
    query: {
        sort: string;
        perPage: number | null;
        filter: { search?: string; active?: string };
    };
}

export default function GencysInternsIndex({
    workspace,
    interns,
    query,
}: Props) {
    const { flash } = usePage().props as {
        flash?: { success?: string; error?: string };
    };
    const canSync = usePermission(PERMISSIONS.ViewGencysInterns);

    const [searchValue, setSearchValue] = useState(query.filter?.search ?? '');
    const [activeOnly, setActiveOnly] = useState(query.filter?.active === '1');
    const [syncing, setSyncing] = useState(false);

    const baseUrl = `/workspaces/${workspace.slug}/gencys/interns`;

    // Shared filter payload so search, the status filter, and paging agree.
    const filterParams = (
        over: { search?: string; activeOnly?: boolean } = {},
    ) => ({
        search: (over.search ?? searchValue) || undefined,
        active: (over.activeOnly ?? activeOnly) ? 1 : undefined,
    });

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);

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

    const initialSorting = useMemo(
        () => toFrontendSort(query.sort ?? null),
        [query.sort],
    );

    // Single source of truth for the request the table/filters issue.
    const fetchData = (overrides: Record<string, unknown> = {}) => {
        router.get(
            baseUrl,
            {
                filter: filterParams(),
                sort: query.sort,
                per_page: query.perPage ?? interns.per_page ?? undefined,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const handleActiveFilter = (next: boolean) => {
        setActiveOnly(next);
        fetchData({ page: 1, filter: filterParams({ activeOnly: next }) });
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

    const columns = useMemo<ColumnDef<Intern>[]>(
        () => [
            {
                accessorKey: 'active',
                enableSorting: true,
                meta: { headerClassName: 'w-16', cellClassName: 'w-16' },
                header: ({ column }) => (
                    <SortableHeader column={column} title="Status" />
                ),
                cell: ({ row }) => (
                    <InternActiveToggle
                        baseUrl={baseUrl}
                        intern={row.original}
                    />
                ),
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
        ],
        [baseUrl],
    );

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Interns`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Interns"
                    description="Gencys ERP interns and their contact details."
                >
                    {canSync && (
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

                    <div className="inline-flex h-9 items-center rounded-[10px] border border-black/6 bg-stone-100 p-0.5 dark:border-white/6 dark:bg-zinc-800">
                        {(
                            [
                                { label: 'All', value: false },
                                { label: 'Active only', value: true },
                            ] as const
                        ).map((opt) => (
                            <button
                                key={opt.label}
                                type="button"
                                onClick={() => handleActiveFilter(opt.value)}
                                className={cn(
                                    'h-8 rounded-[8px] px-3 font-mono! text-[12px]! font-medium transition-all',
                                    activeOnly === opt.value
                                        ? 'bg-white text-gray-800 shadow-sm dark:bg-zinc-700 dark:text-gray-100'
                                        : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200',
                                )}
                            >
                                {opt.label}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={interns.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(interns, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                baseUrl,
                                {
                                    sort: params?.sort,
                                    filter: filterParams(),
                                    page: params?.page ?? 1,
                                    per_page:
                                        params?.per_page ??
                                        query.perPage ??
                                        interns.per_page,
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
            </div>
        </AppLayout>
    );
}

/**
 * Active/inactive toggle for one intern. Optimistically flips the switch, then
 * PATCHes the server; the fresh server prop reconciles it on reload.
 */
function InternActiveToggle({
    baseUrl,
    intern,
}: {
    baseUrl: string;
    intern: Intern;
}) {
    const [active, setActive] = useState(intern.active);
    const [saving, setSaving] = useState(false);

    // Keep in sync when server data changes (e.g. paging/sorting reloads).
    useEffect(() => setActive(intern.active), [intern.active]);

    const toggle = (next: boolean) => {
        setActive(next);
        router.patch(
            `${baseUrl}/${intern.id}/toggle-active`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
                onError: () => {
                    setActive(!next);
                    toast.error('Failed to update status.');
                },
            },
        );
    };

    return (
        <Switch
            checked={active}
            disabled={saving}
            onCheckedChange={toggle}
            aria-label="Toggle active status"
        />
    );
}
