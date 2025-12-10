import { useState, useMemo, useEffect, useCallback } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { DataTable } from '@/components/ui/data-table';
import { ColumnDef } from '@tanstack/react-table';
import { Product } from '@/types/models/Product';
import { Button } from '@/components/ui/button';
import { DeleteProductDialog } from '@/components/products/delete-product-dialog';
import { Workspace } from '@/types/models/Workspace';
import {
    MoreHorizontal,
    Trash2,
    Search,
    X,
    Loader2,
    Edit,
    ArrowUpDown,
    ArrowUp,
    ArrowDown,
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
import workspaces from '@/routes/workspaces';
import { Badge } from '@/components/ui/badge';

interface Filters {
    search: string;
    category: string;
    status: string;
    sort: string;
    direction: string;
}

interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedProducts {
    data: Product[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLinks[];
    from: number;
    to: number;
}

interface ProductsProps {
    workspace: Workspace;
    products: PaginatedProducts;
    filters: Filters;
    categories: string[];
}

const Index = ({ products, workspace, filters, categories }: ProductsProps) => {
    const [productToDelete, setProductToDelete] = useState<Product | null>(null);
    const [searchValue, setSearchValue] = useState(filters.search);
    const [isSearching, setIsSearching] = useState(false);
    const [activeTab, setActiveTab] = useState('products'); // analytics, products, testing_products

    // Debounced search
    const debouncedSearch = useCallback(
        (value: string) => {
            setIsSearching(true);
            router.get(
                workspaces.products.index.url({ workspace }),
                { ...filters, search: value, page: 1 },
                {
                    preserveState: true,
                    preserveScroll: true,
                    onFinish: () => setIsSearching(false),
                }
            );
        },
        [filters, workspace]
    );

    useEffect(() => {
        if (searchValue === filters.search) return;

        const timer = setTimeout(() => {
            debouncedSearch(searchValue);
        }, 300);

        return () => clearTimeout(timer);
    }, [searchValue]);

    const handleEdit = (product: Product) => {
        router.visit(workspaces.products.edit.url({ workspace, product }));
    };

    const handleCreate = () => {
        router.visit(workspaces.products.create.url({ workspace }));
    };

    const handleFilter = (key: string, value: string) => {
        router.get(
            workspaces.products.index.url({ workspace }),
            { ...filters, [key]: value, page: 1 },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleSort = (column: string) => {
        const newDirection = 
            filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';
        
        router.get(
            workspaces.products.index.url({ workspace }),
            { ...filters, sort: column, direction: newDirection, page: 1 },
            { preserveState: true, preserveScroll: true }
        );
    };

    const SortableHeader = ({ column, children }: { column: string; children: React.ReactNode }) => {
        const isSorted = filters.sort === column;
        const direction = filters.direction;
        
        return (
            <button
                onClick={() => handleSort(column)}
                className="flex items-center gap-2 hover:text-foreground transition-colors"
            >
                {children}
                {isSorted ? (
                    direction === 'asc' ? (
                        <ArrowUp className="h-4 w-4" />
                    ) : (
                        <ArrowDown className="h-4 w-4" />
                    )
                ) : (
                    <ArrowUpDown className="h-4 w-4 opacity-50" />
                )}
            </button>
        );
    };

    const clearSearch = () => {
        setSearchValue('');
        router.get(
            workspaces.products.index.url({ workspace }),
            { ...filters, search: '', page: 1 },
            { preserveState: true, preserveScroll: true }
        );
    };

    const clearFilters = () => {
        router.get(workspaces.products.index.url({ workspace }));
    };

    const getStatusBadgeVariant = (status: string) => {
        switch (status) {
            case 'Scaling':
                return 'default';
            case 'Testing':
                return 'secondary';
            case 'Failed':
                return 'destructive';
            case 'Inactive':
                return 'outline';
            default:
                return 'default';
        }
    };

    const columns: ColumnDef<Product>[] = useMemo(
        () => [
            {
                accessorKey: 'name',
                header: () => <SortableHeader column="name">Name</SortableHeader>,
            },
            {
                accessorKey: 'code',
                header: () => <SortableHeader column="code">Code</SortableHeader>,
            },
            {
                accessorKey: 'category',
                header: () => <SortableHeader column="category">Category</SortableHeader>,
            },
            {
                accessorKey: 'ad_budget_today',
                header: () => <SortableHeader column="ad_budget_today">Ad Budget Today</SortableHeader>,
                cell: () => {
                    // Placeholder - will be fetched from Facebook API
                    return <span className="text-muted-foreground">-</span>;
                },
            },
            {
                accessorKey: 'status',
                header: () => <SortableHeader column="status">Status</SortableHeader>,
                cell: ({ row }) => {
                    const status = row.original.status;
                    return (
                        <Badge variant={getStatusBadgeVariant(status)}>
                            {status}
                        </Badge>
                    );
                },
            },
            {
                id: 'actions',
                header: 'Action',
                cell: ({ row }) => {
                    const product = row.original;

                    return (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="sm">
                                    <MoreHorizontal className="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem onClick={() => handleEdit(product)}>
                                    <Edit className="mr-2 h-4 w-4" />
                                    Edit
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onClick={() => setProductToDelete(product)}
                                    className="text-destructive focus:text-destructive"
                                >
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Delete
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    );
                },
            },
        ],
        [filters.sort, filters.direction]
    );

    const hasActiveFilters = filters.search || filters.category || filters.status;

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Products`} />
            <div className="px-4 py-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-bold">Products</h1>
                </div>

                {/* Tabs - Analytics, Products, Testing Products */}
                <div className="mb-6 border-b border-border">
                    <div className="flex gap-1">
                        <button
                            onClick={() => setActiveTab('analytics')}
                            className={`px-4 py-2.5 text-sm font-medium transition-colors relative ${
                                activeTab === 'analytics'
                                    ? 'text-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            Analytics
                            {activeTab === 'analytics' && (
                                <span className="absolute bottom-0 left-0 right-0 h-0.5 bg-primary" />
                            )}
                        </button>
                        <button
                            onClick={() => setActiveTab('products')}
                            className={`px-4 py-2.5 text-sm font-medium transition-colors relative ${
                                activeTab === 'products'
                                    ? 'text-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            Products
                            {activeTab === 'products' && (
                                <span className="absolute bottom-0 left-0 right-0 h-0.5 bg-primary" />
                            )}
                        </button>
                        <button
                            onClick={() => setActiveTab('testing_products')}
                            className={`px-4 py-2.5 text-sm font-medium transition-colors relative ${
                                activeTab === 'testing_products'
                                    ? 'text-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            Testing Products
                            {activeTab === 'testing_products' && (
                                <span className="absolute bottom-0 left-0 right-0 h-0.5 bg-primary" />
                            )}
                        </button>
                    </div>
                </div>

                {/* Filters and Search */}
                <div className="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <div className="flex flex-wrap gap-4">
                        {/* Search */}
                        <div className="relative">
                            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                type="text"
                                placeholder="Search..."
                                value={searchValue}
                                onChange={(e) => setSearchValue(e.target.value)}
                                className="w-[200px] pl-8"
                            />
                            {(searchValue || isSearching) && (
                                <span className="absolute right-2.5 top-2.5">
                                    {isSearching ? (
                                        <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                                    ) : (
                                        <button type="button" onClick={clearSearch}>
                                            <X className="h-4 w-4 text-muted-foreground hover:text-foreground" />
                                        </button>
                                    )}
                                </span>
                            )}
                        </div>

                        {/* Filter by Category */}
                        {categories.length > 0 && (
                            <Select
                                value={filters.category || '__all__'}
                                onValueChange={(value) =>
                                    handleFilter('category', value === '__all__' ? '' : value)
                                }
                            >
                                <SelectTrigger className="w-[180px]">
                                    <SelectValue placeholder="Filter by category" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__all__">All Categories</SelectItem>
                                    {categories
                                        .filter((category) => category && category.trim() !== '')
                                        .map((category) => (
                                            <SelectItem key={category} value={category}>
                                                {category}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                        )}

                        {/* Filter by Status */}
                        <Select
                            value={filters.status || '__all__'}
                            onValueChange={(value) =>
                                handleFilter('status', value === '__all__' ? '' : value)
                            }
                        >
                            <SelectTrigger className="w-[180px]">
                                <SelectValue placeholder="Filter by status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__all__">All Status</SelectItem>
                                <SelectItem value="Scaling">Scaling</SelectItem>
                                <SelectItem value="Testing">Testing</SelectItem>
                                <SelectItem value="Failed">Failed</SelectItem>
                                <SelectItem value="Inactive">Inactive</SelectItem>
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

                    <div className="flex gap-2">
                        {/* Add new Product Button */}
                        <Button size="sm" onClick={handleCreate}>
                            Add new Product
                        </Button>
                    </div>
                </div>

                {/* Data Table */}
                <div className="rounded-md border">
                    <DataTable columns={columns} data={products.data || []} />
                </div>

                {/* Pagination */}
                <div className="mt-4 flex items-center justify-between">
                    <div className="text-sm text-muted-foreground">
                        {products.total > 0 ? (
                            <>
                                Showing {products.from} to {products.to} of {products.total}{' '}
                                results
                            </>
                        ) : (
                            <>No results found</>
                        )}
                    </div>
                    {products.last_page > 1 && (
                        <div className="flex gap-2">
                            {products.links.map((link, index) => (
                                <Button
                                    key={index}
                                    variant={link.active ? 'default' : 'outline'}
                                    size="sm"
                                    disabled={!link.url}
                                    onClick={() => {
                                        if (link.url) {
                                            router.get(
                                                link.url,
                                                {},
                                                { preserveState: true, preserveScroll: true }
                                            );
                                        }
                                    }}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </div>

                {/* Delete Confirmation Dialog */}
                <DeleteProductDialog
                    product={productToDelete}
                    workspace={workspace}
                    onClose={() => setProductToDelete(null)}
                />
            </div>
        </AppLayout>
    );
};

export default Index;

