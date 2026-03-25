import ComponentCard from '@/components/common/ComponentCard';
import PageHeader from '@/components/common/PageHeader';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import workspaces from '@/routes/workspaces';
import { PaginatedData, SharedData } from '@/types';
import { FacebookAccount } from '@/types/models/FacebookAccount';
import { Workspace } from '@/types/models/Workspace';
import { router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import moment from 'moment';
import { useCallback, useEffect, useMemo, useState } from 'react';

interface FacebookAccountsProps {
    workspace: Workspace;
    facebook_accounts: PaginatedData<FacebookAccount>,
    query?: {
        sort?: string | null
        perPage?: number | string
        page?: number | string
        filter?: {
            search?: string
        }
    }
}

const FacebookAccounts = ({
    facebook_accounts,
    workspace,
    query
}: FacebookAccountsProps) => {
    const { auth } = usePage<SharedData>().props;

    const initialSorting = useMemo(() => {
        return toFrontendSort(query?.sort ?? null);
    }, [query?.sort]);

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');

    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(
                workspaces.facebookAccounts.index({ workspace }),
                {
                    sort: query?.sort,
                    'filter[search]': searchValue || undefined,
                    page: searchValue ? 1 : query?.page ?? 1
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['facebook_accounts'],
                },
            );
        }, 500);

        return () => clearTimeout(timer);
    }, [searchValue]);

    const columns: ColumnDef<FacebookAccount>[] = [
        {
            accessorKey: 'name',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Name'} />
            ),
            cell: ({ row }) => {
                return <div className="flex items-center gap-2">
                    <Avatar>
                        <AvatarImage src={row.original.picture_url} alt={row.original.name} />
                        <AvatarFallback className="bg-brand-500 text-white">{row.original.name[0]}</AvatarFallback>
                    </Avatar>
                    <span>{row.original.name}</span>
                </div>
            }
        },
        {
            accessorKey: 'created_at',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Connected At'} />
            ),
            cell: ({ row }) => moment(row.original.created_at).format('MMM D, YYYY h:mm:ss A'),
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
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader title="Facebook Accounts" description="Connect and manage your Facebook business accounts">
                    <button onClick={connectFacebookAccount} className="bg-brand-500 text-white px-4 py-2 rounded-xl font-medium text-theme-sm">
                        Connect Facebook Account
                    </button>
                </PageHeader>

                <div className="space-y-5 sm:space-y-6">
                    <ComponentCard desc="List of  connected facebook accounts">
                        <div>
                            <div className="flex flex-col gap-2 rounded-t-xl border border-b-0 border-gray-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/[0.05]">
                                <input
                                    className="max-w-sm border w-full rounded-lg appearance-none px-4 py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:outline-hidden focus:ring-3  dark:bg-gray-900  dark:placeholder:text-white/30  bg-transparent text-gray-800 border-gray-300 focus:border-brand-300 focus:ring-brand-500/20 dark:border-gray-700 dark:text-white/90  dark:focus:border-brand-800"
                                    placeholder="Search facebook account name"
                                    value={searchValue}
                                    onChange={(e) => setSearchValue(e.target.value)}
                                />
                            </div>

                            <DataTable
                                columns={columns}
                                enableInternalPagination={false}
                                data={facebook_accounts.data || []}
                                initialSorting={initialSorting}
                                meta={{ ...omit(facebook_accounts, ['data']) }}
                                onFetch={(params) => {
                                    router.get(
                                        workspaces.facebookAccounts.index({ workspace }),
                                        {
                                            sort: params?.sort,
                                            'filter[search]': searchValue || undefined,
                                            page: params?.page ?? 1
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
                    </ComponentCard>
                </div>
            </div>
        </AppLayout>
    );
};

export default FacebookAccounts;
