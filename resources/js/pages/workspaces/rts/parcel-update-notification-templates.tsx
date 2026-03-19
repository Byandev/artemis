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
import RTSManagementLayout from '@/pages/workspaces/rts/partials/Layout';
import ComponentCard from '@/components/common/ComponentCard';

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
            <div className="p-4">
                <TemplateForm
                    open={openForm}
                    onOpenChange={setOpenForm}
                    workspace={workspace}
                    initialValue={selected}
                />

                <ComponentCard
                    title={'Parcel Journey Notification Templates'}
                    desc={
                        'Modify the template of the messages for your parcel journey'
                    }
                    className='min-h-screen'
                >
                    <DataTable columns={columns} data={templates} />
                </ComponentCard>
            </div>
        </AppLayout>
    );
};


export default ParcelUpdateNotificationTemplates
