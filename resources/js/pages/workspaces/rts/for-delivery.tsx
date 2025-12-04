import AppLayout from '@/layouts/app-layout'
import { Workspace } from '@/types/models/Workspace'
import React from 'react'
import RtsNavigation from './partials/RtsNavigation'
import { DataTable } from '@/components/ui/data-table'
import { ColumnDef } from '@tanstack/react-table'

export interface Delivery {
    order_id: number
    order_number: string
    page: string
    product?: string | null
    tracking_number?: string | null
    customer?: string | null
    rider?: string | null
    status?: string | null
    parcel_journey_created_at: string
}

type Props = {
    workspace: Workspace,
    data: Delivery[],
}

const ForDelivery: React.FC<Props> = ({ workspace, data }) => {

    const deliveryColumns: ColumnDef<Delivery>[] = [
        {
            accessorKey: "page",
            header: "Page",
        },
        {
            accessorKey: "product",
            header: "Product",
        },
        {
            accessorKey: "tracking_number",
            header: "Tracking #",
        },
        {
            accessorKey: "customer",
            header: "Customer",
        },
        {
            accessorKey: "rider",
            header: "Rider",
        },
        {
            accessorKey: "status",
            header: "Status",
        },
    ];

    return (
        <AppLayout>
            <div className='px-4 py-6'>
                <RtsNavigation workspace={workspace} />

                <div className='grid overflow-x-auto'>
                    <DataTable columns={deliveryColumns} data={data} />
                </div>
            </div>
        </AppLayout>
    )
}

export default ForDelivery