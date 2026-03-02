import ComponentCard from '@/components/common/ComponentCard';
import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import workspaces from '@/routes/workspaces';
import { PaginatedData } from '@/types';
import { AdAccount } from '@/types/models/AdAccount';
import { Workspace } from '@/types/models/Workspace';
import { router, useForm } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import clsx from "clsx";
import { omit } from 'lodash';
import { MoreHorizontal, RefreshCw } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface AdAccountsProps {
    workspace: Workspace;
    ad_accounts: PaginatedData<AdAccount>;
    query?: {
        sort?: string | null
        perPage?: number | string
        page?: number | string
        filter?: {
            search?: string
        }
    }
}



type AdAccountStatus = {
    label: string;
    className: string;
};

const AD_ACCOUNT_STATUS: Record<number, AdAccountStatus> = {
    1: { label: "ACTIVE", className: "bg-emerald-50 text-emerald-700 ring-emerald-200" },
    2: { label: "DISABLED", className: "bg-zinc-50 text-zinc-700 ring-zinc-200" },
    3: { label: "UNSETTLED", className: "bg-amber-50 text-amber-800 ring-amber-200" },
    7: { label: "PENDING_RISK_REVIEW", className: "bg-rose-50 text-rose-700 ring-rose-200" },
    8: { label: "PENDING_SETTLEMENT", className: "bg-sky-50 text-sky-700 ring-sky-200" },
    9: { label: "IN_GRACE_PERIOD", className: "bg-indigo-50 text-indigo-700 ring-indigo-200" },
    100: { label: "PENDING_CLOSURE", className: "bg-orange-50 text-orange-700 ring-orange-200" },
    101: { label: "CLOSED", className: "bg-slate-50 text-slate-700 ring-slate-200" },
    201: { label: "ANY_ACTIVE", className: "bg-teal-50 text-teal-700 ring-teal-200" },
    202: { label: "ANY_CLOSED", className: "bg-gray-50 text-gray-700 ring-gray-200" },
};

const StatusBadge = ({ status }: { status: number }) => {
    const item =
        AD_ACCOUNT_STATUS[status] ??
        { label: `UNKNOWN (${status})`, className: "bg-red-50 text-red-700 ring-red-200" };

    return (
        <span
            className={clsx(
                "inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wide ring-1 ring-inset",
                item.className
            )}
        >
            {item.label}
        </span>
    );
}

const AdAccounts = ({ ad_accounts, workspace, query }: AdAccountsProps) => {
    const { post, processing } = useForm({});

    const initialSorting = useMemo(() => {
        return toFrontendSort(query?.sort ?? null);
    }, [query?.sort]);

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');

    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(
                workspaces.adAccounts.index({ workspace }),
                {
                    sort: query?.sort,
                    'filter[search]': searchValue || undefined,
                    page: searchValue ? 1 : query?.page ?? 1
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['ad_accounts'],
                },
            );
        }, 500);

        return () => clearTimeout(timer);
    }, [searchValue]);

    const refresh = (adAccount: AdAccount) => {
        post(workspaces.adAccounts.refresh.url({ workspace, adAccount }), {
            onSuccess: () => alert('Refresh Started'),
        });
    };

    const columns: ColumnDef<AdAccount>[] = [
        {
            accessorKey: 'id',
            header: ({ column }) => (
                <SortableHeader column={column} title={'ID'} />
            ),
        },
        {
            accessorKey: 'facebook_accounts',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Facebook'} />
            ),
            cell: ({ row }) => {
                return row.original.facebook_accounts
                    .map((f) => f.name)
                    .join(', ');
            },
        },
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title={'Name'} />
            ),
        },
        {
            accessorKey: 'currency',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Currency'} />
            ),
        },
        {
            accessorKey: 'country_code',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Country'} />
            ),
        },
        {
            accessorKey: 'status',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Status'} />
            ),
            cell: ({ row }) => <StatusBadge status={row.original.status} />,
        },
        {
            id: 'actions',
            cell: ({ row }) => {
                const adAccount = row.original;

                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="sm">
                                <MoreHorizontal className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                onClick={() => refresh(adAccount)}
                                disabled={processing}
                            >
                                <RefreshCw className={`mr-2 h-4 w-4 ${processing ? 'animate-spin' : ''}`} />
                                {processing ? 'Refreshing...' : 'Refresh Data'}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },
    ];

    return (
        <AppLayout>
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <h2
                        className="text-xl font-semibold text-gray-800 dark:text-white/90"
                        x-text="pageName"
                    >
                        Ad Accounts
                    </h2>
                </div>

                <div className="space-y-5 sm:space-y-6">
                    <ComponentCard desc="List of ad accounts of connected facebook accounts">
                        <div>
                            <div className="flex flex-col gap-2 rounded-t-xl border border-b-0 border-gray-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/[0.05]">
                                <input
                                    className="max-w-sm border w-full rounded-lg appearance-none px-4 py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:outline-hidden focus:ring-3  dark:bg-gray-900  dark:placeholder:text-white/30  bg-transparent text-gray-800 border-gray-300 focus:border-brand-300 focus:ring-brand-500/20 dark:border-gray-700 dark:text-white/90  dark:focus:border-brand-800"
                                    placeholder="Search ad account name"
                                    value={searchValue}
                                    onChange={(e) => setSearchValue(e.target.value)}
                                />
                            </div>

                            <DataTable
                                columns={columns}
                                enableInternalPagination={false}
                                data={ad_accounts.data || []}
                                initialSorting={initialSorting}
                                meta={{ ...omit(ad_accounts, ['data']) }}
                                onFetch={(params) => {
                                    router.get(
                                        workspaces.adAccounts.index({ workspace }),
                                        {
                                            sort: params?.sort,
                                            'filter[search]': searchValue || undefined,
                                            page: params?.page ?? 1
                                        },
                                        {
                                            preserveState: false,
                                            replace: true,
                                            preserveScroll: true,
                                        },
                                    );
                                }}
                            />
                        </div>
                    </ComponentCard>
                </div>
            </div>
        </AppLayout>
    );
};

export default AdAccounts;
