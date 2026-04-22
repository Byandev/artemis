import { ChecklistProgressItem } from '@/components/checklist/types';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Workspace } from '@/types/models/Workspace';
import axios from 'axios';
import { formatDistanceToNow } from 'date-fns';
import { LoaderCircle } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

type TargetChecklistDrawerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    workspace: Workspace;
    target: 'shop' | 'page';
    targetId: number | null;
    targetName: string;
};

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

    const progress = useMemo(() => {
        const completed = items.filter((item) => item.is_completed).length;
        const total = items.length;
        const percent = total > 0 ? Math.round((completed / total) * 100) : 0;

        return { completed, total, percent };
    }, [items]);

    useEffect(() => {
        if (!open || !targetId) {
            return;
        }

        let active = true;

        const load = async () => {
            setLoading(true);

            try {
                const res = await axios.get(`/workspaces/${workspace.slug}/checklist/progress/${target}/${targetId}`);
                if (active) {
                    setItems(res.data.items ?? []);
                }
            } catch {
                if (active) {
                    toast.error('Unable to load checklist progress.');
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
    }, [open, targetId, target, workspace.slug]);

    const handleToggle = async (item: ChecklistProgressItem, checked: boolean) => {
        if (!targetId || savingId !== null) {
            return;
        }

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
                };
            })
        );

        try {
            if (checked) {
                await axios.post(`/workspaces/${workspace.slug}/checklist/progress/${target}/${targetId}`, {
                    checklist_id: item.id,
                });
            } else {
                await axios.delete(`/workspaces/${workspace.slug}/checklist/progress/${target}/${targetId}`, {
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

    const renderCheckedMeta = (item: ChecklistProgressItem): string | null => {
        if (!item.is_completed) {
            return null;
        }

        const checkedBy = item.checked_by_name ?? 'Unknown';
        if (!item.checked_at) {
            return `Checked by ${checkedBy}`;
        }

        const checkedAt = formatDistanceToNow(new Date(item.checked_at), { addSuffix: true });

        return `Checked by ${checkedBy} · ${checkedAt}`;
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[86vh] overflow-hidden gap-0 rounded-xl border border-black/8 p-0 sm:max-w-2xl dark:border-white/8">
                <DialogHeader className="border-b border-black/6 px-5 py-3 text-left dark:border-white/8">
                    <DialogTitle className="font-mono text-[16px] uppercase tracking-wide text-gray-800 dark:text-gray-100">
                        {targetName ? `${targetName} Checklist` : 'Checklist'}
                    </DialogTitle>
                    <DialogDescription className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                        Review and mark completion for {target === 'shop' ? 'shop' : 'page'} requirements.
                    </DialogDescription>
                </DialogHeader>

                <div className="border-b border-black/6 px-5 py-3 dark:border-white/8">
                    <div className="mb-2 flex items-center justify-between">
                        <p className="font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Progress</p>
                        <p className="font-mono text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            {progress.completed}/{progress.total} ({progress.percent}%)
                        </p>
                    </div>
                    <div className="h-2 overflow-hidden rounded-full bg-emerald-100 dark:bg-emerald-500/15">
                        <div
                            className="h-full rounded-full bg-emerald-600 transition-all dark:bg-emerald-500"
                            style={{ width: `${progress.percent}%` }}
                        />
                    </div>
                </div>

                <div className="max-h-[60vh] overflow-y-auto px-5 py-3">
                    {loading ? (
                        <div className="flex items-center justify-center py-8 text-gray-500 dark:text-gray-400">
                            <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />
                            Loading checklist...
                        </div>
                    ) : items.length === 0 ? (
                        <div className="py-8 text-center font-mono text-[12px] text-gray-500 dark:text-gray-400">
                            No checklist items found for this target.
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {items.map((item) => {
                                const checkedMeta = renderCheckedMeta(item);

                                return (
                                    <div
                                        key={item.id}
                                        className="rounded-lg border border-black/6 bg-white p-3 dark:border-white/8 dark:bg-zinc-900"
                                    >
                                        <div className="flex items-start gap-3">
                                            <Checkbox
                                                checked={item.is_completed}
                                                disabled={savingId !== null}
                                                onCheckedChange={(next) => handleToggle(item, Boolean(next))}
                                                className="mt-0.5"
                                            />
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="font-mono text-[12px] font-medium text-gray-800 dark:text-gray-100">
                                                        {item.title}
                                                    </p>
                                                    {item.required && <Badge variant="secondary">Required</Badge>}
                                                </div>
                                                {checkedMeta ? (
                                                    <p className="mt-1 font-mono text-[10px] uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                                                        {checkedMeta}
                                                    </p>
                                                ) : (
                                                    <p className="mt-1 font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                                        Pending
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
