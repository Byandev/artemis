import PageHeader from '@/components/common/PageHeader';
import { ArchivePageDialog } from '@/components/pages/archive-page-dialog';
import { PageFormDialog } from '@/components/pages/page-form-dialog';
import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { currencyFormatter } from '@/lib/utils';
import workspaces from '@/routes/workspaces';
import { PaginatedData, User } from '@/types';
import { Page } from '@/types/models/Page';
import { Workspace } from '@/types/models/Workspace';
import { Head, router, useForm } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import clsx from 'clsx';
import { omit } from 'lodash';
import {
    Archive,
    Edit,
    MoreHorizontal,
    RefreshCw,
    RotateCcw,
    Search,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface PagesProps {
    workspace: Workspace;
    pages: PaginatedData<Page>;
    query?: {
        sort?: string | null
        perPage?: number | string
        page?: number | string
        filter?: {
            search?: string
        }
    }
    users: User[];
}

const StatusBadge = ({ isArchived }: { isArchived: boolean }) => {
    return (
        <span
            className={clsx(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium',
                isArchived
                    ? 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                    : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
            )}
        >
            <span className={clsx('h-1.5 w-1.5 rounded-full', isArchived ? 'bg-slate-400' : 'bg-emerald-500')} />
            {isArchived ? 'Archived' : 'Active'}
        </span>
    );
};

const EnableBadge = ({ isEnabled }: { isEnabled: boolean }) => {
    return (
        <span
            className={clsx(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium',
                isEnabled
                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'
                    : 'bg-red-50 text-red-500 dark:bg-red-500/10 dark:text-red-400',
            )}
        >
            <span className={clsx('h-1.5 w-1.5 rounded-full', isEnabled ? 'bg-emerald-500' : 'bg-red-500')} />
            {isEnabled ? 'Enabled' : 'Disabled'}
        </span>
    );
};

const Pages = ({ pages, workspace, query, users }: PagesProps) => {
    const initialSorting = useMemo(() => {
        return toFrontendSort(query?.sort ?? null);
    }, [query?.sort]);

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [dialogOpen, setDialogOpen] = useState(false);
    const [selectedPage, setSelectedPage] = useState<Page | undefined>(undefined);
    const [pageToArchive, setPageToArchive] = useState<Page | null>(null);

    const { post, processing } = useForm({});

    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(
                workspaces.pages.index({ workspace }),
                {
                    sort: query?.sort,
                    'filter[search]': searchValue || undefined,
                    page: searchValue ? 1 : query?.page ?? 1
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
    }, [searchValue]);

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
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title={'Name'} />
            ),
        },
        {
            accessorKey: 'shop_name',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Shop'} />
            ),
            cell: ({ row }) => row.original.shop?.name || '-',
        },
        {
            accessorKey: 'owner_name',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Owner'} />
            ),
            cell: ({ row }) => row.original.owner?.name || '-',
        },
        // {
        //     accessorKey: 'current_budget',
        //     header: ({ column }) => (
        //         <SortableHeader column={column} title={'Current Budget'} enabled={false} />
        //     ),
        //     cell: ({ row }) => currencyFormatter(row.original.current_budget ?? 0),
        // },
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
            accessorKey: 'deleted_at',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Status'} />
            ),
            cell: ({ row }) => {
                const isArchived = row.original.deleted_at !== null;
                return <StatusBadge isArchived={isArchived} />;
            },
        },
        {
            accessorKey: 'parcel_journey_enabled',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Parcel Journey'} />
            ),
            cell: ({ row }) => {
                const isEnabled = Boolean(row.original.parcel_journey_enabled);

                return <EnableBadge isEnabled={isEnabled} />;
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
            <Head title={`${workspace.name} - Pages`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader title="Pages" description="Manage your shop pages and their connected stores">
                    <Button size="sm" onClick={handleCreate}>
                        Add New Page
                    </Button>
                </PageHeader>

                <div className="mb-3 flex items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 dark:border-white/6 bg-stone-100 dark:bg-zinc-800 pl-8 pr-3 font-[family-name:--font-dm-mono] text-[12px] text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-600 outline-none transition-all focus:border-emerald-500 dark:focus:border-emerald-400 focus:ring-2 focus:ring-emerald-500/15"
                            placeholder="Search page name..."
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                        />
                    </div>
                </div>

                <div className="rounded-[14px] border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900">
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
                                    preserveState: true,
                                    replace: true,
                                    preserveScroll: true,
                                },
                            );
                        }}
                    />
                </div>

                {/* Page Form Dialog */}
                <PageFormDialog
                    open={dialogOpen}
                    onOpenChange={setDialogOpen}
                    page={selectedPage}
                    workspace={workspace}
                    users={users}
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
