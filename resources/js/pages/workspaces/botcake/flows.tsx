import AppLayout from '@/layouts/app-layout';
import { useEffect, useState } from 'react';
import axios, {AxiosResponse} from 'axios';
import { PaginatedData, RequestParams } from '@/types';
import { Flow } from '@/types/models/Botcake/Flow';
import { ColumnDef } from '@tanstack/react-table';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { Head } from '@inertiajs/react';
import ComponentCard from '@/components/common/ComponentCard';
import { omit } from 'lodash';
import { Workspace } from '@/types/models/Workspace';
import { numberFormatter, percentageFormatter } from '@/lib/utils';

const Flows = ({ workspace }: {workspace: Workspace}) => {
    const [flows, setFlows] = useState<PaginatedData<Flow> | null>(null);
    const [params, setParams] = useState<RequestParams| undefined>({
        include: 'page',
        page: 1,
    });

    useEffect(() => {
        axios.get('/api/v1/botcake/flows', {
            params,
            headers: { 'X-Workspace-Id': workspace.id }
        }).then((response: AxiosResponse<PaginatedData<Flow>>) => {
            setFlows(response.data)
        });
    }, [params, workspace.id]);

    const columns: ColumnDef<Flow>[] = [
        {
            accessorKey: 'name',
            header: ({ column }) => (
                <SortableHeader column={column} title={'ID'} />
            ),
            cell: ({ row }) => {
                return (
                    <div>
                        <p className="font-medium">{row.original.name}</p>
                        <p className="text-xs font-light text-gray-700">
                            {row.original.page?.name}
                        </p>
                    </div>
                );
            },
        },
        {
            accessorKey: 'sent',
            header: ({ column }) => (
                <SortableHeader
                    className={'w-24'}
                    column={column}
                    title={'Sent'}
                />
            ),
            cell: ({ row }) => numberFormatter(row.original.sent),
        },
        {
            accessorKey: 'total_phone_number',
            header: ({ column }) => (
                <SortableHeader
                    className={'w-24'}
                    column={column}
                    title={'Phone Number'}
                />
            ),
            cell: ({ row }) => numberFormatter(row.original.total_phone_number),
        },
        {
            accessorKey: 'success_rate',
            header: ({ column }) => (
                <SortableHeader
                    className={'w-24'}
                    column={column}
                    title={'Success Rate'}
                />
            ),
            cell: ({ row }) => percentageFormatter(row.original.success_rate ?? 0),
        },
    ];


    return (
        <AppLayout>
            <Head title={`${workspace.name} - Botcake Flows`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <h2
                        className="text-xl font-semibold text-gray-800 dark:text-white/90"
                        x-text="pageName"
                    >
                        Flows
                    </h2>
                </div>

                <div className="space-y-5 sm:space-y-6">
                    <ComponentCard desc="Manage workspace teams and their members">
                        <div>
                            <DataTable
                                columns={columns}
                                enableInternalPagination={false}
                                data={flows?.data || []}
                                meta={{ ...omit(flows, ['data']) }}
                                onFetch={(params) => {
                                    setParams((prev) => ({ ...prev, ...params }))
                                }}
                            />
                        </div>
                    </ComponentCard>
                </div>

            </div>
        </AppLayout>
    );
}

export default Flows;
