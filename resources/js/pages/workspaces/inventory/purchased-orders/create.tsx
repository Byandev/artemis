import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';
import { Workspace } from '@/types/models/Workspace';
import ComponentCard from '@/components/common/ComponentCard';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Product } from '@/types/models/Product';

interface Props {
    workspace: Workspace;
    products: Product[]
}

const Create = ({ workspace, products }: Props) => {
    console.log(products)
    const { data, setData, post, processing, errors } = useForm({
        issued_at: '',
        delivered_at: '',
        cog_amount: '',
        delivery_amount: '',
        total_amount: '',
        status: 'PENDING',
        items: [
            { product_id: '', quantity: '', amount: '' }
        ]
    });

    return (
        <AppLayout>
            <Head
                title={`${workspace.name} - Inventory | Create Purchase Order`}
            />

            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <h2
                        className="text-xl font-semibold text-gray-800 dark:text-white/90"
                        x-text="pageName"
                    >
                        Purchased Orders
                    </h2>
                </div>

                <div className="space-y-5 sm:space-y-6">
                    <ComponentCard desc="Create Purchase Order">
                        <div>
                            <form className="space-y-6">
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="issued_at">
                                            Issued Date *
                                        </Label>
                                        <Input
                                            type={'date'}
                                            id="issued_at"
                                            value={data.issued_at}
                                            onChange={(e) =>
                                                setData(
                                                    'issued_at',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Enter product name"
                                            className={
                                                errors.issued_at
                                                    ? 'border-destructive'
                                                    : ''
                                            }
                                        />
                                        {errors.issued_at && (
                                            <p className="text-destructive text-sm">
                                                {errors.issued_at}
                                            </p>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="delivered_at">
                                            Delivered Date
                                        </Label>
                                        <Input
                                            type={'date'}
                                            id="delivered_at"
                                            value={data.delivered_at}
                                            onChange={(e) =>
                                                setData(
                                                    'issued_at',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Enter product name"
                                            className={
                                                errors.delivered_at
                                                    ? 'border-destructive'
                                                    : ''
                                            }
                                        />
                                        {errors.delivered_at && (
                                            <p className="text-destructive text-sm">
                                                {errors.delivered_at}
                                            </p>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="issued_at">
                                            Delivery Amount
                                        </Label>
                                        <Input
                                            type={'number'}
                                            id="delivery_amount"
                                            value={data.delivery_amount}
                                            onChange={(e) =>
                                                setData(
                                                    'delivery_amount',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Enter delivery amount"
                                            className={
                                                errors.delivery_amount
                                                    ? 'border-destructive'
                                                    : ''
                                            }
                                        />
                                        {errors.delivery_amount && (
                                            <p className="text-destructive text-sm">
                                                {errors.delivery_amount}
                                            </p>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="status">Status</Label>

                                        <Select
                                            value={data.status}
                                            onValueChange={(value) =>
                                                setData('status', value)
                                            }
                                        >
                                            <SelectTrigger
                                                className={
                                                    errors.status
                                                        ? 'border-destructive'
                                                        : ''
                                                }
                                            >
                                                <SelectValue placeholder="Select status" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={'PENDING'}>
                                                    Pending
                                                </SelectItem>
                                                <SelectItem value={'DELIVERED'}>
                                                    Delivered
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {errors.status && (
                                            <p className="text-destructive text-sm">
                                                {errors.status}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div>
                                    {data.items.map((item, i) => (
                                        <div
                                            key={`item-${i}`}
                                            className="grid grid-cols-3 gap-4"
                                        >
                                            <div className="grid gap-2">
                                                <Label
                                                    htmlFor={`product_id-${i}`}
                                                >
                                                    Product
                                                </Label>

                                                <Select
                                                    value={item.product_id}
                                                    onValueChange={(value) =>
                                                        setData(
                                                            'items',
                                                            [...data.items].map(
                                                                (item, j) => ({
                                                                    ...item,
                                                                    product_id:
                                                                        i === j
                                                                            ? value
                                                                            : item.product_id,
                                                                }),
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger
                                                        className={
                                                            errors.status
                                                                ? 'border-destructive'
                                                                : ''
                                                        }
                                                    >
                                                        <SelectValue placeholder="Select product" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {products.map(
                                                            (product) => (
                                                                <SelectItem
                                                                    key={`${i}.product-option-${product.id}`}
                                                                    value={
                                                                        product.id
                                                                    }
                                                                >
                                                                    {product.name}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                                {errors.status && (
                                                    <p className="text-destructive text-sm">
                                                        {errors.status}
                                                    </p>
                                                )}
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="issued_at">
                                                    Quantity
                                                </Label>
                                                <Input
                                                    type={'number'}
                                                    id="delivery_amount"
                                                    value={data.delivery_amount}
                                                    onChange={(e) =>
                                                        setData(
                                                            'delivery_amount',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Enter delivery amount"
                                                    className={
                                                        errors.delivery_amount
                                                            ? 'border-destructive'
                                                            : ''
                                                    }
                                                />
                                                {errors.delivery_amount && (
                                                    <p className="text-destructive text-sm">
                                                        {errors.delivery_amount}
                                                    </p>
                                                )}
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="issued_at">
                                                    Quantity
                                                </Label>
                                                <Input
                                                    type={'number'}
                                                    id="delivery_amount"
                                                    value={item.quantity}
                                                    onChange={(e) =>
                                                        setData(
                                                            'delivery_amount',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Enter delivery amount"
                                                    className={
                                                        errors.delivery_amount
                                                            ? 'border-destructive'
                                                            : ''
                                                    }
                                                />
                                                {errors.delivery_amount && (
                                                    <p className="text-destructive text-sm">
                                                        {errors.delivery_amount}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </form>
                        </div>
                    </ComponentCard>
                </div>
            </div>
        </AppLayout>
    );
}


export default Create;
