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
import workspaces from '@/routes/workspaces';
import { User } from '@/types';
import { Page } from '@/types/models/Page';
import { Workspace } from '@/types/models/Workspace';
import { Switch } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import React, { useEffect } from 'react';
import Select from 'react-select';

interface PageFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    page?: Page;
    workspace: Workspace;
    users: User[];
}

// Custom styles for react-select to match your design system
const selectStyles = {
    control: (base: any, state: any) => ({
        ...base,
        borderColor: state.isFocused ? '#3b82f6' : '#e5e7eb',
        boxShadow: state.isFocused
            ? '0 0 0 2px rgba(59, 130, 246, 0.1)'
            : 'none',
        '&:hover': {
            borderColor: '#3b82f6',
        },
        minHeight: '38px',
        borderRadius: '0.375rem',
    }),
    option: (base: any, state: any) => ({
        ...base,
        backgroundColor: state.isSelected
            ? '#3b82f6'
            : state.isFocused
              ? '#f3f4f6'
              : 'white',
        color: state.isSelected ? 'white' : '#111827',
        cursor: 'pointer',
        '&:active': {
            backgroundColor: state.isSelected ? '#3b82f6' : '#e5e7eb',
        },
    }),
    menu: (base: any) => ({
        ...base,
        zIndex: 50,
    }),
};

