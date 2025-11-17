import { Workspace } from '@/types/models/Workspace';
import { Page } from '@/types/models/Page';
import { Product } from '@/types/models/Product';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { PageFormDialog } from '@/components/pages/page-form-dialog';
import { ColumnDef } from '@tanstack/react-table';

interface PageProps {
    workspace: Workspace;
    products: {
        data: Product[];
    };
}

const Index = ({ workspace, products }: PageProps) => {
    const columns: ColumnDef<Product>[] = [
        {
            accessorKey: "title",
            header: "Product Title",
        },
        // {
        //     accessorKey: "orders_last_synced_at",
        //     header: "Orders Last Sync",
        //     cell: ({ row }) => {
        //         return row.original.orders_last_synced_at
        //     }
        // }
        ];

    return (
        <AppLayout>
            <div className='px-4 py-6'>
                <div>
                    <DataTable columns={columns} data={products.data || []}/>
                </div>
            </div>
        </AppLayout>
    );
}

export default Index;
