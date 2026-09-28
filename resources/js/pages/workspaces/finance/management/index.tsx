import PageHeader from '@/components/common/PageHeader';
import { inputCls } from '@/components/finance/account-form-dialog';
import { transactionTypeStyle } from '@/components/finance/transaction-type';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Check,
    ListChecks,
    LucideIcon,
    Paperclip,
    Pencil,
    Plus,
    Settings2,
    Tags,
    Trash2,
    X,
} from 'lucide-react';
import React, { useEffect, useState } from 'react';

interface NamedItem {
    id: number;
    name: string;
}

interface ManagedType {
    id: number;
    name: string;
    nature: 'debit' | 'credit';
    attachments: NamedItem[];
    checklists: NamedItem[];
}

type Kind = 'attachments' | 'checklists';

/** The two lists each transaction type owns. `kind` is also the URL segment. */
const KINDS: {
    kind: Kind;
    title: string;
    icon: LucideIcon;
    placeholder: string;
}[] = [
    {
        kind: 'attachments',
        title: 'Attachments',
        icon: Paperclip,
        placeholder: 'e.g. Bank Statement',
    },
    {
        kind: 'checklists',
        title: 'Checklist',
        icon: ListChecks,
        placeholder: 'e.g. Budget approved',
    },
];

interface Permissions {
    canCreate: boolean;
    canEdit: boolean;
    canDelete: boolean;
}

interface Props {
    workspace: Workspace;
    types: ManagedType[];
}

