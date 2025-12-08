import { useState, useMemo, useEffect, useCallback } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { DataTable } from '@/components/ui/data-table';
import { ColumnDef } from '@tanstack/react-table';
import { Product } from '@/types/models/Product';
import { Button } from '@/components/ui/button';
import { ProductFormDialog } from '@/components/products/product-form-dialog';
import { DeleteProductDialog } from '@/components/products/delete-product-dialog';
import { Workspace } from '@/types/models/Workspace';
import {
    Edit,
    MoreHorizontal,
    Trash2,
    Search,
    X,
    Loader2,
    Eye,
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
    const [dialogOpen, setDialogOpen] = useState(false);
    const [selectedProduct, setSelectedProduct] = useState<Product | undefined>(
        undefined
    );
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
        setSelectedProduct(product);
        setDialogOpen(true);
    };

    const handleCreate = () => {
        setSelectedProduct(undefined);
        setDialogOpen(true);
    };

    const handleFilter = (key: string, value: string) => {
        router.get(
            workspaces.products.index.url({ workspace }),
            { ...filters, [key]: value, page: 1 },
            { preserveState: true, preserveScroll: true }
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
                header: 'Name',
            },
            {
                accessorKey: 'code',
                header: 'Code',
            },
            {
                accessorKey: 'category',
                header: 'Category',
            },
            {
                accessorKey: 'ad_budget_today',
                header: 'Ad Budget Today',
                cell: ({ row }) => {
                    const amount = row.original.ad_budget_today;
                    return `₱${Number(amount).toLocaleString('en-PH', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    })}`;
                },
            },
            {
                accessorKey: 'status',
                header: 'Status',
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
                                    <Eye className="mr-2 h-4 w-4" />
                                    View
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
        []
    );

    const hasActiveFilters = filters.search || filters.category;

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Products`} />
            <div className="px-4 py-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-bold">Products</h1>
                </div>

                {/* Tabs - Analytics, Products, Testing Products */}
                <div className="mb-4 flex gap-2">
                    <Button
                        variant={activeTab === 'analytics' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setActiveTab('analytics')}
                    >
                        Analytics
                    </Button>
                    <Button
                        variant={activeTab === 'products' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setActiveTab('products')}
                    >
                        Products
                    </Button>
                    <Button
                        variant={activeTab === 'testing_products' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setActiveTab('testing_products')}
                    >
                        Testing Products
                    </Button>
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
                                value={filters.category || 'all'}
                                onValueChange={(value) =>
                                    handleFilter('category', value === 'all' ? '' : value)
                                }
                            >
                                <SelectTrigger className="w-[180px]">
                                    <SelectValue placeholder="Filter by category" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Categories</SelectItem>
                                    {categories.map((category) => (
                                        <SelectItem key={category} value={category}>
                                            {category}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}

                        {/* Clear Filters */}
                        {hasActiveFilters && (
                            <Button variant="ghost" size="sm" onClick={clearFilters}>
                                <X className="mr-2 h-4 w-4" />
                                Clear filters
                            </Button>
                        )}
                    </div>

                    <div className="flex gap-2">
                        {/* Filter Button */}
                        <Button size="sm" variant="outline">
                            Filter ▼
                        </Button>
                        
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

                {/* Product Form Dialog */}
                <ProductFormDialog
                    open={dialogOpen}
                    onOpenChange={setDialogOpen}
                    product={selectedProduct}
                    workspace={workspace}
                />

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

