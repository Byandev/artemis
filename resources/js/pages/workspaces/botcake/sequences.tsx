import AppLayout from '@/layouts/app-layout';
import { useEffect, useState } from 'react';
import axios, { AxiosResponse } from 'axios';
import { PaginatedData, RequestParams } from '@/types';
import { Sequence } from '@/types/models/Botcake/Sequence';
import { ColumnDef } from '@tanstack/react-table';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { Head } from '@inertiajs/react';
import PageHeader from '@/components/common/PageHeader';
import { omit } from 'lodash';
import { Workspace } from '@/types/models/Workspace';
import { numberFormatter, percentageFormatter } from '@/lib/utils';

const Sequences = ({ workspace }: { workspace: Workspace }) => {
    const [sequences, setSequences] = useState<PaginatedData<Sequence> | null>(null);
    const [params, setParams] = useState<RequestParams | undefined>({
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
                setSequences(response.data);
            });
    }, [params, workspace.id]);

    const columns: ColumnDef<Sequence>[] = [
        {
            accessorKey: 'name',
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
            accessorKey: 'total_sent',
            header: ({ column }) => <SortableHeader className="w-28" column={column} title="Sent" />,
            cell: ({ row }) => numberFormatter(row.original.total_sent),
        },
        {
            accessorKey: 'total_phone_number',
            header: ({ column }) => <SortableHeader className="w-32" column={column} title="Phone Number" />,
            cell: ({ row }) => numberFormatter(row.original.total_phone_number),
        },
        {
            accessorKey: 'success_rate',
            header: ({ column }) => <SortableHeader className="w-28" column={column} title="Success Rate" />,
            cell: ({ row }) => percentageFormatter(row.original.success_rate),
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Botcake Sequences`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader title="Sequences" description="Schedule and manage automated message sequences" />

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={sequences?.data || []}
                        meta={{ ...omit(sequences, ['data']) }}
                        onFetch={(params) => {
                            setParams((prev) => ({ ...prev, ...params }));
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    );
};

export default Sequences;