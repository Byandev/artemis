import { inputCls } from '@/components/finance/account-form-dialog';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { router, useForm } from '@inertiajs/react';
import {
    Check,
    ListChecks,
    LucideIcon,
    Paperclip,
    Pencil,
    Plus,
    Trash2,
    X,
} from 'lucide-react';
import React, { useEffect, useState } from 'react';

export interface NamedItem {
    id: number;
    name: string;
}

/** One of the workspace's requirements, and how many types call for it. */
export interface Requirement extends NamedItem {
    transaction_types_count: number;
}

export type RequirementKind = 'attachments' | 'checklists';

export type Requirements = Record<RequirementKind, Requirement[]>;

/** The two kinds of requirement a type can call for. `kind` is also the URL segment. */
export const REQUIREMENT_KINDS: {
    kind: RequirementKind;
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

export interface RequirementPermissions {
    canCreate: boolean;
    canEdit: boolean;
    canDelete: boolean;
}

/** A transaction type with the requirements it calls for. */
export interface TypeWithRequirements extends NamedItem {
    attachments: NamedItem[];
    checklists: NamedItem[];
}

/** Ties a requirement list to one type: each row gets a tick for whether it calls for it. */
interface LinkTarget {
    typeId: number;
    isLinked: (id: number) => boolean;
    /** Whether the type calls for it on the server, ticks aside. */
    isSaved: (id: number) => boolean;
    toggle: (id: number, on: boolean) => void;
}

/**
 * Tick which of the workspace's requirements a transaction type calls for, with
 * the same add / rename / delete the RF Requirements page has. Ticks are held
 * until Save; Close drops them. Add / rename / delete apply straight away, as
 * they change the workspace's requirement, not just this type.
 */
export function ManageRequirementsDialog({
    type,
    requirements,
    financeUrl,
    can,
    onClose,
}: {
    type: TypeWithRequirements | null;
    requirements: Requirements;
    financeUrl: string;
    can: RequirementPermissions;
    onClose: () => void;
}) {
    return (
        <Dialog open={!!type} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="gap-0 overflow-hidden border-none p-0 shadow-2xl sm:max-w-lg dark:bg-zinc-900">
                <div className="border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            {type?.name} Requirements
                        </DialogTitle>
                        <DialogDescription className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                            Tick what a fund request of this type calls for.
                            Each item is shared across types, so a rename or
                            delete applies to every type using it.
                        </DialogDescription>
                    </DialogHeader>
                </div>
                {type && (
                    <ManageRequirementsBody
                        key={type.id}
                        type={type}
                        requirements={requirements}
                        financeUrl={financeUrl}
                        can={can}
                        onClose={onClose}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function ManageRequirementsBody({
    type,
    requirements,
    financeUrl,
    can,
    onClose,
}: {
    type: TypeWithRequirements;
    requirements: Requirements;
    financeUrl: string;
    can: RequirementPermissions;
    onClose: () => void;
}) {
    // Unsaved ticks, over what the type calls for on the server. Kept as
    // overrides so a requirement added here (linked by the server) shows ticked.
    const [ticks, setTicks] = useState<Record<string, boolean>>({});
    const [saving, setSaving] = useState(false);

    const isLinked = (kind: RequirementKind, id: number) =>
        ticks[`${kind}:${id}`] ?? type[kind].some((i) => i.id === id);

    const selected = (kind: RequirementKind) =>
        requirements[kind].filter((i) => isLinked(kind, i.id)).map((i) => i.id);

    const dirty = REQUIREMENT_KINDS.some(({ kind }) =>
        requirements[kind].some(
            (i) =>
                isLinked(kind, i.id) !== type[kind].some((t) => t.id === i.id),
        ),
    );

    const save = () =>
        router.put(
            `${financeUrl}/management/transaction-types/${type.id}`,
            {
                attachments: selected('attachments'),
                checklists: selected('checklists'),
            },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onSuccess: onClose,
                onFinish: () => setSaving(false),
            },
        );

    return (
        <>
            <Tabs defaultValue={REQUIREMENT_KINDS[0].kind} className="gap-0">
                <div className="px-5 pt-4">
                    <TabsList className="w-full">
                        {REQUIREMENT_KINDS.map((k) => (
                            <TabsTrigger
                                key={k.kind}
                                value={k.kind}
                                className="font-mono text-[12px]"
                            >
                                <k.icon className="h-3.5 w-3.5" />
                                {k.title}
                                <span className="text-gray-400">
                                    {selected(k.kind).length}
                                </span>
                            </TabsTrigger>
                        ))}
                    </TabsList>
                </div>
                {REQUIREMENT_KINDS.map((k) => (
                    <TabsContent
                        key={k.kind}
                        value={k.kind}
                        className="max-h-[60vh] min-h-64 overflow-y-auto"
                    >
                        <RequirementList
                            kind={k.kind}
                            items={requirements[k.kind]}
                            financeUrl={financeUrl}
                            can={can}
                            link={{
                                typeId: type.id,
                                isLinked: (id) => isLinked(k.kind, id),
                                isSaved: (id) =>
                                    type[k.kind].some((i) => i.id === id),
                                toggle: (id, on) =>
                                    setTicks((t) => ({
                                        ...t,
                                        [`${k.kind}:${id}`]: on,
                                    })),
                            }}
                        />
                    </TabsContent>
                ))}
            </Tabs>
            <div className="flex justify-end gap-2 border-t border-black/6 px-5 py-3 dark:border-white/6">
                <button
                    type="button"
                    onClick={onClose}
                    disabled={saving}
                    className="h-9 rounded-lg border border-black/8 bg-white px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                >
                    Close
                </button>
                {can.canEdit && (
                    <button
                        type="button"
                        onClick={save}
                        disabled={saving || !dirty}
                        className="h-9 rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                    >
                        {saving ? 'Saving...' : 'Save'}
                    </button>
                )}
            </div>
        </>
    );
}

/**
 * One kind of requirement in a type's dialog: an add box on top (a new one is
 * linked to the type), then every one of the workspace's requirements with a
 * tick for whether the type calls for it, and rename / delete.
 */
function RequirementList({
    kind,
    items,
    financeUrl,
    can,
    link,
}: {
    kind: RequirementKind;
    items: Requirement[];
    financeUrl: string;
    can: RequirementPermissions;
    link: LinkTarget;
}) {
    const meta = REQUIREMENT_KINDS.find((k) => k.kind === kind)!;
    const url = `${financeUrl}/management/${kind}`;
    const add = useForm({ name: '', transaction_type_id: link.typeId });

    const handleAdd = (e: React.FormEvent) => {
        e.preventDefault();
        add.post(url, {
            preserveScroll: true,
            onSuccess: () => add.reset('name'),
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
                            placeholder={meta.placeholder}
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
                    <p className="py-6 text-center text-[12px] text-gray-400">
                        No {meta.title.toLowerCase()} yet.
                    </p>
                )}
                {items.map((i) => (
                    <RequirementRow
                        key={i.id}
                        item={i}
                        icon={meta.icon}
                        url={`${url}/${i.id}`}
                        can={can}
                        linked={link.isLinked(i.id)}
                        // Other types calling for it, which a rename or
                        // delete would also touch.
                        others={
                            i.transaction_types_count -
                            (link.isSaved(i.id) ? 1 : 0)
                        }
                        onToggle={(on) => link.toggle(i.id, on)}
                    />
                ))}
            </div>
        </section>
    );
}

function RequirementRow({
    item,
    icon: Icon,
    url,
    can,
    linked,
    others,
    onToggle,
}: {
    item: Requirement;
    icon: LucideIcon;
    url: string;
    can: RequirementPermissions;
    linked: boolean;
    others: number;
    onToggle: (on: boolean) => void;
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
            <Checkbox
                checked={linked}
                disabled={!can.canEdit}
                onCheckedChange={(v) => onToggle(v === true)}
                aria-label={`${linked ? 'Remove' : 'Add'} ${item.name}`}
            />
            <Icon className="h-3.5 w-3.5 shrink-0 text-gray-400" />
            <span className="min-w-0 flex-1 text-[13px] text-gray-700 dark:text-gray-200">
                <span className="break-words">{item.name}</span>
                {others > 0 && (
                    <span className="ml-1.5 font-mono text-[11px] text-gray-400">
                        +{others} other {others === 1 ? 'type' : 'types'}
                    </span>
                )}
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
