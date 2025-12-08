import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Product } from '@/types/models/Product';
import { Workspace } from '@/types/models/Workspace';
import workspaces from '@/routes/workspaces';
import InputError from '@/components/input-error';

interface ProductFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    product?: Product;
    workspace: Workspace;
}

export function ProductFormDialog({
    open,
    onOpenChange,
    product,
    workspace,
}: ProductFormDialogProps) {
    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm({
            name: product?.name || '',
            code: product?.code || '',
            category: product?.category || '',
            status: product?.status || 'Testing',
            description: product?.description || '',
        });

    useEffect(() => {
        if (product) {
            setData({
                name: product.name,
                code: product.code,
                category: product.category,
                status: product.status,
                description: product.description || '',
            });
        } else {
            reset();
        }
    }, [product]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (product) {
            put(workspaces.products.update.url({ workspace, product }), {
                onSuccess: () => {
                    onOpenChange(false);
                    reset();
                },
            });
        } else {
            post(workspaces.products.store.url({ workspace }), {
                onSuccess: () => {
                    onOpenChange(false);
                    reset();
                },
            });
        }
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
                        <DialogDescription>
                            {product
                                ? 'Update the product information below.'
                                : 'Fill in the details to create a new product.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="name">
                                Product Name <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="e.g., Product 1"
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="code">
                                Product Code <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="code"
                                value={data.code}
                                onChange={(e) =>
                                    setData('code', e.target.value.toUpperCase())
                                }
                                placeholder="e.g., ABC"
                                maxLength={10}
                            />
                            <InputError message={errors.code} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="category">
                                Category <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="category"
                                value={data.category}
                                onChange={(e) => setData('category', e.target.value)}
                                placeholder="e.g., Health And Wellness"
                            />
                            <InputError message={errors.category} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="status">
                                Status <span className="text-destructive">*</span>
                            </Label>
                            <Select
                                value={data.status}
                                onValueChange={(value) =>
                                    setData(
                                        'status',
                                        value as 'Scaling' | 'Testing' | 'Failed' | 'Inactive'
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="Scaling">Scaling</SelectItem>
                                    <SelectItem value="Testing">Testing</SelectItem>
                                    <SelectItem value="Failed">Failed</SelectItem>
                                    <SelectItem value="Inactive">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={errors.status} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                id="description"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
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
