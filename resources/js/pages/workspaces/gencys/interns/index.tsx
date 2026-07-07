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
    company_name: string | null;
    username: string | null;
    contact_number_email: string | null;
}

interface Props {
    workspace: Workspace;
    interns: PaginatedData<Intern>;
    companies: string[];
    query: {
        sort: string;
        perPage: number | null;
        filter: { search?: string; company_name?: string };
    };
}

const ALL_COMPANIES = '__all__';

export default function GencysInternsIndex({
    workspace,
    interns,
    companies,
    query,
}: Props) {
    const { flash } = usePage().props as {
        flash?: { success?: string; error?: string };
    };
    const canSync = usePermission(PERMISSIONS.ViewGencysInterns);

    const [searchValue, setSearchValue] = useState(query.filter?.search ?? '');
    const [company, setCompany] = useState(
        query.filter?.company_name ?? ALL_COMPANIES,
    );
    const [syncing, setSyncing] = useState(false);

    const baseUrl = `/workspaces/${workspace.slug}/gencys/interns`;

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
                filter: {
                    search: searchValue || undefined,
                    company_name:
                        company === ALL_COMPANIES ? undefined : company,
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

    const handleCompanyChange = (value: string) => {
        setCompany(value);
        router.get(
            baseUrl,
            {
                filter: {
                    search: searchValue || undefined,
                    company_name: value === ALL_COMPANIES ? undefined : value,
                },
                sort: query.sort,
                per_page: query.perPage ?? interns.per_page ?? undefined,
                page: 1,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const columns = useMemo<ColumnDef<Intern>[]>(
        () => [
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
                accessorKey: 'company_name',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader column={column} title="Company Name" />
                ),
                cell: ({ row }) => (
                    <span className="text-[12px] text-gray-600 dark:text-gray-400">
                        {row.original.company_name ?? '—'}
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
                accessorKey: 'contact_number_email',
                enableSorting: true,
                header: ({ column }) => (
                    <SortableHeader
                        column={column}
                        title="Contact Number / Email"
                    />
                ),
                cell: ({ row }) => (
                    <span className="text-[12px] text-gray-600 dark:text-gray-400">
                        {row.original.contact_number_email ?? '—'}
                    </span>
                ),
            },
        ],
        [],
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
                            placeholder="Search name, company, username or contact…"
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                            aria-label="Search interns"
                        />
                    </div>

                    <Select value={company} onValueChange={handleCompanyChange}>
                        <SelectTrigger className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 px-3 font-mono! text-[12px]! sm:w-56 dark:border-white/6 dark:bg-zinc-800">
                            <SelectValue placeholder="All companies" />
                        </SelectTrigger>
                        <SelectContent className="max-h-72">
                            <SelectItem
                                value={ALL_COMPANIES}
                                className="font-mono! text-[12px]!"
                            >
                                All companies
                            </SelectItem>
                            {companies.map((name) => (
                                <SelectItem
                                    key={name}
                                    value={name}
                                    className="font-mono! text-[12px]!"
                                >
                                    {name}
                                </SelectItem>
                            ))}
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
                            router.get(
                                baseUrl,
                                {
                                    sort: params?.sort,
                                    filter: {
                                        search: searchValue || undefined,
                                        company_name:
                                            company === ALL_COMPANIES
                                                ? undefined
                                                : company,
                                    },
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
