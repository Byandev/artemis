import AppLayout from '@/layouts/app-layout';
import { DataTable } from '@/components/ui/data-table';
import { ColumnDef } from '@tanstack/react-table';
import { Button } from '@/components/ui/button';
import { Workspace } from '@/types/models/Workspace';
import { FacebookAccount } from '@/types/models/FacebookAccount';
import { useCallback } from 'react';
import { usePage } from '@inertiajs/react';
import { SharedData } from '@/types';

interface FacebookAccountsProps {
    workspace: Workspace;
    facebook_accounts: {
        data: FacebookAccount[];
    };
}

const FacebookAccounts = ({
    facebook_accounts,
    workspace,
}: FacebookAccountsProps) => {
    const { auth } = usePage<SharedData>().props;

    const columns: ColumnDef<FacebookAccount>[] = [
        {
            accessorKey: 'id',
            header: 'ID',
        },
        {
            accessorKey: 'name',
            header: 'Name',
        },
    ];

    const connectFacebookAccount = useCallback(() => {
        const state = {
            auth_id: auth.user.id,
            workspace_id: workspace.id,
            time: Math.floor(Date.now() / 1000),
        };

        const query = new URLSearchParams({
            client_id: import.meta.env.VITE_FACEBOOK_CLIENT_ID,
            redirect_uri: import.meta.env.VITE_FACEBOOK_REDIRECT_URI,
            scope: 'email,public_profile,pages_show_list,ads_management,ads_read,business_management,pages_manage_ads',
            response_type: 'code',
            auth_type: 'rerequest',
            config_id: import.meta.env.VITE_FACEBOOK_CONFIG_ID,
            state: JSON.stringify(state),
        }).toString();

        window.location.href = `https://www.facebook.com/v22.0/dialog/oauth?${query}`;
    }, [auth.user.id, workspace.id]);

    return (
        <AppLayout>
            <div className="px-4 py-6">
                <div className="mb-4">
                    <Button size="sm" onClick={connectFacebookAccount}>
                        Connect Facebook Account
                    </Button>
                </div>

                <div>
                    <DataTable
                        columns={columns}
                        data={facebook_accounts.data || []}
                    />
                </div>
            </div>
        </AppLayout>
    );
};

export default FacebookAccounts;