export default function FinanceManagementIndex({ workspace, types }: Props) {
    const [managingId, setManagingId] = useState<number | null>(null);
    const can: Permissions = {
        canCreate: usePermission(PERMISSIONS.CreateFinanceTransactions),
        canEdit: usePermission(PERMISSIONS.EditFinanceTransactions),
        canDelete: usePermission(PERMISSIONS.DeleteFinanceTransactions),
    };
    const canManage = can.canCreate || can.canEdit || can.canDelete;
    const base = `/workspaces/${workspace.slug}/finance`;

    // Read the type from props on every render so the dialog shows the lists as
    // the server returns them after each add / rename / delete.
    const managing = types.find((t) => t.id === managingId) ?? null;

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Finance Management`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Management"
                    description="Each transaction type's attachments and checklist."
                >
                    <Link
                        href={`${base}/transaction-types`}
                        className="flex h-8 items-center gap-1.5 rounded-lg border border-black/8 bg-white px-3.5 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                    >
                        <Tags className="h-3.5 w-3.5" />
                        Transaction Types
                    </Link>
                </PageHeader>

                <div className="overflow-x-auto rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <table className="w-full table-fixed text-left">
                        {/* The three data columns split the width evenly;
                            only the Manage column is fixed. */}
                        <colgroup>
                            <col />
                            {KINDS.map((k) => (
                                <col key={k.kind} />
                            ))}
                            {canManage && <col className="w-28" />}
                        </colgroup>
                        <thead>
                            <tr className="border-b border-black/6 font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:border-white/6">
                                <th className="px-4 py-3 font-normal">
                                    Transaction Type
                                </th>
                                {KINDS.map((k) => (
                                    <th
                                        key={k.kind}
                                        className="px-4 py-3 font-normal"
                                    >
                                        {k.title}
                                    </th>
                                ))}
                                {canManage && <th className="w-28 px-4 py-3" />}
                            </tr>
                        </thead>
                        <tbody>
                            {types.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="px-4 py-10 text-center text-[13px] text-gray-400"
                                    >
                                        No transaction types yet.{' '}
                                        <Link
                                            href={`${base}/transaction-types`}
                                            className="text-emerald-600 hover:underline"
                                        >
                                            Add one
                                        </Link>
                                        .
                                    </td>
                                </tr>
                            )}
                            {types.map((t) => {
                                const s = transactionTypeStyle(t.name);
                                return (
                                    <tr
                                        key={t.id}
                                        className="border-b border-black/4 align-top last:border-0 dark:border-white/4"
                                    >
                                        <td className="px-4 py-3">
                                            <span
                                                className={`inline-flex max-w-full items-center rounded-full px-2.5 py-0.5 font-mono text-[11px] break-words uppercase ${s.cls}`}
                                            >
                                                {t.name}
                                            </span>
                                        </td>
                                        {KINDS.map((k) => (
                                            <td
                                                key={k.kind}
                                                className="px-4 py-3"
                                            >
                                                <Chips
                                                    items={t[k.kind]}
                                                    icon={k.icon}
                                                />
                                            </td>
                                        ))}
                                        {canManage && (
                                            <td className="px-4 py-3 text-right">
                                                <button
                                                    onClick={() =>
                                                        setManagingId(t.id)
                                                    }
                                                    className="inline-flex h-7 items-center gap-1.5 rounded-lg border border-black/6 bg-stone-50 px-2.5 font-mono text-[11px] text-gray-500 transition-all hover:border-black/12 hover:bg-stone-100 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-400"
                                                >
                                                    <Settings2 className="h-3 w-3" />
                                                    Manage
                                                </button>
                                            </td>
                                        )}
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {canManage && (
                    <Dialog
                        open={!!managing}
                        onOpenChange={(o) => !o && setManagingId(null)}
                    >
                        <DialogContent className="gap-0 overflow-hidden border-none p-0 shadow-2xl sm:max-w-lg dark:bg-zinc-900">
                            <div className="border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
                                <DialogHeader>
                                    <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                                        {managing?.name}
                                    </DialogTitle>
                                    <DialogDescription className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                                        The attachments and checklist a fund
                                        request of this type calls for.
                                    </DialogDescription>
                                </DialogHeader>
                            </div>
                            {managing && (
                                <Tabs
                                    key={managing.id}
                                    defaultValue={KINDS[0].kind}
                                    className="gap-0"
                                >
                                    <div className="px-5 pt-4">
                                        <TabsList className="w-full">
                                            {KINDS.map((k) => (
                                                <TabsTrigger
                                                    key={k.kind}
                                                    value={k.kind}
                                                    className="font-mono text-[12px]"
                                                >
                                                    <k.icon className="h-3.5 w-3.5" />
                                                    {k.title}
                                                    <span className="text-gray-400">
                                                        {
                                                            managing[k.kind]
                                                                .length
                                                        }
                                                    </span>
                                                </TabsTrigger>
                                            ))}
                                        </TabsList>
                                    </div>
                                    {KINDS.map((k) => (
                                        <TabsContent
                                            key={k.kind}
                                            value={k.kind}
                                            className="max-h-[60vh] min-h-64 overflow-y-auto"
                                        >
                                            <ItemSection
                                                icon={k.icon}
                                                placeholder={k.placeholder}
                                                items={managing[k.kind]}
                                                url={`${base}/management/transaction-types/${managing.id}/${k.kind}`}
                                                can={can}
                                            />
                                        </TabsContent>
                                    ))}
                                </Tabs>
                            )}
                        </DialogContent>
                    </Dialog>
                )}
            </div>
        </AppLayout>
    );
}

function Chips({
    items,
    icon: Icon,
}: {
    items: NamedItem[];
    icon: LucideIcon;
}) {
    if (items.length === 0) {
        return (
            <span className="text-[12px] text-gray-300 dark:text-gray-600">
                None
            </span>
        );
    }

    return (
        <div className="flex flex-wrap gap-1.5">
            {items.map((i) => (
                <span
                    key={i.id}
                    className="inline-flex items-center gap-1 rounded-md bg-stone-100 px-2 py-0.5 text-[12px] text-gray-600 dark:bg-zinc-800 dark:text-gray-300"
                >
                    <Icon className="h-3 w-3 text-gray-400" />
                    {i.name}
                </span>
            ))}
        </div>
    );
}

/** One of a type's lists (a tab): an add box on top, then each row with rename / delete. */
function ItemSection({
    icon: Icon,
    placeholder,
    items,
    url,
    can,
}: {
    icon: LucideIcon;
    placeholder: string;
    items: NamedItem[];
    url: string;
    can: Permissions;
}) {
    const add = useForm({ name: '' });

    const handleAdd = (e: React.FormEvent) => {
        e.preventDefault();
        add.post(url, {
            preserveScroll: true,
            onSuccess: () => add.reset(),
        });
    };

    return (
        <section className="space-y-3 px-5 py-4">
            {can.canCreate && (
                <form onSubmit={handleAdd}>
                    <div className="flex gap-2">
                        <input
                            type="text"
                            value={add.data.name}
                            onChange={(e) =>
                                add.setData('name', e.target.value)
                            }
                            placeholder={placeholder}
                            className={inputCls}
                        />
                        <button
                            type="submit"
                            disabled={add.processing || !add.data.name.trim()}
                            className="flex h-9 shrink-0 items-center gap-1 rounded-lg bg-emerald-600 px-3 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                        >
                            <Plus className="h-3.5 w-3.5" />
                            Add
                        </button>
                    </div>
                    {add.errors.name && (
                        <p className="mt-1 text-[11px] text-red-500">
                            {add.errors.name}
                        </p>
                    )}
                </form>
            )}

            <div className="space-y-1">
                {items.length === 0 && (
                    <p className="py-2 text-center text-[12px] text-gray-400">
                        None yet.
                    </p>
                )}
                {items.map((i) => (
                    <ItemRow
                        key={i.id}
                        item={i}
                        icon={Icon}
                        url={`${url}/${i.id}`}
                        can={can}
                    />
                ))}
            </div>
        </section>
    );
}

function ItemRow({
    item,
    icon: Icon,
    url,
    can,
}: {
    item: NamedItem;
    icon: LucideIcon;
    url: string;
    can: Permissions;
}) {
    const [editing, setEditing] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const form = useForm({ name: item.name });

    // Pick up a rename coming back from the server.
    useEffect(() => {
        form.setData('name', item.name);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [item.name]);

    const cancel = () => {
        form.setData('name', item.name);
        form.clearErrors();
        setEditing(false);
    };

    const save = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(url, {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    if (editing) {
        return (
            <form onSubmit={save} className="py-1">
                <div className="flex items-center gap-1.5">
                    <input
                        type="text"
                        autoFocus
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        onKeyDown={(e) => e.key === 'Escape' && cancel()}
                        className={inputCls}
                    />
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/40"
                        aria-label="Save"
                    >
                        <Check className="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        onClick={cancel}
                        className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-gray-400 hover:bg-stone-100 dark:hover:bg-zinc-800"
                        aria-label="Cancel"
                    >
                        <X className="h-4 w-4" />
                    </button>
                </div>
                {form.errors.name && (
                    <p className="mt-1 text-[11px] text-red-500">
                        {form.errors.name}
                    </p>
                )}
            </form>
        );
    }

    return (
        <div className="group flex items-center gap-2 rounded-lg px-2 py-2 hover:bg-stone-50 dark:hover:bg-zinc-800">
            <Icon className="h-3.5 w-3.5 shrink-0 text-gray-400" />
            <span className="min-w-0 flex-1 truncate text-[13px] text-gray-700 dark:text-gray-200">
                {item.name}
            </span>
            {confirming ? (
                <span className="flex items-center gap-1">
                    <button
                        onClick={() =>
                            router.delete(url, { preserveScroll: true })
                        }
                        className="rounded-md bg-red-600 px-2 py-1 font-mono text-[11px] text-white hover:bg-red-700"
                    >
                        Delete
                    </button>
                    <button
                        onClick={() => setConfirming(false)}
                        className="rounded-md px-2 py-1 font-mono text-[11px] text-gray-500 hover:bg-stone-100 dark:hover:bg-zinc-700"
                    >
                        Cancel
                    </button>
                </span>
            ) : (
                <span className="flex items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                    {can.canEdit && (
                        <button
                            onClick={() => setEditing(true)}
                            className="flex h-7 w-7 items-center justify-center rounded-md text-gray-400 hover:bg-stone-100 hover:text-gray-600 dark:hover:bg-zinc-700"
                            aria-label={`Rename ${item.name}`}
                        >
                            <Pencil className="h-3.5 w-3.5" />
                        </button>
                    )}
                    {can.canDelete && (
                        <button
                            onClick={() => setConfirming(true)}
                            className="flex h-7 w-7 items-center justify-center rounded-md text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40"
                            aria-label={`Delete ${item.name}`}
                        >
                            <Trash2 className="h-3.5 w-3.5" />
                        </button>
                    )}
                </span>
            )}
        </div>
    );
}
