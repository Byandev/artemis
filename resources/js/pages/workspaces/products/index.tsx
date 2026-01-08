import { useState, useMemo, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import ProductLayout from '@/pages/workspaces/products/partials/layout';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { ColumnDef } from '@tanstack/react-table';
import { Product } from '@/types/models/Product';
import { Button } from '@/components/ui/button';
import { DeleteProductDialog } from '@/components/products/delete-product-dialog';
import { Workspace } from '@/types/models/Workspace';
import ComponentCard from '@/components/common/ComponentCard';
import {
    MoreHorizontal,
    Trash2,
    Edit,
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

interface ProductsProps {
    workspace: Workspace;
    products: PaginatedData<Product>;
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: {
            search?: string;
        };
    };
}

const StatusBadge = ({ status }: { status: string }) => {
    const isActive = status === 'active';
    return (
        <span
            className={clsx(
                "inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wide ring-1 ring-inset",
                isActive
                    ? "bg-emerald-50 text-emerald-700 ring-emerald-200"
                    : "bg-slate-50 text-slate-700 ring-slate-200"
            )}
        >
            {status.toUpperCase()}
        </span>
    );
};

const Index = ({ products, workspace, query }: ProductsProps) => {
    const initialSorting = useMemo(() => {
        return toFrontendSort(query?.sort ?? null);
    }, [query?.sort]);

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [productToDelete, setProductToDelete] = useState<Product | null>(null);

    useEffect(() => {
        const currentSearchParam = query?.filter?.search ?? '';

        if (searchValue === currentSearchParam) {
            return;
        }

        const timer = setTimeout(() => {
            router.get(
                workspaces.products.index({ workspace }),
                {
                    sort: query?.sort,
                    'filter[search]': searchValue || undefined,
                    page: 1,
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['products'],
                },
            );
        }, 500);

        return () => clearTimeout(timer);
    }, [searchValue, query?.filter?.search, query?.sort, workspace]);

    const handleEdit = (product: Product) => {
        router.get(workspaces.products.edit({ workspace, product }));
    };

    const columns: ColumnDef<Product>[] = [
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title={'Name'} />
            ),
        },
        {
            accessorKey: 'category',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Category'} />
            ),
            cell: ({ row }) => row.original.category || '-',
        },
        {
            accessorKey: 'status',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Status'} />
            ),
            cell: ({ row }) => <StatusBadge status={row.original.status || 'inactive'} />,
        },
        {
            id: 'actions',
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
    ];

    return (
        <ProductLayout workspace={workspace}>
            <Head title={`${workspace.name} - Products`} />
            <div className="flex flex-wrap items-center justify-end gap-3 mb-4">
                <Button 
                    size="sm" 
                    onClick={() => router.get(workspaces.products.create({ workspace }))}
                >
                    Add Product
                </Button>
            </div>

            <div className="space-y-5 sm:space-y-6">
                    <ComponentCard desc="Manage your product inventory and pricing">
                        <div>
                            <div className="flex flex-col gap-2 rounded-t-xl border border-b-0 border-gray-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/[0.05]">
                                <input
                                    className="max-w-sm border w-full rounded-lg appearance-none px-4 py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:outline-hidden focus:ring-3  dark:bg-gray-900  dark:placeholder:text-white/30  bg-transparent text-gray-800 border-gray-300 focus:border-brand-300 focus:ring-brand-500/20 dark:border-gray-700 dark:text-white/90  dark:focus:border-brand-800"
                                    placeholder="Search product name or SKU"
                                    value={searchValue}
                                    onChange={(e) => setSearchValue(e.target.value)}
                                />
                            </div>

                            <DataTable
                                columns={columns}
                                enableInternalPagination={false}
                                data={products.data || []}
                                initialSorting={initialSorting}
                                meta={{ ...omit(products, ['data']) }}
                                onFetch={(params) => {
                                    router.get(
                                        workspaces.products.index({ workspace }),
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

            {/* Delete Confirmation Dialog */}
            <DeleteProductDialog
                product={productToDelete}
                workspace={workspace}
                onClose={() => setProductToDelete(null)}
            />
        </ProductLayout>
    );
};

export default Index;

