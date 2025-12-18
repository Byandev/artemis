import { useState, useMemo, useEffect } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { ColumnDef } from '@tanstack/react-table';
import { Page } from '@/types/models/Page';
import { Button } from '@/components/ui/button';
import { PageFormDialog } from '@/components/pages/page-form-dialog';
import { ArchivePageDialog } from '@/components/pages/archive-page-dialog';
import { Workspace } from '@/types/models/Workspace';
import ComponentCard from '@/components/common/ComponentCard';
import { 
    Edit, 
    MoreHorizontal, 
    Archive, 
    RotateCcw, 
    RefreshCw
} from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import workspaces from '@/routes/workspaces';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { omit } from 'lodash';
import clsx from 'clsx';

interface PagesProps {
    workspace: Workspace;
    pages: PaginatedData<Page>;
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: {
            search?: string;
        };
    };
}

const StatusBadge = ({ isArchived }: { isArchived: boolean }) => {
    return (
        <span
            className={clsx(
                "inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wide ring-1 ring-inset",
                isArchived
                    ? "bg-slate-50 text-slate-700 ring-slate-200"
                    : "bg-emerald-50 text-emerald-700 ring-emerald-200"
            )}
        >
            {isArchived ? "ARCHIVED" : "ACTIVE"}
        </span>
    );
};

const Pages = ({ pages, workspace, query }: PagesProps) => {
    const initialSorting = useMemo(() => {
        return toFrontendSort(query?.sort ?? null);
    }, [query?.sort]);

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [dialogOpen, setDialogOpen] = useState(false);
    const [selectedPage, setSelectedPage] = useState<Page | undefined>(undefined);
    const [pageToArchive, setPageToArchive] = useState<Page | null>(null);

    const { post, processing } = useForm({});

    useEffect(() => {
        const currentSearchParam = query?.filter?.search ?? '';

        if (searchValue === currentSearchParam) {
            return;
        }

        const timer = setTimeout(() => {
            router.get(
                workspaces.pages.index({ workspace }),
                {
                    sort: query?.sort,
                    'filter[search]': searchValue || undefined,
                    page: 1,
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['pages'],
                },
            );
        }, 500);

        return () => clearTimeout(timer);
    }, [searchValue, query?.filter?.search, query?.sort, workspace]);

    const handleEdit = (page: Page) => {
        setSelectedPage(page);
        setDialogOpen(true);
    };

    const handleCreate = () => {
        setSelectedPage(undefined);
        setDialogOpen(true);
    };

    const refresh = (page: Page) => {
        post(workspaces.pages.refresh.url({ workspace, page }), {
            onSuccess: () => alert('Refresh Started'),
        });
    };

    const handleRestore = (page: Page) => {
        router.post(workspaces.pages.restore.url({ workspace, page }));
    };

    const columns: ColumnDef<Page>[] = [
        {
            accessorKey: 'id',
            header: ({ column }) => (
                <SortableHeader column={column} title={'ID'} />
            ),
        },
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title={'Name'} />
            ),
        },
        {
            accessorKey: 'shop',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Shop'} />
            ),
            cell: ({ row }) => row.original.shop?.name || '-',
        },
        {
            accessorKey: 'owner',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Owner'} />
            ),
            cell: ({ row }) => row.original.owner?.name || '-',
        },
        {
            accessorKey: 'orders_last_synced_at',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Last Sync'} />
            ),
            cell: ({ row }) => {
                const date = row.original.orders_last_synced_at;
                return date ? new Date(date).toLocaleString() : 'Never';
            },
        },
        {
            accessorKey: 'status',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Status'} />
            ),
            cell: ({ row }) => {
                const isArchived = row.original.deleted_at !== null;
                return <StatusBadge isArchived={isArchived} />;
            },
        },
        {
            id: 'actions',
            cell: ({ row }) => {
                const page = row.original;
                const isArchived = page.deleted_at !== null;

                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="sm">
                                <MoreHorizontal className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {!isArchived && (
                                <>
                                    <DropdownMenuItem onClick={() => handleEdit(page)}>
                                        <Edit className="mr-2 h-4 w-4" />
                                        Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuItem 
                                        onClick={() => refresh(page)}
                                        disabled={processing}
                                    >
                                        <RefreshCw className={`mr-2 h-4 w-4 ${processing ? 'animate-spin' : ''}`} />
                                        {processing ? 'Refreshing...' : 'Refresh Orders'}
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem 
                                        onClick={() => setPageToArchive(page)}
                                        className="text-destructive focus:text-destructive"
                                    >
                                        <Archive className="mr-2 h-4 w-4" />
                                        Archive
                                    </DropdownMenuItem>
                                </>
                            )}
                            {isArchived && (
                                <DropdownMenuItem onClick={() => handleRestore(page)}>
                                    <RotateCcw className="mr-2 h-4 w-4" />
                                    Restore
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Shop & Pages`} />
            <div className="mx-auto max-w-(--breakpoint-2xl) p-4 md:p-6">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <h2
                        className="text-xl font-semibold text-gray-800 dark:text-white/90"
                        x-text="pageName"
                    >
                        Shop & Pages
                    </h2>
                    <Button size="sm" onClick={handleCreate}>
                        Add new page
                    </Button>
                </div>

                <div className="space-y-5 sm:space-y-6">
                    <ComponentCard desc="List of shop pages and their connected stores">
                        <div>
                            <div className="flex flex-col gap-2 rounded-t-xl border border-b-0 border-gray-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/[0.05]">
                                <input
                                    className="max-w-sm border w-full rounded-lg appearance-none px-4 py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:outline-hidden focus:ring-3  dark:bg-gray-900  dark:placeholder:text-white/30  bg-transparent text-gray-800 border-gray-300 focus:border-brand-300 focus:ring-brand-500/20 dark:border-gray-700 dark:text-white/90  dark:focus:border-brand-800"
                                    placeholder="Search page name"
                                    value={searchValue}
                                    onChange={(e) => setSearchValue(e.target.value)}
                                />
                            </div>

                            <DataTable
                                columns={columns}
                                enableInternalPagination={false}
                                data={pages.data || []}
                                initialSorting={initialSorting}
                                meta={{ ...omit(pages, ['data']) }}
                                onFetch={(params) => {
                                    router.get(
                                        workspaces.pages.index({ workspace }),
                                        {
                                            sort: params?.sort,
                                            'filter[search]': searchValue || undefined,
                                            page: params?.page ?? 1
                                        },
                                        {
                                            preserveState: false,
                                            replace: true,
                                            preserveScroll: true,
                                        },
                                    );
                                }}
                            />
                        </div>
                    </ComponentCard>
                </div>

                {/* Page Form Dialog */}
                <PageFormDialog
                    open={dialogOpen}
                    onOpenChange={setDialogOpen}
                    page={selectedPage}
                    workspace={workspace}
                />

                {/* Archive Confirmation Dialog */}
                <ArchivePageDialog
                    page={pageToArchive}
                    workspace={workspace}
                    onClose={() => setPageToArchive(null)}
                />
            </div>
        </AppLayout>
    );
};

export default Pages;
