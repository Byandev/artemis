import PageHeader from '@/components/common/PageHeader';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { numberFormatter, percentageFormatter } from '@/lib/utils';
import { PaginatedData } from '@/types';
import { Flow } from '@/types/models/Botcake/Flow';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import { Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface Props {
    workspace: Workspace;
    flows: PaginatedData<Flow>;
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: { search?: string };
    };
}

export default function Flows({ workspace, flows, query }: Props) {
    const initialSorting = useMemo(() => toFrontendSort(query?.sort ?? null), [query?.sort]);
    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');

    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(
                `/workspaces/${workspace.slug}/botcake/flows`,
                {
                    sort: query?.sort,
                    'filter[search]': searchValue || undefined,
                    page: searchValue ? 1 : query?.page ?? 1,
                },
                { preserveState: true, replace: true, preserveScroll: true, only: ['flows'] },
            );
        }, 500);
        return () => clearTimeout(timer);
    }, [searchValue]);

    const columns: ColumnDef<Flow>[] = [
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Name" />,
            cell: ({ row }) => (
                <div>
                    <p className="font-medium text-gray-900 dark:text-gray-100">{row.original.name}</p>
                    <p className="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                        {row.original.page?.name ?? '-'}
                    </p>
                </div>
            ),
        },
        {
            accessorKey: 'sent',
            header: ({ column }) => <SortableHeader className="w-28" column={column} title="Sent" />,
            cell: ({ row }) => numberFormatter(row.original.sent),
        },
        {
            accessorKey: 'total_phone_number',
            header: ({ column }) => <SortableHeader className="w-32" column={column} title="Phone Number" />,
            cell: ({ row }) => numberFormatter(row.original.total_phone_number),
        },
        {
            accessorKey: 'success_rate',
            header: ({ column }) => <SortableHeader className="w-28" column={column} title="Success Rate" />,
            cell: ({ row }) => percentageFormatter(row.original.success_rate ?? 0),
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Botcake Flows`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader title="Flows" description="Automate customer interactions with messenger flows" />

                <div className="mb-3 flex items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pl-8 pr-3 font-mono! text-[12px]! text-gray-800 outline-none transition-all placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                            placeholder="Search flows…"
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                        />
                    </div>
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={flows.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(flows, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                `/workspaces/${workspace.slug}/botcake/flows`,
                                {
                                    sort: params?.sort,
                                    'filter[search]': searchValue || undefined,
                                    page: params?.page ?? 1,
                                    per_page: params?.per_page,
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