export function PageFormDialog({
    open,
    onOpenChange,
    page,
    workspace,
    users,
}: PageFormDialogProps) {
    const isEditing = !!page;

    const { data, setData, post, put, processing, errors, reset } = useForm({
        id: page?.id || '',
        shop_id: page?.shop_id || '',
        name: page?.name || '',
        pos_token: page?.pos_token || '',
        botcake_token: page?.botcake_token || '',
        infotxt_token: page?.infotxt_token || '',
        infotxt_user_id: page?.infotxt_user_id || '',
        pancake_token: page?.pancake_token || '',
        parcel_journey_flow_id: page?.parcel_journey_flow_id || '',
        parcel_journey_custom_field_id:
            page?.parcel_journey_custom_field_id || '',
        parcel_journey_enabled: page?.parcel_journey_enabled || false,
        owner_id: page?.owner_id || '',
    });

    // setData and reset from useForm are stable references
    useEffect(() => {
        if (page) {
            setData({
                id: page.id || '',
                shop_id: page.shop_id || '',
                name: page.name || '',
                pos_token: page.pos_token || '',
                botcake_token: page.botcake_token || '',
                infotxt_token: page.infotxt_token || '',
                infotxt_user_id: page.infotxt_user_id || '',
                pancake_token: page?.pancake_token || '',
                parcel_journey_flow_id: page?.parcel_journey_flow_id || '',
                parcel_journey_custom_field_id:
                    page?.parcel_journey_custom_field_id || '',
                parcel_journey_enabled: Boolean(page?.parcel_journey_enabled),
                owner_id: page?.owner_id || '',
            });
        } else {
            reset();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [page, open]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isEditing) {
            put(workspaces.pages.update.url({ workspace, page }), {
                onSuccess: () => {
                    onOpenChange(false);
                    reset();
                },
                onError: (e) => console.log(e),
            });
        } else {
            post(workspaces.pages.store.url({ workspace }), {
                onSuccess: () => {
                    onOpenChange(false);
                    reset();
                },
                onError: (e) => console.log(e),
            });
        }
    };

    // Transform users for react-select
    const userOptions = users.map((user) => ({
        value: user.id,
        label: `${user.name}`,
    }));

    // Find the selected user
    const selectedUser = userOptions.find(
        (option) => option.value === data.owner_id,
    );


    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-[600px]">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>
                            {isEditing ? 'Edit Page' : 'Create New Page'}
                        </DialogTitle>
                        <DialogDescription>
                            {isEditing
                                ? 'Update the page information below.'
                                : 'Fill in the details to create a new page.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="id">ID</Label>
                            <Input
                                id="id"
                                type="number"
                                value={data.id}
                                onChange={(e) => setData('id', e.target.value)}
                                placeholder="Enter ID"
                                aria-invalid={!!errors.id}
                            />
                            {errors.id && (
                                <p className="text-destructive text-sm">
                                    {errors.id}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="shop_id">Shop ID</Label>
                            <Input
                                id="shop_id"
                                type="number"
                                value={data.shop_id}
                                onChange={(e) =>
                                    setData('shop_id', e.target.value)
                                }
                                placeholder="Enter shop ID"
                                aria-invalid={!!errors.shop_id}
                            />
                            {errors.shop_id && (
                                <p className="text-destructive text-sm">
                                    {errors.shop_id}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="Enter page name"
                                aria-invalid={!!errors.name}
                            />
                            {errors.name && (
                                <p className="text-destructive text-sm">
                                    {errors.name}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="pos_token">POS Token</Label>
                            <Input
                                id="pos_token"
                                value={data.pos_token}
                                onChange={(e) =>
                                    setData('pos_token', e.target.value)
                                }
                                placeholder="Enter POS token"
                                aria-invalid={!!errors.pos_token}
                            />
                            {errors.pos_token && (
                                <p className="text-destructive text-sm">
                                    {errors.pos_token}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="pancake_token">Pancake Token</Label>
                            <Input
                                id="pancake_token"
                                value={data.pancake_token}
                                onChange={(e) =>
                                    setData('pancake_token', e.target.value)
                                }
                                placeholder="Enter Pancake token"
                                aria-invalid={!!errors.pancake_token}
                            />
                            {errors.pancake_token && (
                                <p className="text-destructive text-sm">
                                    {errors.pancake_token}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="botcake_token">Botcake Token</Label>
                            <Input
                                id="botcake_token"
                                value={data.botcake_token}
                                onChange={(e) =>
                                    setData('botcake_token', e.target.value)
                                }
                                placeholder="Enter Botcake token"
                                aria-invalid={!!errors.botcake_token}
                            />
                            {errors.botcake_token && (
                                <p className="text-destructive text-sm">
                                    {errors.botcake_token}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="parcel_journey_flow_id">
                                Parcel Journey Flow ID
                            </Label>
                            <Input
                                id="parcel_journey_flow_id"
                                value={data.parcel_journey_flow_id}
                                onChange={(e) =>
                                    setData(
                                        'parcel_journey_flow_id',
                                        e.target.value,
                                    )
                                }
                                placeholder="Enter Parcel Journey Flow ID"
                                aria-invalid={!!errors.parcel_journey_flow_id}
                            />
                            {errors.parcel_journey_flow_id && (
                                <p className="text-destructive text-sm">
                                    {errors.parcel_journey_flow_id}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="parcel_journey_custom_field_id">
                                Parcel Journey Custom Field ID
                            </Label>
                            <Input
                                id="parcel_journey_custom_field_id"
                                value={data.parcel_journey_custom_field_id}
                                onChange={(e) =>
                                    setData(
                                        'parcel_journey_custom_field_id',
                                        e.target.value,
                                    )
                                }
                                placeholder="Enter Parcel Journey Custom Field ID"
                                aria-invalid={
                                    !!errors.parcel_journey_custom_field_id
                                }
                            />
                            {errors.parcel_journey_custom_field_id && (
                                <p className="text-destructive text-sm">
                                    {errors.parcel_journey_custom_field_id}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="infotxt_token">Infotxt Token</Label>
                            <Input
                                id="infotxt_token"
                                value={data.infotxt_token}
                                onChange={(e) =>
                                    setData('infotxt_token', e.target.value)
                                }
                                placeholder="Enter Infotxt token"
                                aria-invalid={!!errors.infotxt_token}
                            />
                            {errors.infotxt_token && (
                                <p className="text-destructive text-sm">
                                    {errors.infotxt_token}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="infotxt_user_id">
                                Infotxt User ID
                            </Label>
                            <Input
                                id="infotxt_user_id"
                                value={data.infotxt_user_id}
                                onChange={(e) =>
                                    setData('infotxt_user_id', e.target.value)
                                }
                                placeholder="Enter Infotxt user ID"
                                aria-invalid={!!errors.infotxt_user_id}
                            />
                            {errors.infotxt_user_id && (
                                <p className="text-destructive text-sm">
                                    {errors.infotxt_user_id}
                                </p>
                            )}
                        </div>

                        {/* Owner/User Selection */}
                        <div className="grid gap-2">
                            <Label htmlFor="owner_id">
                                Owner{' '}
                                <span className="ml-1 text-xs text-gray-400">
                                    (Select a user)
                                </span>
                            </Label>
                            <Select
                                id="owner_id"
                                options={userOptions}
                                value={selectedUser}
                                onChange={(option) =>
                                    setData('owner_id', option?.value || '')
                                }
                                placeholder="Search for a user..."
                                isClearable
                                isSearchable
                                styles={selectStyles}
                                noOptionsMessage={() => 'No users found'}
                                aria-invalid={!!errors.owner_id}
                            />
                            {errors.owner_id && (
                                <p className="text-destructive text-sm">
                                    {errors.owner_id}
                                </p>
                            )}
                            {users.length === 0 && (
                                <p className="text-sm text-amber-600">
                                    No users available. Please add users first.
                                </p>
                            )}
                        </div>

                        <div className="flex items-center justify-between gap-2">
                            <Label htmlFor="parcel_journey_enabled">
                                Enable Parcel Journey
                            </Label>
                            <Switch
                                checked={data.parcel_journey_enabled}
                                onChange={(value) =>
                                    setData('parcel_journey_enabled', value)
                                }
                                className="group relative flex h-7 w-14 cursor-pointer rounded-full border bg-white/10 p-1 ease-in-out focus:not-data-focus:outline-none data-checked:bg-white/10 data-focus:outline data-focus:outline-white"
                            >
                                <span
                                    aria-hidden="true"
                                    className="pointer-events-none inline-block size-5 translate-x-0 rounded-full bg-black shadow-lg ring-0 transition duration-200 ease-in-out group-data-checked:translate-x-7"
                                />
                            </Switch>
                            {errors.parcel_journey_enabled && (
                                <p className="text-destructive text-sm">
                                    {errors.parcel_journey_enabled}
                                </p>
                            )}
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing
                                ? 'Saving...'
                                : isEditing
                                  ? 'Update Page'
                                  : 'Create Page'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
