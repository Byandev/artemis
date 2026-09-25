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
import { PERMISSIONS } from '@/constants/permissions';
import {
    PRODUCT_STATUS_COLORS,
    PRODUCT_STATUSES,
    type ProductStatus,
} from '@/constants/product-statuses';
import { usePermission } from '@/hooks/use-permission';
import { toFrontendSort } from '@/lib/sort';
import ProductLayout from '@/pages/workspaces/products/partials/layout';
import workspaces from '@/routes/workspaces';
import { PaginatedData } from '@/types';
import { Product } from '@/types/models/Product';
import { Workspace } from '@/types/models/Workspace';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import clsx from 'clsx';
import { omit } from 'lodash';
import { Edit, MoreHorizontal, Search, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

interface ProductsProps {
    workspace: Workspace;
    products: PaginatedData<Product>;
    summary: {
        total_product_count: number;
        scaling_product_count: number;
        testing_product_count: number;
        inactive_product_count: number;
    };
    statusCounts: Record<string, number>;
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: {
            search?: string;
            status?: string;
        };
    };
}

interface ProductsPageProps {
    flash?: {
        success?: string | null;
    };
}

const StatusBadge = ({ status }: { status: string }) => (
    <span
        className={clsx(
            'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wide',
            PRODUCT_STATUS_COLORS[status as ProductStatus] ??
                'bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-gray-400',
        )}
    >
        {status.toUpperCase()}
    </span>
);

// "All" plus one tab per lifecycle stage, in the same order as the enum.
const STATUS_TABS: { label: string; value: string }[] = [
    { label: 'All', value: '' },
    ...PRODUCT_STATUSES.map((s) => ({ label: s, value: s })),
];

const StatCard = ({ label, value }: { label: string; value: number }) => (
    <div className="rounded-[14px] border border-black/6 bg-white p-[18px] dark:border-white/6 dark:bg-zinc-900">
        <p className="text-[11px] font-medium text-gray-400 dark:text-gray-500">
            {label}
        </p>
        <h4 className="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">
            {value.toLocaleString()}
        </h4>
    </div>
);

