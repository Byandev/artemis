import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { DataTable } from '@/components/ui/data-table';
import { ColumnDef } from '@tanstack/react-table';
import { Page } from '@/types/models/Page';
import { Button } from '@/components/ui/button';
import { PageFormDialog } from '@/components/pages/page-form-dialog';
import { Workspace } from '@/types/models/Workspace';
import { Edit, MoreHorizontal } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useForm } from '@inertiajs/react';
import workspaces from '@/routes/workspaces';

interface PagesProps {
    workspace: Workspace;
    userWorkspaces: Workspace[];
    pages: {
        data: Page[];
    };
}

const Pages = ({ pages, workspace, userWorkspaces }: PagesProps) => {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [selectedPage, setSelectedPage] = useState<Page | undefined>(undefined);
    const { post } = useForm({

    })

    const handleEdit = (page: Page) => {
        setSelectedPage(page);
        setDialogOpen(true);
    };

    const refresh = (page: Page) => {
        post(workspaces.pages.refresh.url({ workspace, page }), {
            onSuccess: () => alert('Refresh Started')
        })
    }

    const handleCreate = () => {
        setSelectedPage(undefined);
        setDialogOpen(true);
    };

    const columns: ColumnDef<Page>[] = [
        {
            accessorKey: "id",
            header: "ID",
        },
        {
            accessorKey: "name",
            header: "Name",
        },
        {
            accessorKey: "orders_last_synced_at",
            header: "Orders Last Sync",
            cell: ({ row }) => {
                return row.original.orders_last_synced_at
            }
        },
        {
            id: "actions",
            cell: ({ row }) => {
                const page = row.original;

                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="sm">
                                <MoreHorizontal className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem onClick={() => handleEdit(page)}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit
                            </DropdownMenuItem>

                            <DropdownMenuItem onClick={() => refresh(page)}>
                                <Edit className="mr-2 h-4 w-4" />
                                Refresh
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },
    ];

    return (
        <AppLayout workspaces={userWorkspaces} currentWorkspace={workspace.name}>
            <div className='px-4 py-6'>
                <div className='mb-4'>
                    <Button size='sm' onClick={handleCreate}>Add new page</Button>
                </div>

                <div>
                    <DataTable columns={columns} data={pages.data || []}/>
                </div>

                <PageFormDialog
                    open={dialogOpen}
                    onOpenChange={setDialogOpen}
                    page={selectedPage}
                    workspace={workspace}
                />
            </div>
        </AppLayout>
    );
}

export default Pages;
