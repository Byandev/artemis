import AppLayout from '@/layouts/app-layout';
import RtsNavigation from '@/pages/workspaces/rts/partials/RtsNavigation';
import { Workspace } from '@/types/models/Workspace';
import { ChangeEvent, useRef, useState } from 'react';
import { ParcelJourneyNotificationTemplate } from '@/types/models/ParcelJourneyNotificationTemplate';
import { ColumnDef } from '@tanstack/react-table';
import { DataTable } from '@/components/ui/data-table';
import { startCase } from 'lodash'
import { Button } from '@/components/ui/button';
import TemplateForm from '@/components/rts/template-form';

type Props = {
    workspace: Workspace;
    templates: ParcelJourneyNotificationTemplate[]
}

const ParcelUpdateNotificationTemplates = ({ workspace, templates }: Props) => {
    const [openForm, setOpenForm] = useState(false);
    const [selected, setSelected] = useState<ParcelJourneyNotificationTemplate | undefined>(undefined);

    const columns: ColumnDef<ParcelJourneyNotificationTemplate>[] = [
        {
            accessorKey: 'type',
            header: 'Type',
            cell: ({ row }) => startCase(row.original.type)
        },
        {
            accessorKey: 'activity',
            header: 'Activity',
            cell: ({ row }) => startCase(row.original.activity)
        },
        {
            accessorKey: 'receiver',
            header: 'Receiver',
            cell: ({ row }) => startCase(row.original.receiver)
        },
        {
            accessorKey: 'message',
            header: 'Message',
            cell: ({ row }) => {
                return <div className="truncate max-w-4xl">
                    {row.original.message}
                </div>
            }
        },
        {
            accessorKey: 'id',
            header: 'Action',
            cell: ({ row }) => {
                return <Button size={'sm'} onClick={() => {
                    setSelected(row.original)
                    setOpenForm(true)
                }} className="cursor-pointer">Edit</Button>
            }
        }
    ];

    return (
        <AppLayout>
            <div className="px-4 py-6">
                <RtsNavigation workspace={workspace} />

                <div className="py-5">
                    <h4 className="font-semibold text-2xl">Parcel Update Templates</h4>
                </div>

                <TemplateForm open={openForm} onOpenChange={setOpenForm} workspace={workspace} initialValue={selected} />

                <div>
                    <DataTable
                        columns={columns}
                        data={templates}
                    />
                </div>
            </div>
        </AppLayout>
    );
};


export default ParcelUpdateNotificationTemplates
