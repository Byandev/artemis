import { Can } from '@/components/can';
import { ChecklistProofDialog } from '@/components/checklist/checklist-proof-dialog';
import {
    ChecklistProgressItem,
    formatFileSize,
} from '@/components/checklist/types';
import { UncheckProofDialog } from '@/components/checklist/uncheck-proof-dialog';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { PERMISSIONS } from '@/constants/permissions';
import { cn } from '@/lib/utils';
import { Workspace } from '@/types/models/Workspace';
import axios from 'axios';
import { formatDistanceToNow } from 'date-fns';
import {
    CheckCircle2,
    Circle,
    ClipboardList,
    ExternalLink,
    FileText,
    LoaderCircle,
    ShieldAlert,
    TriangleAlert,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

type TargetChecklistDrawerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    workspace: Workspace;
    target: 'shop' | 'page';
    targetId: number | null;
    targetName: string;
};

/**
 * The proof thumbnail. The URL is a redirect to a short-lived signed URL, which
 * an <img> follows fine — except on a disk that streams the bytes as an
 * attachment instead, so a failed load falls back to a file tile.
 */
function ProofThumbnail({ item }: { item: ChecklistProgressItem }) {
    const [broken, setBroken] = useState(false);
    const proof = item.proof;

    useEffect(() => {
        setBroken(false);
    }, [proof?.url]);

    if (!proof) {
        return null;
    }

    const isImage = proof.mime_type.startsWith('image/') && !broken;

    return (
        <a
            href={proof.url}
            target="_blank"
            rel="noreferrer"
            title={`${proof.file_name} · ${formatFileSize(proof.size)}`}
            className="group/proof mt-2 flex max-w-full items-center gap-2.5 rounded-lg border border-black/8 bg-white p-1.5 transition-colors hover:border-emerald-500 dark:border-white/8 dark:bg-zinc-900 dark:hover:border-emerald-400"
        >
            {isImage ? (
                <img
                    src={proof.url}
                    alt=""
                    loading="lazy"
                    onError={() => setBroken(true)}
                    className="h-10 w-10 shrink-0 rounded-md border border-black/8 object-cover dark:border-white/10"
                />
            ) : (
                <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-black/8 bg-stone-100 text-gray-400 dark:border-white/10 dark:bg-zinc-800 dark:text-gray-500">
                    <FileText className="h-4 w-4" />
                </span>
            )}
            <span className="min-w-0 flex-1">
                <span className="block truncate font-mono text-[11px] text-gray-700 dark:text-gray-200">
                    {proof.file_name}
                </span>
                <span className="block font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                    {formatFileSize(proof.size)}
                </span>
            </span>
            <ExternalLink className="mr-1 h-3.5 w-3.5 shrink-0 text-gray-300 transition-colors group-hover/proof:text-emerald-600 dark:text-gray-600 dark:group-hover/proof:text-emerald-400" />
        </a>
    );
}

