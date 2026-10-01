import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import workspaces from '@/routes/workspaces';
import { Shop } from '@/types/models/Shop';
import { Workspace } from '@/types/models/Workspace';
import clsx from 'clsx';
import { AlertCircle, Loader2, Search, Sparkles, Tags } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

export interface PancakeOrderTag {
    id: number;
    name: string;
    color?: string | null;
    is_system_tag?: boolean;
    groups?: { id: number; name: string }[];
}

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    workspace: Workspace;
    shop: Shop | null;
    /** Creating tags writes to the connected Pancake shop — Edit Shops only. */
    canCreatePresets?: boolean;
};

function csrfFromCookie(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

// Falls back to a neutral dot when Pancake sends no colour, and guards against
// anything that isn't a hex code (the value lands straight in an inline style).
const HEX = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i;
const dotColor = (color?: string | null) =>
    color && HEX.test(color.trim()) ? color.trim() : '#a1a1aa';

export default function OrderTagsModal({
    open,
    onOpenChange,
    workspace,
    shop,
    canCreatePresets = false,
}: Props) {
    const [tags, setTags] = useState<PancakeOrderTag[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [search, setSearch] = useState('');
    const [creating, setCreating] = useState(false);

    const shopId = shop?.id;
    const workspaceSlug = workspace.slug;

    const loadTags = useCallback(
        (signal?: AbortSignal) => {
            if (!shopId) return Promise.resolve();

            setLoading(true);
            setError(null);

            return fetch(
                workspaces.shops.orderTags.url({
                    workspace: workspaceSlug,
                    shop: shopId,
                }),
                {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal,
                },
            )
                .then(async (res) => {
                    const data = (await res.json()) as {
                        tags?: PancakeOrderTag[];
                        message?: string;
                    };

                    if (!res.ok) {
                        throw new Error(
                            data.message ?? 'Could not load order tags.',
                        );
                    }

                    setTags(data.tags ?? []);
                })
                .catch((e: unknown) => {
                    if (signal?.aborted) return;
                    setError(
                        e instanceof Error
                            ? e.message
                            : 'Could not load order tags.',
                    );
                })
                .finally(() => {
                    if (!signal?.aborted) setLoading(false);
                });
        },
        [shopId, workspaceSlug],
    );

    useEffect(() => {
        if (!open || !shopId) return;

        const controller = new AbortController();

        setTags([]);
        setSearch('');
        loadTags(controller.signal);

        return () => controller.abort();
    }, [open, shopId, loadTags]);

    const createPresets = async () => {
        if (!shopId) return;

        setCreating(true);

        try {
            const res = await fetch(
                workspaces.shops.orderTagPresets.url({
                    workspace: workspaceSlug,
                    shop: shopId,
                }),
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-XSRF-TOKEN': csrfFromCookie(),
                    },
                },
            );

            const data = (await res.json()) as {
                created?: string[];
                skipped?: string[];
                failed?: { name: string; message: string }[];
                message?: string;
            };

            if (!res.ok) {
                toast.error(data.message ?? 'Could not create the tags.');
                return;
            }

            const created = data.created?.length ?? 0;
            const skipped = data.skipped?.length ?? 0;
            const failed = data.failed ?? [];

            if (failed.length > 0) {
                toast.error(
                    `${created} created, ${skipped} skipped. Failed: ${failed
                        .map((f) => `${f.name} (${f.message})`)
                        .join(', ')}`,
                );
            } else if (created === 0) {
                toast.info('All preset tags already exist on this shop.');
            } else {
                toast.success(
                    `${created} tag${created === 1 ? '' : 's'} created${
                        skipped > 0 ? `, ${skipped} already existed` : ''
                    }.`,
                );
            }

            await loadTags();
        } catch {
            toast.error('Could not reach the server. Try again.');
        } finally {
            setCreating(false);
        }
    };

    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();
        if (!term) return tags;

        return tags.filter(
            (tag) =>
                tag.name?.toLowerCase().includes(term) ||
                tag.groups?.some((group) =>
                    group.name?.toLowerCase().includes(term),
                ),
        );
    }, [tags, search]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Tags className="h-4 w-4" />
                        Order Tags
                    </DialogTitle>
                    <DialogDescription>
                        Order tags configured in Pancake for{' '}
                        <span className="font-medium">{shop?.name}</span>.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-3 py-2">
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                            placeholder="Search tags..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            disabled={loading}
                        />
                    </div>

                    <div className="max-h-[380px] min-h-[120px] overflow-y-auto rounded-[10px] border border-black/6 dark:border-white/6">
                        {loading && (
                            <div className="flex h-[120px] items-center justify-center gap-2 font-mono text-[12px] text-gray-400 dark:text-gray-500">
                                <Loader2 className="h-4 w-4 animate-spin" />
                                Loading tags…
                            </div>
                        )}

                        {!loading && error && (
                            <div className="flex h-[120px] flex-col items-center justify-center gap-2 px-6 text-center">
                                <AlertCircle className="h-4 w-4 text-red-500" />
                                <p className="font-mono text-[12px] text-red-500">
                                    {error}
                                </p>
                            </div>
                        )}

                        {!loading && !error && filtered.length === 0 && (
                            <div className="flex h-[120px] items-center justify-center font-mono text-[12px] text-gray-400 dark:text-gray-500">
                                {tags.length === 0
                                    ? 'No order tags found for this shop.'
                                    : 'No tags match your search.'}
                            </div>
                        )}

                        {!loading && !error && filtered.length > 0 && (
                            <ul className="divide-y divide-black/6 dark:divide-white/6">
                                {filtered.map((tag) => (
                                    <li
                                        key={tag.id}
                                        className="flex items-start gap-3 px-3 py-2.5"
                                    >
                                        <span
                                            className="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full"
                                            style={{
                                                backgroundColor: dotColor(
                                                    tag.color,
                                                ),
                                            }}
                                        />
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="text-[13px] font-medium text-gray-800 dark:text-gray-100">
                                                    {tag.name}
                                                </span>
                                                {tag.is_system_tag && (
                                                    <span className="rounded-full bg-stone-100 px-2 py-0.5 font-mono text-[10px] tracking-wider text-gray-500 uppercase dark:bg-zinc-800 dark:text-gray-400">
                                                        System
                                                    </span>
                                                )}
                                            </div>
                                            {tag.groups &&
                                                tag.groups.length > 0 && (
                                                    <div className="mt-1 flex flex-wrap gap-1.5">
                                                        {tag.groups.map(
                                                            (group) => (
                                                                <span
                                                                    key={
                                                                        group.id
                                                                    }
                                                                    className="rounded-full bg-emerald-50 px-2 py-0.5 font-mono text-[10px] text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400"
                                                                >
                                                                    {group.name}
                                                                </span>
                                                            ),
                                                        )}
                                                    </div>
                                                )}
                                        </div>
                                        <span
                                            className={clsx(
                                                'shrink-0 font-mono text-[10px] text-gray-300 dark:text-gray-600',
                                            )}
                                        >
                                            #{tag.id}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    {!loading && !error && tags.length > 0 && (
                        <p className="font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                            {filtered.length} of {tags.length} tag
                            {tags.length === 1 ? '' : 's'}
                        </p>
                    )}
                </div>

                {canCreatePresets && (
                    <DialogFooter className="sm:justify-between">
                        <p className="font-mono text-[10px] leading-relaxed text-gray-400 dark:text-gray-500">
                            Adds the 7 standard tags. Existing ones are left
                            untouched.
                        </p>
                        <Button
                            type="button"
                            size="sm"
                            onClick={createPresets}
                            disabled={creating || loading}
                        >
                            {creating ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <Sparkles className="h-4 w-4" />
                            )}
                            {creating ? 'Creating…' : 'Create preset tags'}
                        </Button>
                    </DialogFooter>
                )}
            </DialogContent>
        </Dialog>
    );
}
