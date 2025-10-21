import { FormEventHandler } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AuthLayout from '@/layouts/auth-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Props {
    userName: string;
}

export default function WorkspaceSetup({ userName }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: `${userName}'s Workspace`,
        description: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/workspaces/setup');
    };

    return (
        <AuthLayout title={'Create Your Workspace'} description={''}>
            <Head title="Create Your Workspace" />

            <div className="space-y-6">
                <div className="space-y-2 text-center">
                    <h1 className="text-3xl font-bold">
                        Create Your Workspace
                    </h1>
                    <p className="text-muted-foreground">
                        Welcome! Let's set up your workspace to get started.
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="name">Workspace Name</Label>
                        <Input
                            id="name"
                            type="text"
                            name="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="My Awesome Workspace"
                            required
                            autoFocus
                        />
                        {errors.name && (
                            <p className="text-sm text-destructive">
                                {errors.name}
                            </p>
                        )}
                        <p className="text-xs text-muted-foreground">
                            You can always change this later.
                        </p>
                    </div>

                    {/*<div className="space-y-2">*/}
                    {/*    <Label htmlFor="description">*/}
                    {/*        Description <span className="text-muted-foreground">(Optional)</span>*/}
                    {/*    </Label>*/}
                    {/*    <Textarea*/}
                    {/*        id="description"*/}
                    {/*        name="description"*/}
                    {/*        value={data.description}*/}
                    {/*        onChange={(e) => setData('description', e.target.value)}*/}
                    {/*        placeholder="Describe your workspace..."*/}
                    {/*        rows={3}*/}
                    {/*    />*/}
                    {/*    {errors.description && (*/}
                    {/*        <p className="text-sm text-destructive">{errors.description}</p>*/}
                    {/*    )}*/}
                    {/*</div>*/}

                    <Button
                        type="submit"
                        className="w-full"
                        disabled={processing}
                    >
                        {processing
                            ? 'Creating Workspace...'
                            : 'Create Workspace'}
                    </Button>
                </form>
            </div>
        </AuthLayout>
    );
}