export function TargetChecklistDrawer({
    open,
    onOpenChange,
    workspace,
    target,
    targetId,
    targetName,
}: TargetChecklistDrawerProps) {
    const [items, setItems] = useState<ChecklistProgressItem[]>([]);
    const [loading, setLoading] = useState(false);
    const [savingId, setSavingId] = useState<number | null>(null);
    const [proofItem, setProofItem] = useState<ChecklistProgressItem | null>(
        null,
    );
    const [uploadingProof, setUploadingProof] = useState(false);
    const [uncheckItem, setUncheckItem] =
        useState<ChecklistProgressItem | null>(null);

    const progressUrl = `/workspaces/${workspace.slug}/checklist/progress/${target}/${targetId}`;

    const progress = useMemo(() => {
        const completed = items.filter((item) => item.is_completed).length;
        const total = items.length;
        const percent = total > 0 ? Math.round((completed / total) * 100) : 0;
        const requiredPending = items.filter(
            (item) => item.required && !item.is_completed,
        ).length;

        return { completed, total, percent, requiredPending };
    }, [items]);

    const fetchItems = useCallback(async () => {
        const res = await axios.get(
            `/workspaces/${workspace.slug}/checklist/progress/${target}/${targetId}`,
        );

        return (res.data.items ?? []) as ChecklistProgressItem[];
    }, [workspace.slug, target, targetId]);

    useEffect(() => {
        if (!open || !targetId) {
            return;
        }

        let active = true;

        const load = async () => {
            setLoading(true);

            try {
                const loaded = await fetchItems();
                if (active) {
                    setItems(loaded);
                }
            } catch (error) {
                if (active) {
                    const message = axios.isAxiosError(error)
                        ? error.response?.data?.message
                        : null;

                    toast.error(
                        message || 'Unable to load checklist progress.',
                    );
                    setItems([]);
                }
            } finally {
                if (active) {
                    setLoading(false);
                }
            }
        };

        load();

        return () => {
            active = false;
        };
    }, [open, targetId, fetchItems]);

    const handleToggle = (item: ChecklistProgressItem, checked: boolean) => {
        if (!targetId || savingId !== null) {
            return;
        }

        // Items that demand evidence are never ticked off straight from the
        // checkbox — the upload dialog does it once a file is attached.
        if (checked && item.requires_proof) {
            setProofItem(item);
            return;
        }

        // Unchecking destroys the stored proof, so it goes through a
        // confirmation dialog rather than happening on a stray click.
        if (!checked && item.proof) {
            setUncheckItem(item);
            return;
        }

        applyToggle(item, checked);
    };

    const applyToggle = async (
        item: ChecklistProgressItem,
        checked: boolean,
    ) => {
        const previousItems = items;
        const optimisticTime = new Date().toISOString();

        setSavingId(item.id);
        setItems((current) =>
            current.map((currentItem) => {
                if (currentItem.id !== item.id) {
                    return currentItem;
                }

                if (checked) {
                    return {
                        ...currentItem,
                        is_completed: true,
                        checked_by_name: 'You',
                        checked_at: optimisticTime,
                    };
                }

                return {
                    ...currentItem,
                    is_completed: false,
                    checked_by_name: undefined,
                    checked_at: undefined,
                    note: null,
                    proof: null,
                };
            }),
        );

        try {
            if (checked) {
                await axios.post(progressUrl, {
                    checklist_id: item.id,
                });
            } else {
                await axios.delete(progressUrl, {
                    data: {
                        checklist_id: item.id,
                    },
                });
            }
        } catch {
            setItems(previousItems);
            toast.error('Unable to update checklist progress.');
        } finally {
            setSavingId(null);
        }
    };

    const handleProofSubmit = async (file: File, note: string) => {
        if (!proofItem || !targetId || uploadingProof) {
            return;
        }

        const replacing = Boolean(proofItem.proof);
        const payload = new FormData();
        payload.append('checklist_id', String(proofItem.id));
        payload.append('proof', file);
        if (note) {
            payload.append('note', note);
        }

        setUploadingProof(true);

        try {
            await axios.post(progressUrl, payload);

            // Refetched rather than patched in place: the proof URL is minted
            // server-side from the completion that was just created.
            setItems(await fetchItems());
            setProofItem(null);
            toast.success(
                replacing
                    ? 'Proof replaced.'
                    : 'Checklist item marked complete.',
            );
        } catch (error) {
            const message = axios.isAxiosError(error)
                ? (error.response?.data?.errors?.proof?.[0] ??
                  error.response?.data?.message)
                : null;

            toast.error(message || 'Unable to upload proof of completion.');
        } finally {
            setUploadingProof(false);
        }
    };

    const renderCheckedMeta = (item: ChecklistProgressItem): string | null => {
        if (!item.is_completed) {
            return null;
        }

        const checkedBy = item.checked_by_name ?? 'Unknown';
        if (!item.checked_at) {
            return `Checked by ${checkedBy}`;
        }

        const checkedAt = formatDistanceToNow(new Date(item.checked_at), {
            addSuffix: true,
        });

        return `Checked by ${checkedBy} · ${checkedAt}`;
    };

    const renderItem = (item: ChecklistProgressItem) => {
        const checkedMeta = renderCheckedMeta(item);
        const saving = savingId === item.id;

        return (
            <div
                key={item.id}
                className={cn(
                    'group rounded-lg border p-3 transition-colors',
                    item.is_completed
                        ? 'border-emerald-500/25 bg-emerald-50/40 dark:border-emerald-400/20 dark:bg-emerald-500/5'
                        : 'border-black/6 bg-white hover:border-black/12 dark:border-white/8 dark:bg-zinc-900 dark:hover:border-white/16',
                )}
            >
                <div className="flex items-start gap-3">
                    <div className="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center">
                        {saving ? (
                            <LoaderCircle className="h-4 w-4 animate-spin text-emerald-600 dark:text-emerald-400" />
                        ) : (
                            <Can
                                permission={PERMISSIONS.EditChecklist}
                                fallback={
                                    item.is_completed ? (
                                        <CheckCircle2 className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                    ) : (
                                        <Circle className="h-4 w-4 text-gray-300 dark:text-gray-600" />
                                    )
                                }
                            >
                                <Checkbox
                                    checked={item.is_completed}
                                    disabled={savingId !== null}
                                    onCheckedChange={(next) =>
                                        handleToggle(item, Boolean(next))
                                    }
                                    className="data-[state=checked]:border-emerald-600 data-[state=checked]:bg-emerald-600 dark:data-[state=checked]:border-emerald-500 dark:data-[state=checked]:bg-emerald-500"
                                />
                            </Can>
                        )}
                    </div>

                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <p
                                className={cn(
                                    'font-mono text-[12px] font-medium break-words',
                                    item.is_completed
                                        ? 'text-gray-500 dark:text-gray-400'
                                        : 'text-gray-800 dark:text-gray-100',
                                )}
                            >
                                {item.title}
                            </p>
                            {item.required && !item.is_completed && (
                                <span className="rounded border border-amber-500/30 bg-amber-50 px-1.5 py-px font-mono text-[9px] tracking-wider text-amber-700 uppercase dark:border-amber-400/25 dark:bg-amber-400/10 dark:text-amber-400">
                                    Required
                                </span>
                            )}
                            {item.requires_proof && !item.is_completed && (
                                <span className="inline-flex items-center gap-1 rounded border border-black/8 bg-stone-100 px-1.5 py-px font-mono text-[9px] tracking-wider text-gray-500 uppercase dark:border-white/8 dark:bg-zinc-800 dark:text-gray-400">
                                    <ShieldAlert className="h-2.5 w-2.5" />
                                    Proof
                                </span>
                            )}
                        </div>

                        {checkedMeta ? (
                            <p className="mt-1 font-mono text-[10px] tracking-wider text-emerald-700 uppercase dark:text-emerald-400">
                                {checkedMeta}
                            </p>
                        ) : (
                            <p className="mt-1 font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                Pending
                            </p>
                        )}

                        {item.proof && <ProofThumbnail item={item} />}

                        {item.note && (
                            <p className="mt-2 border-l-2 border-black/8 pl-2 font-mono text-[10px] leading-relaxed break-words text-gray-500 dark:border-white/10 dark:text-gray-400">
                                {item.note}
                            </p>
                        )}

                        {item.is_completed && item.requires_proof && (
                            <Can permission={PERMISSIONS.EditChecklist}>
                                <button
                                    type="button"
                                    onClick={() => setProofItem(item)}
                                    className="mt-2 font-mono text-[10px] tracking-wider text-gray-400 uppercase underline underline-offset-2 transition-colors hover:text-emerald-700 dark:text-gray-500 dark:hover:text-emerald-400"
                                >
                                    {item.proof
                                        ? 'Replace proof'
                                        : 'Attach proof'}
                                </button>
                            </Can>
                        )}
                    </div>
                </div>
            </div>
        );
    };

    return (
        <>
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent className="max-h-[86vh] gap-0 overflow-hidden rounded-xl border border-black/8 p-0 sm:max-w-2xl dark:border-white/8">
                    <DialogHeader className="border-b border-black/6 px-5 py-3 text-left dark:border-white/8">
                        <div className="flex items-start gap-2.5">
                            <span className="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">
                                <ClipboardList className="h-4 w-4" />
                            </span>
                            <div className="min-w-0">
                                <DialogTitle className="truncate font-mono text-[16px] tracking-wide text-gray-800 uppercase dark:text-gray-100">
                                    {targetName
                                        ? `${targetName} Checklist`
                                        : 'Checklist'}
                                </DialogTitle>
                                <DialogDescription className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                                    Review and mark completion for{' '}
                                    {target === 'shop' ? 'shop' : 'page'}{' '}
                                    requirements.
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>

                    <div className="border-b border-black/6 px-5 py-3 dark:border-white/8">
                        <div className="mb-2 flex items-center justify-between">
                            <p className="font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                Progress
                            </p>
                            <p className="font-mono text-[10px] tracking-wider text-gray-500 uppercase dark:text-gray-400">
                                {progress.completed}/{progress.total} (
                                {progress.percent}%)
                            </p>
                        </div>
                        <div className="h-2 overflow-hidden rounded-full bg-black/6 dark:bg-white/10">
                            <div
                                className="h-full rounded-full bg-emerald-600 transition-all duration-300 dark:bg-emerald-500"
                                style={{ width: `${progress.percent}%` }}
                            />
                        </div>
                        {progress.total > 0 &&
                            (progress.requiredPending > 0 ? (
                                <p className="mt-2 flex items-center gap-1.5 font-mono text-[10px] tracking-wider text-amber-700 uppercase dark:text-amber-400">
                                    <TriangleAlert className="h-3 w-3 shrink-0" />
                                    {progress.requiredPending} required item
                                    {progress.requiredPending === 1
                                        ? ''
                                        : 's'}{' '}
                                    outstanding
                                </p>
                            ) : (
                                <p className="mt-2 flex items-center gap-1.5 font-mono text-[10px] tracking-wider text-emerald-700 uppercase dark:text-emerald-400">
                                    <CheckCircle2 className="h-3 w-3 shrink-0" />
                                    All required items complete
                                </p>
                            ))}
                    </div>

                    <div className="max-h-[60vh] overflow-y-auto px-5 py-3">
                        {loading ? (
                            <div className="space-y-2">
                                {[0, 1, 2].map((row) => (
                                    <div
                                        key={row}
                                        className="flex items-start gap-3 rounded-lg border border-black/6 bg-white p-3 dark:border-white/8 dark:bg-zinc-900"
                                    >
                                        <div className="mt-0.5 h-4 w-4 shrink-0 animate-pulse rounded bg-black/8 dark:bg-white/10" />
                                        <div className="flex-1 space-y-2">
                                            <div className="h-2.5 w-1/2 animate-pulse rounded bg-black/8 dark:bg-white/10" />
                                            <div className="h-2 w-1/4 animate-pulse rounded bg-black/6 dark:bg-white/8" />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : items.length === 0 ? (
                            <div className="flex flex-col items-center gap-2 py-10 text-center">
                                <ClipboardList className="h-6 w-6 text-gray-300 dark:text-gray-600" />
                                <p className="font-mono text-[12px] text-gray-500 dark:text-gray-400">
                                    No checklist items for this{' '}
                                    {target === 'shop' ? 'shop' : 'page'} yet.
                                </p>
                                <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                    Add them from the Checklist settings page.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {items.map(renderItem)}
                            </div>
                        )}
                    </div>

                    {target === 'page' && !loading && items.length > 0 && (
                        <div className="border-t border-black/6 bg-stone-50 px-5 py-2.5 dark:border-white/8 dark:bg-zinc-800/40">
                            <p className="flex items-center gap-1.5 font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                <ShieldAlert className="h-3 w-3 shrink-0" />
                                Page items need a proof file before they can be
                                completed.
                            </p>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <UncheckProofDialog
                open={uncheckItem !== null}
                onOpenChange={(next) => {
                    if (!next) {
                        setUncheckItem(null);
                    }
                }}
                item={uncheckItem}
                onConfirm={() => {
                    if (uncheckItem) {
                        applyToggle(uncheckItem, false);
                    }
                    setUncheckItem(null);
                }}
            />

            <ChecklistProofDialog
                open={proofItem !== null}
                onOpenChange={(next) => {
                    if (!next && !uploadingProof) {
                        setProofItem(null);
                    }
                }}
                item={proofItem}
                submitting={uploadingProof}
                onSubmit={handleProofSubmit}
            />
        </>
    );
}
