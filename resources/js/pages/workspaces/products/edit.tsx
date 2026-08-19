import PageHeader from '@/components/common/PageHeader';
import DatePicker from '@/components/ui/date-picker';
import { MultiSelect } from '@/components/ui/multi-select';
import { PRODUCT_STATUSES, ProductStatus } from '@/constants/product-statuses';
import AppLayout from '@/layouts/app-layout';
import workspaces from '@/routes/workspaces';
import { Product } from '@/types/models/Product';
import { Shop } from '@/types/models/Shop';
import { Workspace } from '@/types/models/Workspace';
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface PageProps {
    workspace: Workspace;
    product: Product & { shops?: Shop[] };
    shops: Shop[];
}

const inputClass =
    'h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400';
const labelClass =
    'block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500';
const fieldClass = 'space-y-1.5';
const errorClass = 'font-mono text-[11px] text-red-500';

const Edit = ({ workspace, product, shops }: PageProps) => {
    const { data, setData, put, processing, errors } = useForm({
        name: product.name || '',
        code: product.code || '',
        category: product.category || '',
        status: product.status as ProductStatus,
        winning_date: product.winning_date || '',
        description: product.description || '',
        shop_ids: product.shops?.map((s) => s.id) || ([] as number[]),
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(workspaces.products.update.url({ workspace, product }));
    };

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Edit Product`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Edit Product"
                    description={`${product.name} · ${product.code}`}
                >
                    <button
                        onClick={() =>
                            router.get(
                                workspaces.products.index.url({ workspace }),
                            )
                        }
                        className="flex items-center gap-1.5 font-mono! text-[12px]! text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                    >
                        <ArrowLeft className="h-3.5 w-3.5" />
                        Back to Products
                    </button>
                </PageHeader>

                <form onSubmit={handleSubmit} className="space-y-5">
                    {/* Basic Info */}
                    <div className="rounded-2xl border border-black/6 bg-white p-6 dark:border-white/6 dark:bg-zinc-900">
                        <p className="mb-5 font-mono text-[10px] font-semibold tracking-widest text-gray-300 uppercase dark:text-gray-600">
                            Basic Info
                        </p>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className={fieldClass}>
                                <label className={labelClass}>
                                    Product Name{' '}
                                    <span className="text-red-400">*</span>
                                </label>
                                <input
                                    type="text"
                                    className={inputClass}
                                    placeholder="Enter product name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                />
                                {errors.name && (
                                    <p className={errorClass}>{errors.name}</p>
                                )}
                            </div>
                            <div className={fieldClass}>
                                <label className={labelClass}>
                                    Product Code{' '}
                                    <span className="text-red-400">*</span>
                                </label>
                                <input
                                    type="text"
                                    className={inputClass}
                                    placeholder="Enter product code"
                                    maxLength={10}
                                    value={data.code}
                                    onChange={(e) =>
                                        setData('code', e.target.value)
                                    }
                                />
                                {errors.code && (
                                    <p className={errorClass}>{errors.code}</p>
                                )}
                            </div>
                            <div className={fieldClass}>
                                <label className={labelClass}>
                                    Category{' '}
                                    <span className="text-red-400">*</span>
                                </label>
                                <input
                                    type="text"
                                    className={inputClass}
                                    placeholder="Enter category"
                                    value={data.category}
                                    onChange={(e) =>
                                        setData('category', e.target.value)
                                    }
                                />
                                {errors.category && (
                                    <p className={errorClass}>
                                        {errors.category}
                                    </p>
                                )}
                            </div>
                            <div className={fieldClass}>
                                <label className={labelClass}>
                                    Status{' '}
                                    <span className="text-red-400">*</span>
                                </label>
                                <select
                                    className={inputClass}
                                    value={data.status}
                                    onChange={(e) =>
                                        setData(
                                            'status',
                                            e.target.value as ProductStatus,
                                        )
                                    }
                                >
                                    {PRODUCT_STATUSES.map((s) => (
                                        <option key={s} value={s}>
                                            {s}
                                        </option>
                                    ))}
                                </select>
                                {errors.status && (
                                    <p className={errorClass}>
                                        {errors.status}
                                    </p>
                                )}
                            </div>
                            <div className={fieldClass}>
                                <label className={labelClass}>
                                    Winning Date
                                </label>
                                <DatePicker
                                    id="winning_date"
                                    fullWidth
                                    placeholder="Not marked as winning"
                                    defaultDate={
                                        product.winning_date ?? undefined
                                    }
                                    onChange={(_dates, dateStr) =>
                                        setData('winning_date', dateStr)
                                    }
                                />
                                {errors.winning_date && (
                                    <p className={errorClass}>
                                        {errors.winning_date}
                                    </p>
                                )}
                            </div>
                            <div className={`${fieldClass} sm:col-span-2`}>
                                <label className={labelClass}>
                                    Description
                                </label>
                                <textarea
                                    rows={4}
                                    className="w-full resize-none rounded-[10px] border border-black/8 bg-stone-50 px-3 py-2.5 font-mono! text-[13px]! text-gray-800 transition-all outline-none placeholder:text-gray-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                                    placeholder="Enter product description (optional)"
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                />
                                {errors.description && (
                                    <p className={errorClass}>
                                        {errors.description}
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Shops */}
                    <div className="rounded-2xl border border-black/6 bg-white p-6 dark:border-white/6 dark:bg-zinc-900">
                        <p className="mb-5 font-mono text-[10px] font-semibold tracking-widest text-gray-300 uppercase dark:text-gray-600">
                            Shops
                        </p>
                        <div className={fieldClass}>
                            <label className={labelClass}>Linked Shops</label>
                            <MultiSelect
                                options={shops.map((shop) => ({
                                    value: shop.id.toString(),
                                    label: shop.name,
                                }))}
                                selected={data.shop_ids.map(String)}
                                onChange={(selected) =>
                                    setData('shop_ids', selected.map(Number))
                                }
                                placeholder="Select shops for this product..."
                            />
                            {errors.shop_ids && (
                                <p className={errorClass}>{errors.shop_ids}</p>
                            )}
                        </div>
                    </div>

                    {/* Footer */}
                    <div className="flex items-center justify-end gap-2">
                        <button
                            type="button"
                            onClick={() =>
                                router.get(
                                    workspaces.products.index.url({
                                        workspace,
                                    }),
                                )
                            }
                            className="flex h-9 items-center rounded-lg border border-black/8 bg-stone-100 px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-200 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="flex h-9 items-center rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                        >
                            {processing ? 'Saving…' : 'Save Changes'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
};

export default Edit;
