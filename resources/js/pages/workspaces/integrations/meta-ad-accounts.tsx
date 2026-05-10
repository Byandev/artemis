import PageHeader from '@/components/common/PageHeader'
import { Button } from '@/components/ui/button'
import { DataTable, SortableHeader } from '@/components/ui/data-table'
import AppLayout from '@/layouts/app-layout'
import { toFrontendSort } from '@/lib/sort'
import { PaginatedData } from '@/types'
import { Workspace } from '@/types/models/Workspace'
import { Head, router } from '@inertiajs/react'
import { ColumnDef } from '@tanstack/react-table'
import clsx from 'clsx'
import { omit } from 'lodash'
import { Activity, CheckCircle2, Database, Facebook, RefreshCw, Search } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'

interface AdAccount {
    id: number
    name: string
    business_name: string | null
    currency: string | null
    country_code: string | null
    account_status: number | null
    last_synced_at: string | null
    meta_users?: { id: number; name: string }[]
}

interface Props {
    workspace: Workspace
    adAccounts: PaginatedData<AdAccount>
    query?: {
        sort?: string | null
        perPage?: number | string
        page?: number | string
        filter?: { search?: string }
    }
}

const ACCOUNT_STATUS: Record<number, { label: string; cls: string; dot: string }> = {
    1: { label: 'Active', cls: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400', dot: 'bg-emerald-500' },
    2: { label: 'Disabled', cls: 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400', dot: 'bg-red-400' },
    3: { label: 'Unsettled', cls: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400', dot: 'bg-amber-400' },
    7: { label: 'Pending Risk Review', cls: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400', dot: 'bg-amber-400' },
    8: { label: 'Pending Settlement', cls: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400', dot: 'bg-amber-400' },
    9: { label: 'In Grace Period', cls: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400', dot: 'bg-amber-400' },
    100: { label: 'Pending Closure', cls: 'bg-stone-100 text-stone-600 dark:bg-zinc-800 dark:text-zinc-400', dot: 'bg-stone-400' },
    101: { label: 'Closed', cls: 'bg-stone-100 text-stone-600 dark:bg-zinc-800 dark:text-zinc-400', dot: 'bg-stone-400' },
}

function StatusBadge({ status }: { status: number | null }) {
    if (status == null) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full bg-stone-100 px-2 py-0.5 font-mono text-[10px] uppercase tracking-wide text-stone-500 dark:bg-zinc-800 dark:text-zinc-500">
                <span className="h-1 w-1 rounded-full bg-stone-300" />
                Unknown
            </span>
        )
    }
    const cfg = ACCOUNT_STATUS[status] ?? {
        label: `Status ${status}`,
        cls: 'bg-stone-100 text-stone-600 dark:bg-zinc-800 dark:text-zinc-400',
        dot: 'bg-stone-400',
    }
    return (
        <span className={clsx('inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 font-mono text-[10px] uppercase tracking-wide', cfg.cls)}>
            <span className={clsx('h-1 w-1 rounded-full', cfg.dot)} />
            {cfg.label}
        </span>
    )
}

function formatRelative(ts: string | null) {
    if (!ts) return 'Never'
    const d = new Date(ts)
    const diff = (Date.now() - d.getTime()) / 1000
    if (diff < 60) return `${Math.round(diff)}s ago`
    if (diff < 3600) return `${Math.round(diff / 60)}m ago`
    if (diff < 86400) return `${Math.round(diff / 3600)}h ago`
    return `${Math.round(diff / 86400)}d ago`
}

function StatCard({
    label,
    value,
    icon: Icon,
}: {
    label: string
    value: string | number
    icon: typeof Database
}) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-5 transition-colors hover:border-black/10 dark:border-white/6 dark:bg-zinc-900 dark:hover:border-white/10">
            <div className="flex items-start justify-between">
                <div>
                    <p className="font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                        {label}
                    </p>
                    <p className="mt-2 text-2xl font-semibold tracking-tight text-gray-700 dark:text-gray-200">
                        {value}
                    </p>
                </div>
                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-400">
                    <Icon className="h-4 w-4" />
                </div>
            </div>
        </div>
    )
}

export default function MetaAdAccounts({ workspace, adAccounts, query }: Props) {
    const indexUrl = `/workspaces/${workspace.slug}/integrations/meta/ad-accounts`

    const initialSorting = useMemo(() => toFrontendSort(query?.sort ?? null), [query?.sort])
    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '')

    useEffect(() => {
        const t = setTimeout(() => {
            router.get(
                indexUrl,
                {
                    sort: query?.sort,
                    'filter[search]': searchValue || undefined,
                    page: searchValue ? 1 : (query?.page ?? 1),
                    per_page: query?.perPage ?? adAccounts.per_page,
                },
                { preserveState: true, replace: true, preserveScroll: true, only: ['adAccounts'] },
            )
        }, 400)
        return () => clearTimeout(t)
    }, [searchValue]) // eslint-disable-line react-hooks/exhaustive-deps

    const activeCount = adAccounts.data.filter((a) => a.account_status === 1).length
    const lastSynced = adAccounts.data
        .map((a) => a.last_synced_at)
        .filter(Boolean)
        .sort()
        .reverse()[0] ?? null

    const columns: ColumnDef<AdAccount>[] = [
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Account" />,
            cell: ({ row }) => (
                <div className="flex flex-col gap-0.5">
                    <span className="text-[12px] font-medium text-gray-700 dark:text-gray-200">
                        {row.original.name}
                    </span>
                    {row.original.business_name && (
                        <span className="text-[11px] text-gray-400 dark:text-gray-500">
                            {row.original.business_name}
                        </span>
                    )}
                    <span className="font-mono text-[10px] text-gray-300 dark:text-gray-600">
                        {row.original.id}
                    </span>
                </div>
            ),
        },
        {
            accessorKey: 'account_status',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Status" />,
            cell: ({ row }) => <StatusBadge status={row.original.account_status} />,
        },
        {
            accessorKey: 'currency',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Currency" />,
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                    {row.original.currency ?? '—'}
                </span>
            ),
        },
        {
            accessorKey: 'country_code',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Country" />,
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                    {row.original.country_code ?? '—'}
                </span>
            ),
        },
        {
            id: 'meta_users',
            header: ({ column }) => <SortableHeader column={column} title="Connected via" enabled={false} />,
            cell: ({ row }) => {
                const users = row.original.meta_users ?? []
                if (users.length === 0) return <span className="text-gray-300 dark:text-gray-600">—</span>
                return (
                    <div className="flex flex-wrap gap-1">
                        {users.map((u) => (
                            <span
                                key={u.id}
                                className="inline-flex items-center gap-1 rounded-md bg-stone-100 px-1.5 py-0.5 text-[10px] text-gray-600 dark:bg-zinc-800 dark:text-gray-400"
                            >
                                <Facebook className="h-2.5 w-2.5" />
                                {u.name}
                            </span>
                        ))}
                    </div>
                )
            },
        },
        {
            accessorKey: 'last_synced_at',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Last Synced" />,
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                    {formatRelative(row.original.last_synced_at)}
                </span>
            ),
        },
    ]

    return (
        <AppLayout>
            <Head title="Meta Ads · Ad Accounts" />

            <div className="w-full space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Ad Accounts"
                    description="All Meta ad accounts visible through your connected Facebook users."
                >
                </PageHeader>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <StatCard label="Total Ad Accounts" value={adAccounts.total} icon={Database} />
                    <StatCard label="Active (this page)" value={activeCount} icon={CheckCircle2} />
                    <StatCard label="Last Synced" value={formatRelative(lastSynced)} icon={RefreshCw} />
                </div>

                <div className="flex items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            type="text"
                            placeholder="Search ad accounts..."
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                        />
                    </div>
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={adAccounts.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(adAccounts, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                indexUrl,
                                {
                                    sort: params?.sort,
                                    'filter[search]': searchValue || undefined,
                                    page: params?.page ?? 1,
                                    per_page: params?.per_page ?? query?.perPage ?? adAccounts.per_page,
                                },
                                { preserveState: true, replace: true, preserveScroll: true },
                            )
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    )
}