const Index = ({
    products,
    workspace,
    summary,
    statusCounts,
    query,
}: ProductsProps) => {
    const { flash } = usePage().props as ProductsPageProps;
    const initialSorting = useMemo(() => {
        return toFrontendSort(query?.sort ?? null);
    }, [query?.sort]);

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    // '' is the "All" tab — the controller only narrows when a status is sent.
    const [status, setStatus] = useState(query?.filter?.status ?? '');
    const [productToDelete, setProductToDelete] = useState<Product | null>(
        null,
    );
    const canCreateProducts = usePermission(PERMISSIONS.CreateProducts);
    const canEditProducts = usePermission(PERMISSIONS.EditProducts);
    const canDeleteProducts = usePermission(PERMISSIONS.DeleteProducts);
    const canUseProductActions = canEditProducts || canDeleteProducts;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    // Partial reloads have to pull the counts too — search narrows them, so the
    // stat cards and tab badges would otherwise drift out of sync with the table.
    const RELOAD_PROPS = ['products', 'summary', 'statusCounts', 'query'];

    const fetchProducts = (
        overrides: Record<string, string | number | null | undefined> = {},
        partial = true,
    ) => {
        router.get(
            workspaces.products.index({ workspace }),
            {
                sort: query?.sort,
                'filter[search]': searchValue || undefined,
                'filter[status]': status || undefined,
                page: query?.page ?? 1,
                per_page: query?.perPage ?? products.per_page,
                ...overrides,
            },
            {
                preserveState: partial,
                replace: true,
                preserveScroll: true,
                ...(partial ? { only: RELOAD_PROPS } : {}),
            },
        );
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            fetchProducts({ page: searchValue ? 1 : (query?.page ?? 1) });
        }, 500);

        return () => clearTimeout(timer);
    }, [searchValue]);

    const handleStatusChange = (next: string) => {
        setStatus(next);
        fetchProducts({ 'filter[status]': next || undefined, page: 1 });
    };

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
            cell: ({ row }) => (
                <StatusBadge status={row.original.status || 'inactive'} />
            ),
        },
        ...(canUseProductActions
            ? [
                  {
                      id: 'actions',
                      cell: ({ row }) => {
                          const product = row.original;

                          return (
                              <DropdownMenu>
                                  <DropdownMenuTrigger asChild>
                                      <button className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 transition-all hover:border-black/12 hover:bg-stone-100 hover:text-gray-600 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-500 dark:hover:border-white/12 dark:hover:bg-zinc-700 dark:hover:text-gray-300">
                                          <MoreHorizontal className="h-3.5 w-3.5" />
                                      </button>
                                  </DropdownMenuTrigger>
                                  <DropdownMenuContent
                                      align="end"
                                      className="w-36"
                                  >
                                      {canEditProducts && (
                                          <DropdownMenuItem
                                              onClick={() =>
                                                  handleEdit(product)
                                              }
                                          >
                                              <Edit />
                                              Edit
                                          </DropdownMenuItem>
                                      )}
                                      {canEditProducts && canDeleteProducts && (
                                          <DropdownMenuSeparator />
                                      )}
                                      {canDeleteProducts && (
                                          <DropdownMenuItem
                                              variant="destructive"
                                              onClick={() =>
                                                  setProductToDelete(product)
                                              }
                                          >
                                              <Trash2 />
                                              Delete
                                          </DropdownMenuItem>
                                      )}
                                  </DropdownMenuContent>
                              </DropdownMenu>
                          );
                      },
                  } as ColumnDef<Product>,
              ]
            : []),
    ];

    return (
        <ProductLayout
            workspace={workspace}
            headerActions={
                canCreateProducts ? (
                    <Button
                        size="sm"
                        onClick={() =>
                            router.get(
                                workspaces.products.create({ workspace }),
                            )
                        }
                    >
                        Add Product
                    </Button>
                ) : null
            }
        >
            <Head title={`${workspace.name} - Products`} />

            <div className="mb-5 grid grid-cols-2 gap-2 md:gap-4 xl:grid-cols-4">
                <StatCard
                    label="Total Products"
                    value={summary.total_product_count}
                />
                <StatCard
                    label="Scaling Products"
                    value={summary.scaling_product_count}
                />
                <StatCard
                    label="Testing Products"
                    value={summary.testing_product_count}
                />
                <StatCard
                    label="Inactive Products"
                    value={summary.inactive_product_count}
                />
            </div>

            <div className="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div className="relative w-full max-w-xs">
                    <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                    <input
                        className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                        placeholder="Search product name or code..."
                        value={searchValue}
                        onChange={(e) => setSearchValue(e.target.value)}
                    />
                </div>

                <div className="flex max-w-full flex-nowrap items-center overflow-x-auto sm:justify-end">
                    <div className="flex h-8 shrink-0 items-center gap-0.5 rounded-[10px] border border-black/6 bg-stone-100 p-0.5 dark:border-white/6 dark:bg-zinc-800">
                        {STATUS_TABS.map((tab) => (
                            <button
                                key={tab.value || 'all'}
                                onClick={() => handleStatusChange(tab.value)}
                                className={clsx(
                                    'h-full rounded-lg px-3 text-[12px]! font-semibold tracking-tight whitespace-nowrap transition-all',
                                    status === tab.value
                                        ? 'bg-white text-gray-800 shadow-sm dark:bg-zinc-700 dark:text-gray-100'
                                        : 'text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300',
                                )}
                            >
                                {tab.label}
                                <span className="ml-1.5 text-gray-400 dark:text-gray-500">
                                    {tab.value
                                        ? (statusCounts[tab.value] ?? 0)
                                        : summary.total_product_count}
                                </span>
                            </button>
                        ))}
                    </div>
                </div>
            </div>

            <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                <DataTable
                    columns={columns}
                    enableInternalPagination={false}
                    data={products.data || []}
                    initialSorting={initialSorting}
                    meta={{ ...omit(products, ['data']) }}
                    onFetch={(params) => {
                        fetchProducts(
                            {
                                sort: params?.sort,
                                page: params?.page ?? 1,
                                per_page:
                                    params?.per_page ??
                                    query?.perPage ??
                                    products.per_page,
                            },
                            false,
                        );
                    }}
                />
            </div>

            {/* Delete Confirmation Dialog */}
            {canDeleteProducts && (
                <DeleteProductDialog
                    product={productToDelete}
                    workspace={workspace}
                    onClose={() => setProductToDelete(null)}
                />
            )}
        </ProductLayout>
    );
};

export default Index;
