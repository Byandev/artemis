import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { PRODUCT_STATUSES, ProductStatus } from '@/constants/product-statuses';
import { Product } from '@/types/models/Product';
import { Workspace } from '@/types/models/Workspace';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

interface ProductFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    product?: Product | null;
    workspace: Workspace;
    onSuccess?: () => void;
}

export function ProductFormDialog({
    open,
    onOpenChange,
    product,
    workspace,
    onSuccess,
}: ProductFormDialogProps) {
    const isEditing = !!product;

    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm({
            name: product?.name || '',
            code: product?.code || '',
            category: product?.category || '',
            status: product?.status || 'Testing',
            description: product?.description || '',
            _method: 'POST', // Default to POST
        });

    useEffect(() => {
        if (open && product) {
            setData({
                name: product.name,
                code: product.code,
                category: product.category,
                status: product.status,
                description: product.description || '',
                _method: 'PATCH', // Set to PATCH for spoofing when editing
            });
        } else if (open) {
            reset();
            setData('_method', 'POST');
        }
    }, [product, open]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const url = isEditing
            ? `/workspaces/${workspace.slug}/products/${product?.id}`
            : `/workspaces/${workspace.slug}/products`;

        post(url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    isEditing
                        ? 'Product updated successfully'
                        : 'Product created successfully',
                );
                onOpenChange(false);
                if (!isEditing) reset();
                onSuccess?.();
            },
            onError: () => {
                toast.error('Failed to save product. Please check the form.');
            },
        });
    };

    const handleOpenChange = (newOpen: boolean) => {
        if (!newOpen) {
            reset();
            clearErrors();
        }
        onOpenChange(newOpen);
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-[500px]">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>
                            {product ? 'Edit Product' : 'Add New Product'}
                        </DialogTitle>
                        <DialogHeader>
                            <DialogDescription>
                                {product
                                    ? 'Update the product information below.'
                                    : 'Fill in the details to create a new product.'}
                            </DialogDescription>
                        </DialogHeader>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="name">
                                Product Name{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="e.g., Product 1"
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="code">
                                Product Code{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="code"
                                value={data.code}
                                onChange={(e) =>
                                    setData(
                                        'code',
                                        e.target.value.toUpperCase(),
                                    )
                                }
                                placeholder="e.g., ABC"
                                maxLength={10}
                            />
                            <InputError message={errors.code} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="category">
                                Category{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="category"
                                value={data.category}
                                onChange={(e) =>
                                    setData('category', e.target.value)
                                }
                                placeholder="e.g., Health And Wellness"
                            />
                            <InputError message={errors.category} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="status">
                                Status{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Select
                                value={data.status}
                                onValueChange={(value) =>
                                    setData('status', value as ProductStatus)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    {PRODUCT_STATUSES.map((status) => (
                                        <SelectItem key={status} value={status}>
                                            {status}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.status} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                id="description"
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                                placeholder="Optional product description"
                                rows={3}
                            />
                            <InputError message={errors.description} />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => handleOpenChange(false)}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing
                                ? 'Saving...'
                                : product
                                  ? 'Update Product'
                                  : 'Create Product'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
