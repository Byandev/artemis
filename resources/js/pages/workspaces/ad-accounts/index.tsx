import AppLayout from '@/layouts/app-layout';
import { DataTable } from '@/components/ui/data-table';
import { ColumnDef } from '@tanstack/react-table';
import { Workspace } from '@/types/models/Workspace';
import { usePage } from '@inertiajs/react';
import { SharedData } from '@/types';
import { AdAccount } from '@/types/models/AdAccount';

interface AdAccountsProps {
    workspace: Workspace;
    userWorkspaces: Workspace[];
    ad_accounts: {
        data: AdAccount[];
    };
}

const FacebookAccounts = ({
    ad_accounts,
    userWorkspaces,
    workspace,
}: AdAccountsProps) => {
    const { auth } = usePage<SharedData>().props;
    console.log(ad_accounts.data[0])

    const columns: ColumnDef<AdAccount>[] = [
        {
            accessorKey: 'id',
            header: 'ID',
        },
        {
            accessorKey: 'facebook_accounts',
            header: 'Facebook',
            cell: ({ row }) => {
                return row.original.facebook_accounts.map(f => f.name).join(", ")
            }
        },
        {
            accessorKey: 'name',
            header: 'Name',
        },
        {
            accessorKey: 'currency',
            header: 'Currency',
        },
        {
            accessorKey: 'country_code',
            header: 'Country',
        },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }) => {
                switch (row.original.status) {
                    case 1:
                        return 'ACTIVE'
                    case 2:
                        return 'DISABLED'
                    case 3:
                        return 'UNSETTLED'
                    case 7:
                        return 'PENDING_RISK_REVIEW'
                    case 8:
                        return 'PENDING_SETTLEMENT'
                    case 9:
                        return 'IN_GRACE_PERIOD'
                    case 100:
                        return 'PENDING_CLOSURE'
                    case 101:
                        return 'CLOSED'
                    case 201:
                        return 'ANY_ACTIVE'
                    case 202:
                        return 'ANY_CLOSED'
                }

                return row.original.status
            }
        },
    ];


    return (
        <AppLayout workspaces={userWorkspaces} currentWorkspace={workspace}>
            <div className="px-4 py-6">
                <div>
                    <DataTable
                        columns={columns}
                        data={ad_accounts.data || []}
                    />
                </div>
            </div>
        </AppLayout>
    );
};

export default FacebookAccounts;
