import { Workspace } from '@/types/models/Workspace';
import { Product } from '@/types/models/Product';
import { Page } from '@/types/models/Page';
import ProductLayout from '@/pages/workspaces/products/partials/layout';
import { Head, useForm } from '@inertiajs/react';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { MultiSelect } from '@/components/ui/multi-select';
import workspaces from '@/routes/workspaces';
import ComponentCard from '@/components/common/ComponentCard';

interface PageProps {
    workspace: Workspace;
    product: Product & { pages?: Page[] };
    pages: Page[];
}

const Edit = ({ workspace, product, pages }: PageProps) => {
    const { data, setData, put, processing, errors } = useForm({
        name: product.name || '',
        code: product.code || '',
        category: product.category || '',
        status: product.status as 'Scaling' | 'Testing' | 'Failed' | 'Inactive',
        description: product.description || '',
        page_ids: product.pages?.map(p => p.id) || [] as number[],
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(workspaces.products.update.url({ workspace, product }), {
            onSuccess: () => console.log('Product updated successfully'),
            onError: (errors) => console.error('Form errors:', errors),
        });
    };

    return (
        <ProductLayout workspace={workspace}>
            <Head title={`${workspace.name} - Edit Product`} />

            <ComponentCard title={"Edit Product"} desc={"Update the details for this product"}>
                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Product Name *</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Enter product name"
                                className={errors.name ? 'border-destructive' : ''}
                            />
                            {errors.name && (
                                <p className="text-sm text-destructive">{errors.name}</p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="code">Product Code *</Label>
                            <Input
                                id="code"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value)}
                                placeholder="Enter product code"
                                maxLength={10}
                                className={errors.code ? 'border-destructive' : ''}
                            />
                            {errors.code && (
                                <p className="text-sm text-destructive">{errors.code}</p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="category">Category *</Label>
                            <Input
                                id="category"
                                value={data.category}
                                onChange={(e) => setData('category', e.target.value)}
                                placeholder="Enter category"
                                className={errors.category ? 'border-destructive' : ''}
                            />
                            {errors.category && (
                                <p className="text-sm text-destructive">{errors.category}</p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="status">Status *</Label>
                            <Select
                                value={data.status}
                                onValueChange={(value: 'Scaling' | 'Testing' | 'Failed' | 'Inactive') =>
                                    setData('status', value)
                                }
                            >
                                <SelectTrigger className={errors.status ? 'border-destructive' : ''}>
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="Testing">Testing</SelectItem>
                                    <SelectItem value="Scaling">Scaling</SelectItem>
                                    <SelectItem value="Failed">Failed</SelectItem>
                                    <SelectItem value="Inactive">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                            {errors.status && (
                                <p className="text-sm text-destructive">{errors.status}</p>
                            )}
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>
                        <Textarea
                            id="description"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder="Enter product description (optional)"
                            rows={4}
                            className={errors.description ? 'border-destructive' : ''}
                        />
                        {errors.description && (
                            <p className="text-sm text-destructive">{errors.description}</p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="pages">Pages (Optional)</Label>
                        <MultiSelect
                            options={pages.map(page => ({
                                value: page.id.toString(),
                                label: page.name,
                            }))}
                            selected={data.page_ids.map(String)}
                            onChange={(selected) => setData('page_ids', selected.map(Number))}
                            placeholder="Select pages for this product..."
                        />
                        {errors.page_ids && (
                            <p className="text-sm text-destructive">{errors.page_ids}</p>
                        )}
                    </div>

                    <div className="flex justify-end gap-3 pt-4 border-t">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => window.history.back()}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Updating...' : 'Update Product'}
                        </Button>
                    </div>
                </form>
            </ComponentCard>
        </ProductLayout>
    );
};

export default Edit;
