import { Workspace } from '@/types/models/Workspace';
import AppLayout from '@/layouts/app-layout';
import { useForm } from '@inertiajs/react';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import workspaces from '@/routes/workspaces';

interface PageProps {
    workspace: Workspace;
}

const Create = ({ workspace}: PageProps) => {
    const { data, setData, post, put, processing, errors, reset } = useForm({
        title: '',
        description: '',
        image: null
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        post(workspaces.products.store.url({ workspace }), {
            onSuccess: () => {
                reset();
            },
        });
    };

    return (
        <AppLayout>
            <div className='px-4 py-6'>
                <form className="space-y-4" onSubmit={handleSubmit}>
                    <div className="grid gap-2">
                        <Label htmlFor="image">Image</Label>
                        <Input
                            id="image"
                            type="file"
                            onChange={(e) => {
                                if (e.target?.files?.length) {
                                    setData('image', e.target.files[0])
                                }
                            }}
                            placeholder="Product Title"
                            aria-invalid={!!errors.title}
                        />
                        {errors.title && (
                            <p className="text-sm text-destructive">{errors.title}</p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="title">Title</Label>
                        <Input
                            id="title"
                            type="text"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            placeholder="Product Title"
                            aria-invalid={!!errors.title}
                        />
                        {errors.title && (
                            <p className="text-sm text-destructive">{errors.title}</p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>
                        <Textarea
                            id="description"
                            rows={100}
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder="Description"
                            aria-invalid={!!errors.description}
                        />
                        {errors.description && (
                            <p className="text-sm text-destructive">{errors.description}</p>
                        )}
                    </div>

                    <Button type="submit" disabled={processing}>
                        {processing ? 'Saving Product...' : 'Save Product'}
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}

export default Create;
