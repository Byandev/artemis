import { DeleteProductDialog } from '@/components/products/delete-product-dialog';
import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { toFrontendSort } from '@/lib/sort';
import ProductLayout from '@/pages/workspaces/products/partials/layout';
import workspaces from '@/routes/workspaces';
import { PaginatedData } from '@/types';
import { Product } from '@/types/models/Product';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import clsx from 'clsx';
import { omit } from 'lodash';
import {
    Edit,
    MoreHorizontal,
    Search,
    Trash2,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

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
        const timer = setTimeout(() => {
            router.get(
                workspaces.products.index({ workspace }),
                {
                    sort: query?.sort,
                    'filter[search]': searchValue || undefined,
                    page: searchValue ? 1 : query?.page ?? 1
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
    }, [searchValue]);

    const handleEdit = (product: Product) => {
        router.get(workspaces.products.edit({ workspace, product }));
    };

    const columns: ColumnDef<Product>[] = [
        {
            accessorKey: 'code',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title={'Code'} />
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
        <ProductLayout
            workspace={workspace}
            headerActions={
                <Button
                    size="sm"
                    onClick={() => router.get(workspaces.products.create({ workspace }))}
                >
                    Add Product
                </Button>
            }
        >
            <Head title={`${workspace.name} - Products`} />

            <div className="mb-3 flex items-center gap-2">
                <div className="relative w-full max-w-xs">
                    <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                    <input
                        className="h-9 w-full rounded-[10px] border border-black/6 dark:border-white/6 bg-stone-100 dark:bg-zinc-800 pl-8 pr-3 font-[family-name:--font-dm-mono] text-[12px] text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-600 outline-none transition-all focus:border-emerald-500 dark:focus:border-emerald-400 focus:ring-2 focus:ring-emerald-500/15"
                        placeholder="Search product name or code..."
                        value={searchValue}
                        onChange={(e) => setSearchValue(e.target.value)}
                    />
                </div>
            </div>

            <div className="rounded-[14px] border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900">
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

