import AppLayout from '@/layouts/app-layout';
import { useEffect, useState } from 'react';
import axios, { AxiosResponse } from 'axios';
import { PaginatedData } from '@/types';
import { Campaign } from '@/types/models/AdManager';
import { ColumnDef } from '@tanstack/react-table';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { omit } from 'lodash';
import ComponentCard from '@/components/common/ComponentCard';


const Campaigns = () => {
    const [campaigns, setCampaigns] = useState<PaginatedData<Campaign> | null>()

    useEffect(() => {
        axios.get(`/api/v1/ads-manager/campaigns`, {
            params: {
                include: 'adAccount',
            },
        }).then((response: AxiosResponse<PaginatedData<Campaign>>) => {
            setCampaigns(response.data);
        });
    }, []);

    const columns: ColumnDef<Campaign>[] = [
        {
            accessorKey: 'ad_account.name',
            header: ({ column }) => (
                <SortableHeader column={column} title="Ad Account" />
            ),
        },
        {
            accessorKey: 'name',
            header: ({ column }) => (
                <SortableHeader column={column} title="Campaign" />
            ),
        },
        {
            accessorKey: 'status',
            header: ({ column }) => (
                <SortableHeader column={column} title="Status" />
            ),
        },
    ];

    return (
        <AppLayout>
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <h2
                        className="text-[22px] font-semibold tracking-tight text-gray-900 dark:text-gray-100"
                        x-text="pageName"
                    >
                        Ads Manager
                    </h2>
                </div>

                <div className="space-y-5 sm:space-y-6">
                    <ComponentCard desc="List of shop pages and their connected stores">
                        <div>
                            <DataTable
                                columns={columns}
                                enableInternalPagination={false}
                                data={campaigns?.data || []}
                                // initialSorting={initialSorting}
                                meta={{ ...omit(campaigns, ['data']) }}
                                onFetch={(params) => {
                                    console.log(params)
                                }}
                            />
                        </div>
                    </ComponentCard>
                </div>
            </div>
        </AppLayout>
    );
}

export default Campaigns;
