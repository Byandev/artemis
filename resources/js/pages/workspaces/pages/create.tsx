import React from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Page } from '@/types/models/Page';
import workspaces from '@/routes/workspaces';
import { Workspace } from '@/types/models/Workspace';
import { Switch } from '@headlessui/react';
import AppLayout from '@/layouts/app-layout';
import ComponentCard from '@/components/common/ComponentCard';

interface PageFormProps {
    page?: Page;
    workspace: Workspace;
}

export default function PageForm({ page, workspace }: PageFormProps) {
    const isEditing = !!page;

    const { data, setData, post, put, processing, errors } = useForm({
        id: page?.id || '',
        shop_id: page?.shop_id || '',
        name: page?.name || '',
        pos_token: page?.pos_token || '',
        botcake_token: page?.botcake_token || '',
        infotxt_token: page?.infotxt_token || '',
        infotxt_user_id: page?.infotxt_user_id || '',
        pancake_token: page?.pancake_token || '',
        parcel_journey_flow_id: page?.parcel_journey_flow_id || '',
        parcel_journey_custom_field_id: page?.parcel_journey_custom_field_id || '',
        parcel_journey_enabled: Boolean(page?.parcel_journey_enabled) || false,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isEditing) {
            put(workspaces.pages.update.url({ workspace, page }), {
                onError: (e) => console.log(e)
            });
        } else {
            post(workspaces.pages.store.url({ workspace }), {
                onError: (e) => console.log(e),
            });
        }
    };

    return (
        <AppLayout>
            <Head title={isEditing ? 'Edit Page' : 'Create Page'} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-xl font-semibold text-gray-800 dark:text-white/90">
                        Pages
                    </h2>
                </div>

                <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                    <ComponentCard
                        title={isEditing ? 'Edit Page' : 'Create New Page'}
                        desc={isEditing ? 'Update the page information below.' : 'Fill in the details to create a new page.'}
                    >
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {/* ID Field */}
                                <div className="grid gap-2">
                                    <Label htmlFor="id">ID</Label>
                                    <Input
                                        id="id"
                                        type="number"
                                        value={data.id}
                                        onChange={(e) => setData('id', e.target.value)}
                                        placeholder="Enter ID"
                                        className={errors.id ? 'border-destructive' : ''}
                                    />
                                    {errors.id && <p className="text-destructive text-sm">{errors.id}</p>}
                                </div>

                                {/* Shop ID Field */}
                                <div className="grid gap-2">
                                    <Label htmlFor="shop_id">Shop ID</Label>
                                    <Input
                                        id="shop_id"
                                        type="number"
                                        value={data.shop_id}
                                        onChange={(e) => setData('shop_id', e.target.value)}
                                        placeholder="Enter shop ID"
                                        className={errors.shop_id ? 'border-destructive' : ''}
                                    />
                                    {errors.shop_id && <p className="text-destructive text-sm">{errors.shop_id}</p>}
                                </div>

                                {/* Name Field */}
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="Enter page name"
                                        className={errors.name ? 'border-destructive' : ''}
                                    />
                                    {errors.name && <p className="text-destructive text-sm">{errors.name}</p>}
                                </div>

                                {/* POS Token */}
                                <div className="grid gap-2">
                                    <Label htmlFor="pos_token">POS Token</Label>
                                    <Input
                                        id="pos_token"
                                        value={data.pos_token}
                                        onChange={(e) => setData('pos_token', e.target.value)}
                                        placeholder="Enter POS token"
                                    />
                                </div>

                                {/* Pancake Token */}
                                <div className="grid gap-2">
                                    <Label htmlFor="pancake_token">Pancake Token</Label>
                                    <Input
                                        id="pancake_token"
                                        value={data.pancake_token}
                                        onChange={(e) => setData('pancake_token', e.target.value)}
                                        placeholder="Enter Pancake token"
                                    />
                                </div>

                                {/* Botcake Token */}
                                <div className="grid gap-2">
                                    <Label htmlFor="botcake_token">Botcake Token</Label>
                                    <Input
                                        id="botcake_token"
                                        value={data.botcake_token}
                                        onChange={(e) => setData('botcake_token', e.target.value)}
                                        placeholder="Enter Botcake token"
                                    />
                                </div>

                                {/* Parcel Journey Flow Id */}
                                <div className="grid gap-2">
                                    <Label htmlFor="parcel_journey_flow_id">Parcel Journey Flow Id</Label>
                                    <Input
                                        id="parcel_journey_flow_id"
                                        value={data.parcel_journey_flow_id}
                                        onChange={(e) => setData('parcel_journey_flow_id', e.target.value)}
                                    />
                                </div>

                                {/* Parcel Journey Custom Field Id */}
                                <div className="grid gap-2">
                                    <Label htmlFor="parcel_journey_custom_field_id">Parcel Journey Custom Field Id</Label>
                                    <Input
                                        id="parcel_journey_custom_field_id"
                                        value={data.parcel_journey_custom_field_id}
                                        onChange={(e) => setData('parcel_journey_custom_field_id', e.target.value)}
                                    />
                                </div>

                                {/* Infotxt Token */}
                                <div className="grid gap-2">
                                    <Label htmlFor="infotxt_token">Infotxt Token</Label>
                                    <Input
                                        id="infotxt_token"
                                        value={data.infotxt_token}
                                        onChange={(e) => setData('infotxt_token', e.target.value)}
                                    />
                                </div>

                                {/* Infotxt User Id */}
                                <div className="grid gap-2">
                                    <Label htmlFor="infotxt_user_id">Infotxt User Id</Label>
                                    <Input
                                        id="infotxt_user_id"
                                        value={data.infotxt_user_id}
                                        onChange={(e) => setData('infotxt_user_id', e.target.value)}
                                    />
                                </div>
                            </div>

                            {/* Toggle Switch */}
                            <div className="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                                <Label htmlFor="parcel_journey_enabled">Enable Parcel Journey</Label>
                                <Switch
                                    checked={data.parcel_journey_enabled}
                                    onChange={(value) => setData('parcel_journey_enabled', value)}
                                    className="group relative flex h-7 w-14 cursor-pointer rounded-full border bg-gray-300 p-1 transition-colors duration-200 ease-in-out data-checked:bg-brand-500"
                                >
                                    <span className="pointer-events-none inline-block size-5 translate-x-0 rounded-full bg-white shadow-lg transition duration-200 ease-in-out group-data-checked:translate-x-7" />
                                </Switch>
                            </div>

                            {/* Actions */}
                            <div className="flex justify-end gap-3 pt-4 border-t">
                                <Link href={workspaces.pages.index({ workspace })}>
                                    <Button type="button" variant="outline" disabled={processing}>
                                        Cancel
                                    </Button>
                                </Link>
                                <Button type="submit" disabled={processing}>
                                    {processing ? (isEditing ? 'Updating...' : 'Creating...') : (isEditing ? 'Update Page' : 'Create Page')}
                                </Button>
                            </div>
                        </form>
                    </ComponentCard>
                </div>
            </div>
        </AppLayout>
    );
}