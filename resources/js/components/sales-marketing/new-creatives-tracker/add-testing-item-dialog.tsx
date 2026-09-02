import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useDebouncedState } from '@/hooks/use-debounced-state';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { Loader2, Search, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

export type ItemType = 'campaign' | 'ad_set';

interface AvailableItem {
    id: string;
    name: string;
    account_name: string | null;
}

interface Props {
    workspaceSlug: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

/** A pick, keyed by type so a campaign and an ad set can share an id safely. */
interface Selection {
    item_type: ItemType;
    item_id: string;
    name: string;
}

const TABS: { value: ItemType; label: string }[] = [
    { value: 'campaign', label: 'Campaigns' },
    { value: 'ad_set', label: 'Ad Sets' },
];

const keyOf = (type: ItemType, id: string) => `${type}:${id}`;

export default function AddTestingItemDialog({
    workspaceSlug,
    open,
    onOpenChange,
}: Props) {
    const [type, setType] = useState<ItemType>('campaign');
    const {
        value: search,
        setValue: setSearch,
        debounced,
    } = useDebouncedState('');

    const [items, setItems] = useState<AvailableItem[]>([]);
    const [truncated, setTruncated] = useState(false);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);

    // Picks survive switching tabs and retyping the search, so you can gather a
    // few campaigns and a few ad sets before committing to any of them.
    const [selected, setSelected] = useState<Record<string, Selection>>({});

    const selectedList = useMemo(() => Object.values(selected), [selected]);

    // Fetch on open, and whenever the tab or the settled search term changes.
    useEffect(() => {
        if (!open) return;

        const controller = new AbortController();
        setLoading(true);

        const params = new URLSearchParams({ type });
        if (debounced.trim()) params.set('search', debounced.trim());

        fetch(
            `/workspaces/${workspaceSlug}/sales-marketing/new-creatives-tracker/available-items?${params}`,
            {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            },
        )
            .then((res) => {
                if (!res.ok) throw new Error('Request failed');
                return res.json();
            })
            .then((data) => {
                setItems(data.items ?? []);
                setTruncated(Boolean(data.truncated));
            })
            .catch((err) => {
                // An aborted request is the next keystroke arriving, not a failure.
                if (err.name === 'AbortError') return;
                setItems([]);
                toast.error('Could not load items. Try again.');
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });

        return () => controller.abort();
    }, [open, type, debounced, workspaceSlug]);

    const toggle = (item: AvailableItem) => {
        const key = keyOf(type, item.id);

        setSelected((prev) => {
            const next = { ...prev };
            if (next[key]) {
                delete next[key];
            } else {
                next[key] = {
                    item_type: type,
                    item_id: item.id,
                    name: item.name,
                };
            }
            return next;
        });
    };

    const clearSelection = () => setSelected({});

    const reset = () => {
        clearSelection();
        setSearch('');
        setType('campaign');
        setItems([]);
    };

    const handleOpenChange = (next: boolean) => {
        onOpenChange(next);
        if (!next) reset();
    };

    const submit = () => {
        if (selectedList.length === 0 || saving) return;

        setSaving(true);

        router.post(
            `/workspaces/${workspaceSlug}/sales-marketing/new-creatives-tracker/items`,
            {
                items: selectedList.map(({ item_type, item_id }) => ({
                    item_type,
                    item_id,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(
                        `${selectedList.length} ${selectedList.length === 1 ? 'item' : 'items'} added to the tracker.`,
                    );
                    handleOpenChange(false);
                },
                onError: () => toast.error('Could not add those items.'),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-2xl">
                <div className="border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            Add Testing Item
                        </DialogTitle>
                        <DialogDescription className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                            Pick the active campaigns or ad sets to track. Only
                            things not already in the tracker are listed.
                        </DialogDescription>
                    </DialogHeader>
                </div>

                {/* Type switch + search */}
                <div className="flex flex-col gap-3 border-b border-black/6 px-5 py-3 sm:flex-row sm:items-center dark:border-white/6">
                    <div className="flex shrink-0 rounded-[10px] bg-stone-100 p-0.5 dark:bg-zinc-800">
                        {TABS.map((tab) => (
                            <button
                                key={tab.value}
                                type="button"
                                onClick={() => setType(tab.value)}
                                className={cn(
                                    'rounded-[8px] px-3 py-1.5 text-[12px] font-medium transition-colors',
                                    type === tab.value
                                        ? 'bg-white text-gray-800 shadow-xs dark:bg-zinc-700 dark:text-gray-100'
                                        : 'text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300',
                                )}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>

                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-gray-300 dark:text-gray-600" />
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by name…"
                            className="h-9 w-full rounded-[10px] border border-black/8 bg-stone-50 pr-3 pl-9 text-[13px] text-gray-800 transition-all outline-none placeholder:text-gray-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600"
                        />
                    </div>
                </div>

                {/* Results */}
                <div className="max-h-[45vh] min-h-[220px] overflow-y-auto px-2 py-2">
                    {loading ? (
                        <div className="flex h-[200px] items-center justify-center gap-2 text-[12px] text-gray-400 dark:text-gray-500">
                            <Loader2 className="size-3.5 animate-spin" />
                            Loading…
                        </div>
                    ) : items.length === 0 ? (
                        <div className="flex h-[200px] items-center justify-center px-6 text-center text-[12px] text-gray-400 dark:text-gray-500">
                            {debounced.trim()
                                ? `No active ${type === 'campaign' ? 'campaigns' : 'ad sets'} match “${debounced.trim()}”.`
                                : `No active ${type === 'campaign' ? 'campaigns' : 'ad sets'} left to add.`}
                        </div>
                    ) : (
                        <ul>
                            {items.map((item) => {
                                const checked = Boolean(
                                    selected[keyOf(type, item.id)],
                                );

                                return (
                                    <li key={item.id}>
                                        <label
                                            className={cn(
                                                'flex cursor-pointer items-center gap-3 rounded-[8px] px-3 py-2 transition-colors',
                                                checked
                                                    ? 'bg-emerald-50 dark:bg-emerald-500/10'
                                                    : 'hover:bg-stone-50 dark:hover:bg-zinc-800/60',
                                            )}
                                        >
                                            <Checkbox
                                                checked={checked}
                                                onCheckedChange={() =>
                                                    toggle(item)
                                                }
                                            />
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-[13px] text-gray-800 dark:text-gray-100">
                                                    {item.name}
                                                </span>
                                                {item.account_name && (
                                                    <span className="block truncate text-[11px] text-gray-400 dark:text-gray-500">
                                                        {item.account_name}
                                                    </span>
                                                )}
                                            </span>
                                        </label>
                                    </li>
                                );
                            })}
                        </ul>
                    )}

                    {truncated && !loading && (
                        <p className="px-3 py-2 text-[11px] text-gray-400 dark:text-gray-500">
                            Showing the first 100 matches — narrow the search to
                            see more.
                        </p>
                    )}
                </div>

                {/* Selection summary + actions */}
                <div className="flex items-center justify-between gap-3 border-t border-black/6 px-5 py-3 dark:border-white/6">
                    <div className="min-w-0 text-[12px] text-gray-500 dark:text-gray-400">
                        {selectedList.length === 0 ? (
                            <span className="text-gray-400 dark:text-gray-500">
                                Nothing selected
                            </span>
                        ) : (
                            <button
                                type="button"
                                onClick={clearSelection}
                                className="inline-flex items-center gap-1 text-emerald-600 hover:underline dark:text-emerald-400"
                            >
                                {selectedList.length} selected
                                <X className="size-3" />
                            </button>
                        )}
                    </div>

                    <div className="flex shrink-0 items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => handleOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            onClick={submit}
                            disabled={selectedList.length === 0 || saving}
                        >
                            {saving && (
                                <Loader2 className="size-3.5 animate-spin" />
                            )}
                            Add
                            {selectedList.length > 0
                                ? ` ${selectedList.length}`
                                : ''}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
