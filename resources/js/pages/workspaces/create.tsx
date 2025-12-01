import { FormEventHandler, use } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AuthLayout from '@/layouts/auth-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function WorkspaceCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/workspaces');
    };

    return (
        <AuthLayout title={'Create New Workspace'} description={'Workspaces are a share environments where teams can work together.'}>
            <Head title="Create New Workspace" />

            <div className="space-y-6">
                {/* <div className="space-y-2 text-center">
                    <h1 className="text-3xl font-bold">Create New Workspace</h1>
                    <p className="text-muted-foreground">
                        Workspaces are a share environments where teams can work together.
                    </p>
                </div> */}

                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="name">Workspace Name</Label>
                        <Input
                            id="name"
                            type="text"
                            name="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="Team Workspace"
                            required
                            autoFocus
                        />
                        {errors.name && (
                            <p className="text-sm text-destructive">{errors.name}</p>
                        )}
                    </div>

                    <Button type="submit" className="w-full" disabled={processing}>
                        {processing ? 'Creating workspace…' : 'Create workspace'}
                    </Button>

                    <Button
                        type="button"
                        variant="ghost"
                        className="w-full"
                        disabled={processing}
                        onClick={() => window.history.back()}
                    >
                        Back
                    </Button>
                </form>
            </div>
        </AuthLayout>
    );
}
