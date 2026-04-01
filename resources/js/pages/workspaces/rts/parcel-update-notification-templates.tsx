import AppLayout from '@/layouts/app-layout';
import PageHeader from '@/components/common/PageHeader';
import { DataTable } from '@/components/ui/data-table';
import { Workspace } from '@/types/models/Workspace';
import { useState } from 'react';
import { ParcelJourneyNotificationTemplate } from '@/types/models/ParcelJourneyNotificationTemplate';
import { ColumnDef } from '@tanstack/react-table';
import { omit, startCase } from 'lodash';
import { Button } from '@/components/ui/button';
import TemplateForm from '@/components/rts/template-form';
import { Head, router } from '@inertiajs/react';
import { PaginatedData } from '@/types';

type Props = {
    workspace: Workspace;
    templates: PaginatedData<ParcelJourneyNotificationTemplate>;
}

const ParcelUpdateNotificationTemplates = ({ workspace, templates }: Props) => {
    const [openForm, setOpenForm] = useState(false);
    const [selected, setSelected] = useState<ParcelJourneyNotificationTemplate | undefined>(undefined);

    const columns: ColumnDef<ParcelJourneyNotificationTemplate>[] = [
        {
            accessorKey: 'type',
            header: 'Type',
            cell: ({ row }) => (
                <span className="text-[12px] font-medium text-gray-800 dark:text-gray-200">
                    {startCase(row.original.type)}
                </span>
            ),
        },
        {
            accessorKey: 'activity',
            header: 'Activity',
            cell: ({ row }) => (
                <span className="inline-flex items-center rounded-full bg-stone-100 px-2.5 py-1 font-mono text-[11px] font-medium text-gray-600 dark:bg-zinc-800 dark:text-gray-400">
                    {startCase(row.original.activity)}
                </span>
            ),
        },
        {
            accessorKey: 'receiver',
            header: 'Receiver',
            cell: ({ row }) => (
                <span className="inline-flex items-center rounded-full bg-stone-100 px-2.5 py-1 font-mono text-[11px] font-medium text-gray-600 dark:bg-zinc-800 dark:text-gray-400">
                    {startCase(row.original.receiver)}
                </span>
            ),
        },
        {
            accessorKey: 'message',
            header: 'Message',
            cell: ({ row }) => (
                <div className="truncate max-w-3xl font-mono text-[11px] text-gray-500 dark:text-gray-400">
                    {row.original.message}
                </div>
            ),
        },
        {
            id: 'actions',
            cell: ({ row }) => (
                <div className="flex justify-end">
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => {
                            setSelected(row.original);
                            setOpenForm(true);
                        }}
                        className="h-7 cursor-pointer font-mono! text-[11px]!"
                    >
                        Edit
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} — Parcel Journey Templates`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Parcel Journey Templates"
                    description="Modify the template of the messages for your parcel journey"
                />

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={templates.data || []}
                        enableInternalPagination={false}
                        meta={{ ...omit(templates, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                `/workspaces/${workspace.slug}/rts/parcel-journeys`,
                                { page: params?.page ?? 1 },
                                { preserveState: true, replace: true, preserveScroll: true, only: ['templates'] },
                            );
                        }}
                    />
                </div>

                <TemplateForm
                    open={openForm}
                    onOpenChange={(open) => {
                        setOpenForm(open);
                        if (!open) setSelected(undefined);
                    }}
                    workspace={workspace}
                    initialValue={selected}
                />
            </div>
        </AppLayout>
    );
};

export default ParcelUpdateNotificationTemplates;
