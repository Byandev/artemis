import PageHeader from '@/components/common/PageHeader';
import {
    Field,
    Footer,
    inputCls,
} from '@/components/finance/account-form-dialog';
import { FinanceDeleteDialog } from '@/components/finance/delete-dialog';
import {
    REQUIREMENT_KINDS,
    Requirement,
    RequirementKind,
    Requirements,
} from '@/components/finance/fund-request-requirements';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, router, useForm } from '@inertiajs/react';
import { TriangleDownIcon, TriangleUpIcon } from '@radix-ui/react-icons';
import { MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-react';
import React, { useEffect, useState } from 'react';

interface Props {
    workspace: Workspace;
    requirements: Requirements;
    query: { sort?: string | null };
}

/**
 * The workspace's attachment and checklist requirements, one tab each. Which
 * types call for which is set from the Transaction Types page ("Manage
 * Requirements").
 */
export default function FinanceManagementIndex({
    workspace,
    requirements,
    query,
}: Props) {
    const canCreate = usePermission(PERMISSIONS.CreateFinanceTransactions);
    const canEdit = usePermission(PERMISSIONS.EditFinanceTransactions);
    const canDelete = usePermission(PERMISSIONS.DeleteFinanceTransactions);
    const showActions = canEdit || canDelete;

    const [tab, setTab] = useState<RequirementKind>(REQUIREMENT_KINDS[0].kind);
    const [createOpen, setCreateOpen] = useState(false);
    const [editing, setEditing] = useState<Requirement | null>(null);
    const [toDelete, setToDelete] = useState<Requirement | null>(null);
    const sortDir = query?.sort === '-name' ? 'desc' : 'asc';
    const toggleSort = () =>
        router.get(
            `/workspaces/${workspace.slug}/finance/management`,
            { sort: sortDir === 'asc' ? '-name' : 'name' },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const current = REQUIREMENT_KINDS.find((k) => k.kind === tab)!;
    const url = `/workspaces/${workspace.slug}/finance/management/${tab}`;

    return (
        <AppLayout>
            <Head title={`${workspace.name} - RF Requirements`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="RF Requirements"
                    description="The attachments and checklist items a fund request can call for."
                >
                    {canCreate && (
                        <button
                            onClick={() => setCreateOpen(true)}
                            className="flex h-8 items-center gap-1 rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                        >
                            <Plus className="h-3.5 w-3.5" />
                            Add{' '}
                            {tab === 'attachments'
                                ? 'Attachment'
                                : 'Checklist Item'}
                        </button>
                    )}
                </PageHeader>

                <Tabs
                    value={tab}
                    onValueChange={(v) => setTab(v as RequirementKind)}
                    className="gap-3"
                >
                    <TabsList>
                        {REQUIREMENT_KINDS.map((k) => (
                            <TabsTrigger
                                key={k.kind}
                                value={k.kind}
                                className="font-mono text-[12px]"
                            >
                                <k.icon className="h-3.5 w-3.5" />
                                {k.title}
                                <span className="text-gray-400">
                                    {requirements[k.kind].length}
                                </span>
                            </TabsTrigger>
                        ))}
                    </TabsList>
                    {REQUIREMENT_KINDS.map((k) => (
                        <TabsContent key={k.kind} value={k.kind}>
                            <div className="overflow-x-auto rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                                <table className="w-full text-left">
                                    <thead>
                                        <tr className="border-b border-black/6 font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:border-white/6">
                                            <th
                                                className="cursor-pointer px-4 py-3 font-normal select-none"
                                                aria-sort={
                                                    sortDir === 'asc'
                                                        ? 'ascending'
                                                        : 'descending'
                                                }
                                                onClick={toggleSort}
                                            >
                                                <span className="flex items-center gap-1">
                                                    Name
                                                    <span className="flex flex-col">
                                                        <TriangleUpIcon
                                                            className={`-mb-1 ${sortDir === 'asc' ? 'text-brand-500' : 'text-gray-300'}`}
                                                        />
                                                        <TriangleDownIcon
                                                            className={`-mt-1 ${sortDir === 'desc' ? 'text-brand-500' : 'text-gray-300'}`}
                                                        />
                                                    </span>
                                                </span>
                                            </th>
                                            {showActions && (
                                                <th className="w-24 px-4 py-3 text-center font-normal">
                                                    Actions
                                                </th>
                                            )}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {requirements[k.kind].length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={2}
                                                    className="px-4 py-10 text-center text-[13px] text-gray-400"
                                                >
                                                    No {k.title.toLowerCase()}{' '}
                                                    yet.
                                                </td>
                                            </tr>
                                        )}
                                        {requirements[k.kind].map((item) => (
                                            <tr
                                                key={item.id}
                                                className="border-b border-black/4 last:border-0 dark:border-white/4"
                                            >
                                                <td className="px-4 py-3">
                                                    <span className="flex items-center gap-2 text-[13px] text-gray-700 dark:text-gray-200">
                                                        <k.icon className="h-3.5 w-3.5 shrink-0 text-gray-400" />
                                                        {item.name}
                                                    </span>
                                                </td>
                                                {showActions && (
                                                    <td className="px-4 py-3">
                                                        <div className="flex justify-center">
                                                            <DropdownMenu>
                                                                <DropdownMenuTrigger
                                                                    asChild
                                                                >
                                                                    <button className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 transition-all hover:border-black/12 hover:bg-stone-100 hover:text-gray-600 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-500">
                                                                        <MoreHorizontal className="h-3.5 w-3.5" />
                                                                    </button>
                                                                </DropdownMenuTrigger>
                                                                <DropdownMenuContent
                                                                    align="end"
                                                                    className="w-36"
                                                                >
                                                                    {canEdit && (
                                                                        <DropdownMenuItem
                                                                            onClick={() =>
                                                                                setEditing(
                                                                                    item,
                                                                                )
                                                                            }
                                                                        >
                                                                            <Pencil className="mr-2 h-3.5 w-3.5" />{' '}
                                                                            Edit
                                                                        </DropdownMenuItem>
                                                                    )}
                                                                    {canEdit &&
                                                                        canDelete && (
                                                                            <DropdownMenuSeparator />
                                                                        )}
                                                                    {canDelete && (
                                                                        <DropdownMenuItem
                                                                            className="text-red-600 focus:text-red-600 dark:text-red-400"
                                                                            onClick={() =>
                                                                                setToDelete(
                                                                                    item,
                                                                                )
                                                                            }
                                                                        >
                                                                            <Trash2 className="mr-2 h-3.5 w-3.5" />{' '}
                                                                            Delete
                                                                        </DropdownMenuItem>
                                                                    )}
                                                                </DropdownMenuContent>
                                                            </DropdownMenu>
                                                        </div>
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </TabsContent>
                    ))}
                </Tabs>

                {(canCreate || canEdit) && (
                    <RequirementFormDialog
                        open={createOpen || editing !== null}
                        onOpenChange={(o) => {
                            if (!o) {
                                setCreateOpen(false);
                                setEditing(null);
                            }
                        }}
                        item={editing}
                        url={url}
                        label={current.title}
                        placeholder={current.placeholder}
                    />
                )}
                {canDelete && (
                    <FinanceDeleteDialog
                        open={!!toDelete}
                        onClose={() => setToDelete(null)}
                        title="Delete Requirement?"
                        description={`Delete "${toDelete?.name}"? It is removed from every transaction type calling for it.`}
                        url={toDelete ? `${url}/${toDelete.id}` : ''}
                        successMessage="Requirement deleted"
                    />
                )}
            </div>
        </AppLayout>
    );
}

function RequirementFormDialog({
    open,
    onOpenChange,
    item,
    url,
    label,
    placeholder,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    item: Requirement | null;
    url: string;
    label: string;
    placeholder: string;
}) {
    const isEditing = !!item;
    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm({ name: '' });

    useEffect(() => {
        if (open) {
            clearErrors();
            setData('name', item?.name ?? '');
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, item]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        };
        if (isEditing) {
            put(`${url}/${item!.id}`, options);
        } else {
            post(url, options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-0 overflow-hidden border-none p-0 shadow-2xl sm:max-w-md dark:bg-zinc-900">
                <div className="border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            {isEditing ? 'Edit' : 'Add'} {label}
                        </DialogTitle>
                        <DialogDescription className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                            {isEditing
                                ? 'A rename applies to every transaction type using it.'
                                : 'Assign it to a type from Transaction Types → Manage Requirements.'}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="space-y-5 px-5 py-4">
                        <Field label="Name" required error={errors.name}>
                            <input
                                type="text"
                                autoFocus
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder={placeholder}
                                className={inputCls}
                            />
                        </Field>
                    </div>

                    <Footer
                        processing={processing}
                        isEditing={isEditing}
                        onCancel={() => onOpenChange(false)}
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}
