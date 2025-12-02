import { useState, useMemo } from 'react';
import AppLayout from '@/layouts/app-layout';
import { DataTable } from '@/components/ui/data-table';
import { ColumnDef } from '@tanstack/react-table';
import { Page } from '@/types/models/Page';
import { Button } from '@/components/ui/button';
import { PageFormDialog } from '@/components/pages/page-form-dialog';
import { Workspace } from '@/types/models/Workspace';
import { 
    Edit, 
    MoreHorizontal, 
    Archive, 
    RotateCcw, 
    RefreshCw,
    Search,
    ArrowUpDown,
    ArrowUp,
    ArrowDown,
    X
} from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { useForm, router } from '@inertiajs/react';
import workspaces from '@/routes/workspaces';
import { Badge } from '@/components/ui/badge';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

interface Owner {
    id: number;
    name: string;
}

interface Shop {
    id: number;
    name: string;
}

interface Filters {
    search: string;
    owner_id: string;
    shop_id: string;
    status: string;
    sort: string;
    direction: string;
}

interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedPages {
    data: Page[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLinks[];
    from: number;
    to: number;
}

interface PagesProps {
    workspace: Workspace;
    pages: PaginatedPages;
    filters: Filters;
    owners: Owner[];
    shops: Shop[];
}

const Pages = ({ pages, workspace, filters, owners, shops }: PagesProps) => {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [selectedPage, setSelectedPage] = useState<Page | undefined>(undefined);
    const [archiveDialogOpen, setArchiveDialogOpen] = useState(false);
    const [pageToArchive, setPageToArchive] = useState<Page | null>(null);
    const [searchValue, setSearchValue] = useState(filters.search);

    const { post, processing } = useForm({});

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

    const handleArchive = (page: Page) => {
        setPageToArchive(page);
        setArchiveDialogOpen(true);
    };

    const confirmArchive = () => {
        if (pageToArchive) {
            router.post(`/workspaces/${workspace.slug}/pages/${pageToArchive.id}/archive`, {}, {
                onSuccess: () => setArchiveDialogOpen(false),
            });
        }
    };

    const handleRestore = (page: Page) => {
        router.post(`/workspaces/${workspace.slug}/pages/${page.id}/restore`);
    };

    const handleFilter = (key: string, value: string) => {
        router.get(
            `/workspaces/${workspace.slug}/pages`,
            { ...filters, [key]: value, page: 1 },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        handleFilter('search', searchValue);
    };

    const clearSearch = () => {
        setSearchValue('');
        handleFilter('search', '');
    };

    const handleSort = (field: string) => {
        const newDirection = filters.sort === field && filters.direction === 'asc' ? 'desc' : 'asc';
        router.get(
            `/workspaces/${workspace.slug}/pages`,
            { ...filters, sort: field, direction: newDirection, page: 1 },
            { preserveState: true, preserveScroll: true }
        );
    };

    const clearFilters = () => {
        router.get(`/workspaces/${workspace.slug}/pages`, { status: filters.status });
    };

    const getSortIcon = (field: string) => {
        if (filters.sort !== field) return <ArrowUpDown className="ml-2 h-4 w-4" />;
        return filters.direction === 'asc' 
            ? <ArrowUp className="ml-2 h-4 w-4" /> 
            : <ArrowDown className="ml-2 h-4 w-4" />;
    };

    const columns: ColumnDef<Page>[] = useMemo(() => [
        {
            accessorKey: 'id',
            header: 'ID',
        },
        {
            accessorKey: 'name',
            header: () => (
                <Button
                    variant="ghost"
                    onClick={() => handleSort('name')}
                    className="h-8 px-2 lg:px-3"
                >
                    Name
                    {getSortIcon('name')}
                </Button>
            ),
        },
        {
            accessorKey: 'shop',
            header: () => (
                <Button
                    variant="ghost"
                    onClick={() => handleSort('shop_id')}
                    className="h-8 px-2 lg:px-3"
                >
                    Shop
                    {getSortIcon('shop_id')}
                </Button>
            ),
            cell: ({ row }) => row.original.shop?.name || '-',
        },
        {
            accessorKey: 'owner',
            header: () => (
                <Button
                    variant="ghost"
                    onClick={() => handleSort('owner_id')}
                    className="h-8 px-2 lg:px-3"
                >
                    Owner
                    {getSortIcon('owner_id')}
                </Button>
            ),
            cell: ({ row }) => row.original.owner?.name || '-',
        },
        {
            accessorKey: 'orders_last_synced_at',
            header: 'Last Sync',
            cell: ({ row }) => {
                const date = row.original.orders_last_synced_at;
                return date ? new Date(date).toLocaleString() : 'Never';
            },
        },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }) => {
                const isArchived = row.original.archived_at !== null;
                return (
                    <Badge variant={isArchived ? 'secondary' : 'default'}>
                        {isArchived ? 'Archived' : 'Active'}
                    </Badge>
                );
            },
        },
        {
            id: 'actions',
            cell: ({ row }) => {
                const page = row.original;
                const isArchived = page.archived_at !== null;

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
                                    <DropdownMenuItem onClick={() => refresh(page)}>
                                        <RefreshCw className="mr-2 h-4 w-4" />
                                        Refresh Orders
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem 
                                        onClick={() => handleArchive(page)}
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
    ], [filters.sort, filters.direction]);

    const hasActiveFilters = filters.search || filters.owner_id || filters.shop_id;

    return (
        <AppLayout>
            <div className="px-4 py-6">
                <div className="mb-6 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Shop & Pages</h1>
                    <Button size="sm" onClick={handleCreate}>
                        Add new page
                    </Button>
                </div>

                {/* Status Tabs */}
                <div className="mb-4 flex gap-2">
                    <Button
                        variant={filters.status === 'active' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => handleFilter('status', 'active')}
                    >
                        Active
                    </Button>
                    <Button
                        variant={filters.status === 'archived' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => handleFilter('status', 'archived')}
                    >
                        Archived
                    </Button>
                </div>

                {/* Filters */}
                <div className="mb-4 flex flex-wrap gap-4">
                    {/* Search */}
                    <form onSubmit={handleSearch} className="flex gap-2">
                        <div className="relative">
                            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                type="text"
                                placeholder="Search by name..."
                                value={searchValue}
                                onChange={(e) => setSearchValue(e.target.value)}
                                className="pl-8 w-[200px]"
                            />
                            {searchValue && (
                                <button
                                    type="button"
                                    onClick={clearSearch}
                                    className="absolute right-2.5 top-2.5"
                                >
                                    <X className="h-4 w-4 text-muted-foreground hover:text-foreground" />
                                </button>
                            )}
                        </div>
                        <Button type="submit" size="sm" variant="secondary">
                            Search
                        </Button>
                    </form>

                    {/* Filter by Owner */}
                    <Select
                        value={filters.owner_id || 'all'}
                        onValueChange={(value) => handleFilter('owner_id', value === 'all' ? '' : value)}
                    >
                        <SelectTrigger className="w-[180px]">
                            <SelectValue placeholder="Filter by owner" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Owners</SelectItem>
                            {owners.map((owner) => (
                                <SelectItem key={owner.id} value={String(owner.id)}>
                                    {owner.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {/* Filter by Shop */}
                    <Select
                        value={filters.shop_id || 'all'}
                        onValueChange={(value) => handleFilter('shop_id', value === 'all' ? '' : value)}
                    >
                        <SelectTrigger className="w-[180px]">
                            <SelectValue placeholder="Filter by shop" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Shops</SelectItem>
                            {shops.map((shop) => (
                                <SelectItem key={shop.id} value={String(shop.id)}>
                                    {shop.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {/* Clear Filters */}
                    {hasActiveFilters && (
                        <Button variant="ghost" size="sm" onClick={clearFilters}>
                            <X className="mr-2 h-4 w-4" />
                            Clear filters
                        </Button>
                    )}
                </div>

                {/* Data Table */}
                <div className="rounded-md border">
                    <DataTable columns={columns} data={pages.data || []} />
                </div>

                {/* Pagination */}
                {pages.last_page > 1 && (
                    <div className="mt-4 flex items-center justify-between">
                        <div className="text-sm text-muted-foreground">
                            Showing {pages.from} to {pages.to} of {pages.total} results
                        </div>
                        <div className="flex gap-2">
                            {pages.links.map((link, index) => (
                                <Button
                                    key={index}
                                    variant={link.active ? 'default' : 'outline'}
                                    size="sm"
                                    disabled={!link.url}
                                    onClick={() => {
                                        if (link.url) {
                                            router.get(link.url, {}, { preserveState: true, preserveScroll: true });
                                        }
                                    }}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}

                {/* Page Form Dialog */}
                <PageFormDialog
                    open={dialogOpen}
                    onOpenChange={setDialogOpen}
                    page={selectedPage}
                    workspace={workspace}
                />

                {/* Archive Confirmation Dialog */}
                <AlertDialog open={archiveDialogOpen} onOpenChange={setArchiveDialogOpen}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Archive Page</AlertDialogTitle>
                            <AlertDialogDescription>
                                Are you sure you want to archive "{pageToArchive?.name}"? 
                                You can restore it later from the Archived tab.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction onClick={confirmArchive}>
                                Archive
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </AppLayout>
    );
};

export default Pages;
