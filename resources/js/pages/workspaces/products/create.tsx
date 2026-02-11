import { Workspace } from '@/types/models/Workspace';
import { Page } from '@/types/models/Page';
import ProductLayout from '@/pages/workspaces/products/partials/layout';
import { Head, useForm } from '@inertiajs/react';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { MultiSelect } from '@/components/ui/multi-select';
import { ArrowLeft } from 'lucide-react';
import workspaces from '@/routes/workspaces';
import ComponentCard from '@/components/common/ComponentCard';

interface PageProps {
    workspace: Workspace;
    pages: Page[];
}

const Create = ({ workspace, pages }: PageProps) => {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        code: '',
        category: '',
        status: 'Testing' as 'Scaling' | 'Testing' | 'Failed' | 'Inactive',
        description: '',
        page_ids: [] as number[],
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        console.log('Form data before submit:', data);
        post(workspaces.products.store.url({ workspace }), {
            onSuccess: () => console.log('Product created successfully'),
            onError: (errors) => console.error('Form errors:', errors),
        });
    };

    return (
        <ProductLayout workspace={workspace}>
            <Head title={`${workspace.name} - Create Product`} />

            <ComponentCard title="Create Product" desc={'Add a new product to your workspace'}>
                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="gap-2 grid">
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

                        <div className="gap-2 grid">
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

                        <div className="gap-2 grid">
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

                        <div className="gap-2 grid">
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

                    <div className="gap-2 grid">
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

                    <div className="gap-2 grid">
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
                            {processing ? 'Creating...' : 'Create Product'}
                        </Button>
                    </div>
                </form>
            </ComponentCard>
        </ProductLayout>
    );
};

export default Create;
