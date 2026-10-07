import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { RmoSubStatus } from '@/types/models/Pancake/OrderForDelivery';
import { Workspace } from '@/types/models/Workspace';
import { Head, router, useForm } from '@inertiajs/react';
import { Bike, Check, Pencil, Trash2, UserRound, X } from 'lucide-react';
import { type FormEventHandler, type ReactNode, useState } from 'react';

interface Props {
    workspace: Workspace;
    cxStatuses: RmoSubStatus[];
    riderStatuses: RmoSubStatus[];
}

export default function RmoStatuses({
    workspace,
    cxStatuses,
    riderStatuses,
}: Props) {
    const baseUrl = `/workspaces/${workspace.slug}/settings/rmo/statuses`;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'RMO statuses', href: baseUrl },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="RMO statuses" />

            <SettingsLayout workspace={workspace}>
                <div className="space-y-6">
                    <HeadingSmall
                        title="RMO Statuses"
                        description="The customer and rider statuses this workspace can tag RMO orders with."
                    />

                    <div className="space-y-3">
                        <StatusList
                            baseUrl={`${baseUrl}/cx`}
                            title="Customer statuses"
                            icon={
                                <UserRound className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                            }
                            statuses={cxStatuses}
                        />
                        <StatusList
                            baseUrl={`${baseUrl}/rider`}
                            title="Rider statuses"
                            icon={
                                <Bike className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                            }
                            statuses={riderStatuses}
                        />
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

function StatusList({
    baseUrl,
    title,
    icon,
    statuses,
}: {
    baseUrl: string;
    title: string;
    icon: ReactNode;
    statuses: RmoSubStatus[];
}) {
    const createForm = useForm({ name: '' });
    const editForm = useForm({ name: '' });
    const [editingId, setEditingId] = useState<number | null>(null);

    const create: FormEventHandler = (e) => {
        e.preventDefault();
        createForm.post(baseUrl, {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
    };

    const startEdit = (status: RmoSubStatus) => {
        editForm.clearErrors();
        editForm.setData('name', status.name);
        setEditingId(status.id);
    };

    const saveEdit: FormEventHandler = (e) => {
        e.preventDefault();
        if (editingId === null) return;
        editForm.put(`${baseUrl}/${editingId}`, {
            preserveScroll: true,
            onSuccess: () => setEditingId(null),
        });
    };

    const remove = (status: RmoSubStatus) => {
        if (
            !confirm(
                `Delete "${status.name}"? Orders tagged with it will be cleared.`,
            )
        )
            return;
        router.delete(`${baseUrl}/${status.id}`, { preserveScroll: true });
    };

    return (
        <div className="rounded-[12px] border border-black/8 bg-white p-4 dark:border-white/8 dark:bg-zinc-900">
            <div className="flex items-center gap-3">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-stone-100 dark:bg-zinc-800">
                    {icon}
                </div>
                <p className="text-[13px] font-medium text-gray-900 dark:text-gray-100">
                    {title}
                </p>
            </div>

            <div className="mt-3 space-y-1 border-t border-black/6 pt-3 dark:border-white/6">
                {statuses.length === 0 && (
                    <p className="text-[12px] text-gray-400 dark:text-gray-500">
                        No statuses yet.
                    </p>
                )}

                {statuses.map((status) =>
                    editingId === status.id ? (
                        <form
                            key={status.id}
                            onSubmit={saveEdit}
                            className="space-y-1"
                        >
                            <div className="flex items-center gap-2">
                                <Input
                                    value={editForm.data.name}
                                    onChange={(e) =>
                                        editForm.setData('name', e.target.value)
                                    }
                                    maxLength={50}
                                    autoFocus
                                    className="h-8"
                                />
                                <Button
                                    type="submit"
                                    size="icon"
                                    variant="ghost"
                                    disabled={editForm.processing}
                                    aria-label="Save"
                                >
                                    <Check className="h-4 w-4" />
                                </Button>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    onClick={() => setEditingId(null)}
                                    aria-label="Cancel"
                                >
                                    <X className="h-4 w-4" />
                                </Button>
                            </div>
                            <InputError message={editForm.errors.name} />
                        </form>
                    ) : (
                        <div
                            key={status.id}
                            className="flex items-center justify-between gap-2 rounded-md px-2 py-1 hover:bg-stone-50 dark:hover:bg-zinc-800/60"
                        >
                            <span className="text-[13px] text-gray-800 dark:text-gray-200">
                                {status.name}
                            </span>
                            <div className="flex items-center">
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    className="h-7 w-7"
                                    onClick={() => startEdit(status)}
                                    aria-label={`Edit ${status.name}`}
                                >
                                    <Pencil className="h-3.5 w-3.5" />
                                </Button>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    className="h-7 w-7 text-red-500 hover:text-red-600"
                                    onClick={() => remove(status)}
                                    aria-label={`Delete ${status.name}`}
                                >
                                    <Trash2 className="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </div>
                    ),
                )}

                <form onSubmit={create} className="space-y-1 pt-2">
                    <div className="flex items-center gap-2">
                        <Input
                            value={createForm.data.name}
                            onChange={(e) =>
                                createForm.setData('name', e.target.value)
                            }
                            placeholder="New status name"
                            maxLength={50}
                            className="h-8"
                        />
                        <Button
                            type="submit"
                            size="sm"
                            disabled={
                                createForm.processing ||
                                !createForm.data.name.trim()
                            }
                        >
                            Add
                        </Button>
                    </div>
                    <InputError message={createForm.errors.name} />
                </form>
            </div>
        </div>
    );
}
