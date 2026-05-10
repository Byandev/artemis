import ComponentCard from '@/components/common/ComponentCard'
import PageHeader from '@/components/common/PageHeader'
import { Button } from '@/components/ui/button'
import { DataTable, SortableHeader } from '@/components/ui/data-table'
import AppLayout from '@/layouts/app-layout'
import { Workspace } from '@/types/models/Workspace'
import { Head, router } from '@inertiajs/react'
import { ColumnDef } from '@tanstack/react-table'
import { Facebook, RefreshCw } from 'lucide-react'

interface MetaAdAccount {
    id: string
    name: string
    currency: string | null
    country_code: string | null
    account_status: number | null
    business_name: string | null
    last_synced_at: string | null
}

interface MetaUser {
    id: string
    name: string
    email: string | null
    token_expires_at: string | null
    last_synced_at: string | null
    ad_accounts: MetaAdAccount[]
}

interface Props {
    workspace: Workspace
    metaUsers: MetaUser[]
}

const adAccountColumns: ColumnDef<MetaAdAccount>[] = [
    {
        accessorKey: 'name',
        header: ({ column }) => <SortableHeader column={column} title="Account" enabled={false} />,
        cell: ({ row }) => <span className="font-medium">{row.original.name}</span>,
    },
    {
        accessorKey: 'id',
        header: ({ column }) => <SortableHeader column={column} title="Account ID" enabled={false} />,
        cell: ({ row }) => <span className="font-mono text-xs">{row.original.id}</span>,
    },
    {
        accessorKey: 'business_name',
        header: ({ column }) => <SortableHeader column={column} title="Business" enabled={false} />,
        cell: ({ row }) => row.original.business_name ?? '—',
    },
    {
        accessorKey: 'currency',
        header: ({ column }) => <SortableHeader column={column} title="Currency" enabled={false} />,
        cell: ({ row }) => row.original.currency ?? '—',
    },
    {
        accessorKey: 'country_code',
        header: ({ column }) => <SortableHeader column={column} title="Country" enabled={false} />,
        cell: ({ row }) => row.original.country_code ?? '—',
    },
    {
        accessorKey: 'last_synced_at',
        header: ({ column }) => <SortableHeader column={column} title="Last synced" enabled={false} />,
        cell: ({ row }) =>
            row.original.last_synced_at
                ? new Date(row.original.last_synced_at).toLocaleString()
                : '—',
    },
]

export default function MetaIntegrations({ workspace, metaUsers }: Props) {
    const connectUrl = `/workspaces/${workspace.slug}/integrations/meta/connect`

    const sync = (metaUserId: string) => {
        router.post(
            `/workspaces/${workspace.slug}/integrations/meta/users/${metaUserId}/sync-ad-accounts`,
            {},
            { preserveScroll: true },
        )
    }

    return (
        <AppLayout>
            <Head title="Meta Integration" />

            <PageHeader
                title="Meta Integration"
                description="Connect your Meta (Facebook) account to sync ad accounts, campaigns, and insights."
            />

            <ComponentCard title="Connected Meta accounts">
                {metaUsers.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No Meta account connected yet. Connect one to start
                        syncing ads data.
                    </p>
                ) : (
                    <div className="space-y-6">
                        {metaUsers.map((user) => (
                            <div key={user.id} className="rounded-lg border">
                                <div className="flex items-start justify-between p-4">
                                    <div>
                                        <p className="font-medium">{user.name}</p>
                                        {user.email && (
                                            <p className="text-sm text-muted-foreground">
                                                {user.email}
                                            </p>
                                        )}
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {user.last_synced_at
                                                ? `Last synced ${new Date(user.last_synced_at).toLocaleString()}`
                                                : 'Never synced'}
                                            {user.token_expires_at &&
                                                ` · Token expires ${new Date(user.token_expires_at).toLocaleDateString()}`}
                                        </p>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => sync(user.id)}
                                    >
                                        <RefreshCw className="mr-2 h-4 w-4" />
                                        Sync ad accounts
                                    </Button>
                                </div>

                                <DataTable
                                    columns={adAccountColumns}
                                    data={user.ad_accounts}
                                />
                            </div>
                        ))}
                    </div>
                )}

                <div className="mt-6">
                    <Button asChild>
                        <a href={connectUrl}>
                            <Facebook className="mr-2 h-4 w-4" />
                            Connect Meta account
                        </a>
                    </Button>
                </div>
            </ComponentCard>
        </AppLayout>
    )
}
