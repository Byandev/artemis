import AppLayout from '@/layouts/app-layout';
import PageHeader from '@/components/common/PageHeader';
import { Head, router, useForm } from '@inertiajs/react';
import { Workspace } from '@/types/models/Workspace';
import { Role } from '@/types/models/Role';
import { ArrowLeft, ShieldCheck } from 'lucide-react';
import { toast, Toaster } from 'sonner';

interface Permission {
    id: number;
    name: string;
    granted: boolean;
}

interface PermissionGroup {
    category: string;
    permissions: Permission[];
}

interface Props {
    workspace: Workspace;
    role: Role;
    groups: PermissionGroup[];
}

export default function RolePermissions({ workspace, role, groups }: Props) {
    const initialIds = groups
        .flatMap((g) => g.permissions)
        .filter((p) => p.granted)
        .map((p) => p.id);

    const { data, setData, put, processing } = useForm<{ permission_ids: number[] }>({
        permission_ids: initialIds,
    });

    const toggle = (id: number) => {
        setData('permission_ids',
            data.permission_ids.includes(id)
                ? data.permission_ids.filter((i) => i !== id)
                : [...data.permission_ids, id]
        );
    };

    const toggleAll = (permissions: Permission[]) => {
        const ids = permissions.map((p) => p.id);
        const allGranted = ids.every((id) => data.permission_ids.includes(id));
        if (allGranted) {
            setData('permission_ids', data.permission_ids.filter((id) => !ids.includes(id)));
        } else {
            const merged = Array.from(new Set([...data.permission_ids, ...ids]));
            setData('permission_ids', merged);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/workspaces/${workspace.slug}/roles/${role.id}/permissions`, {
            preserveScroll: true,
            onSuccess: () => toast.success('Permissions saved.'),
        });
    };

    return (
        <AppLayout>
            <Head title={`${role.name} — Permissions`} />
            <Toaster position="top-right" richColors />

            <div className="w-full space-y-6 p-4 md:p-6">
                <PageHeader
                    title={`${role.name} — Permissions`}
                    description="Toggle which actions this role is allowed to perform."
                >
                    <button
                        type="button"
                        onClick={() => router.get(`/workspaces/${workspace.slug}/roles`)}
                        className="flex h-8 items-center gap-2 rounded-lg border border-black/8 bg-white px-3.5 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300"
                    >
                        <ArrowLeft className="h-3.5 w-3.5" />
                        Back to Roles
                    </button>
                </PageHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {groups.map((group) => {
                        const allChecked = group.permissions.every((p) =>
                            data.permission_ids.includes(p.id)
                        );
                        const someChecked = group.permissions.some((p) =>
                            data.permission_ids.includes(p.id)
                        );

                        return (
                            <div
                                key={group.category}
                                className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900"
                            >
                                {/* Category header */}
                                <div className="flex items-center justify-between border-b border-black/6 px-5 py-3 dark:border-white/6">
                                    <div className="flex items-center gap-2">
                                        <ShieldCheck className="h-3.5 w-3.5 text-emerald-500" />
                                        <span className="font-mono text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                            {group.category}
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => toggleAll(group.permissions)}
                                        className="font-mono text-[11px] text-emerald-600 hover:underline dark:text-emerald-400"
                                    >
                                        {allChecked ? 'Deselect all' : 'Select all'}
                                    </button>
                                </div>

                                {/* Permissions grid */}
                                <div className="grid grid-cols-1 gap-px bg-black/4 dark:bg-white/4 sm:grid-cols-2 lg:grid-cols-3">
                                    {group.permissions.map((permission) => {
                                        const checked = data.permission_ids.includes(permission.id);
                                        return (
                                            <label
                                                key={permission.id}
                                                className="flex cursor-pointer items-center gap-3 bg-white px-5 py-3.5 transition-colors hover:bg-stone-50 dark:bg-zinc-900 dark:hover:bg-zinc-800"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={checked}
                                                    onChange={() => toggle(permission.id)}
                                                    className="h-4 w-4 rounded border-gray-300 accent-emerald-600"
                                                />
                                                <span className="font-mono text-[12px] text-gray-700 dark:text-gray-300">
                                                    {permission.name}
                                                </span>
                                            </label>
                                        );
                                    })}
                                </div>
                            </div>
                        );
                    })}

                    <div className="flex justify-end pt-2">
                        <button
                            type="submit"
                            disabled={processing}
                            className="flex h-9 items-center rounded-lg bg-emerald-600 px-5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                        >
                            {processing ? 'Saving…' : 'Save Permissions'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
