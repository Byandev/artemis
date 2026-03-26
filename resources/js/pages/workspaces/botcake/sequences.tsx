import AppLayout from '@/layouts/app-layout';
import { useEffect, useState } from 'react';
import axios, {AxiosResponse} from 'axios';
import { PaginatedData } from '@/types';
import { ColumnDef } from '@tanstack/react-table';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { Head } from '@inertiajs/react';
import ComponentCard from '@/components/common/ComponentCard';
import PageHeader from '@/components/common/PageHeader';
import { omit } from 'lodash';
import { Workspace } from '@/types/models/Workspace';
import { numberFormatter, percentageFormatter } from '@/lib/utils';
import { Sequence } from '@/types/models/Botcake/Sequence';

const Sequences = ({ workspace }: {workspace: Workspace}) => {
    const [flows, setFlows] = useState<PaginatedData<Sequence> | null>(null);
    const [params, setParams] = useState<
        | {
        [key: string]: string | number | null;
    }
        | undefined
    >({
        include: 'page',
        page: 1,
    });

    useEffect(() => {
        axios
            .get('/api/v1/botcake/sequences', {
                params,
                headers: { 'X-Workspace-Id': workspace.id },
            })
            .then((response: AxiosResponse<PaginatedData<Sequence>>) => {
                setFlows(response.data);
            });
    }, [params, workspace.id]);

    const columns: ColumnDef<Sequence>[] = [
        {
            accessorKey: 'name',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Name'} />
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
            accessorKey: 'total_sent',
            header: ({ column }) => (
                <SortableHeader
                    className="w-24"
                    column={column}
                    title={'Sent'}
                />
            ),
            cell: ({ row }) => numberFormatter(row.original.total_sent),
        },
        {
            accessorKey: 'total_phone_number',
            header: ({ column }) => (
                <SortableHeader
                    className="w-24"
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
                    className="w-24"
                    column={column}
                    title={'Success Rate'}
                />
            ),
            cell: ({ row }) => percentageFormatter(row.original.success_rate),
        },
    ];


    return (
        <AppLayout>
            <Head title={`${workspace.name} - Botcake Flows`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader title="Sequences" description="Schedule and manage automated message sequences" />

                <div className="space-y-5 sm:space-y-6">
                    <ComponentCard desc="Manage workspace teams and their members">
                        <div>
                            <DataTable
                                columns={columns}
                                enableInternalPagination={false}
                                data={flows?.data || []}
                                meta={{ ...omit(flows, ['data']) }}
                                onFetch={(params) => {
                                    setParams((prev) => ({ ...prev, ...params}))
                                }}
                            />
                        </div>
                    </ComponentCard>
                </div>

            </div>
        </AppLayout>
    );
}

export default Sequences;
