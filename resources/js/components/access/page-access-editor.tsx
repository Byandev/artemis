import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';

interface PageOption {
    id: number;
    name: string;
}

interface Props {
    pages: PageOption[];
    initialPageIds: number[];
    submitUrl: string;
    backUrl: string;
}

/**
 * Page-access editor: an alphabetical checkbox list of every page. Empty = full
 * access.
 */
export function PageAccessEditor({
    pages,
    initialPageIds,
    submitUrl,
    backUrl,
}: Props) {
    const [selected, setSelected] = useState<Set<number>>(
        () => new Set(initialPageIds),
    );
    const [search, setSearch] = useState('');
    const [saving, setSaving] = useState(false);

    // Pages arrive already sorted by name from the backend.
    const visible = useMemo(() => {
        const q = search.trim().toLowerCase();
        return q
            ? pages.filter((p) => p.name.toLowerCase().includes(q))
            : pages;
    }, [pages, search]);

    const toggle = (id: number) => {
        setSelected((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    const allVisibleSelected =
        visible.length > 0 && visible.every((p) => selected.has(p.id));

    const toggleAllVisible = () => {
        setSelected((prev) => {
            const next = new Set(prev);
            if (allVisibleSelected) {
                visible.forEach((p) => next.delete(p.id));
            } else {
                visible.forEach((p) => next.add(p.id));
            }
            return next;
        });
    };

    const save = () => {
        setSaving(true);
        router.put(
            submitUrl,
            { page_ids: Array.from(selected) },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    return (
        <div className="w-full space-y-4">
            <div className="flex items-center justify-between gap-3">
                <p className="text-xs text-zinc-500">
                    {selected.size === 0
                        ? 'No pages selected — full access (no restriction).'
                        : `${selected.size} of ${pages.length} pages selected.`}
                </p>
                {pages.length > 0 && (
                    <button
                        type="button"
                        onClick={toggleAllVisible}
                        className="text-xs font-medium text-brand-600 hover:underline"
                    >
                        {allVisibleSelected ? 'Clear all' : 'Select all'}
                    </button>
                )}
            </div>

            {pages.length > 8 && (
                <div className="relative">
                    <Search className="absolute top-1/2 left-2.5 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Filter pages…"
                        className="pl-8"
                    />
                </div>
            )}

            <div className="max-h-96 overflow-y-auto rounded-lg border border-zinc-200 p-2 dark:border-zinc-800">
                {visible.length === 0 ? (
                    <div className="px-3 py-6 text-center text-sm text-zinc-500">
                        {pages.length === 0
                            ? 'No pages in this workspace.'
                            : 'No pages match your filter.'}
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-x-6 sm:grid-cols-2 lg:grid-cols-3">
                        {visible.map((p) => (
                            <label
                                key={p.id}
                                className="flex cursor-pointer items-center gap-3 rounded-md px-2 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                            >
                                <Checkbox
                                    checked={selected.has(p.id)}
                                    onCheckedChange={() => toggle(p.id)}
                                />
                                <span className="truncate text-sm text-zinc-900 dark:text-zinc-100">
                                    {p.name}
                                </span>
                            </label>
                        ))}
                    </div>
                )}
            </div>

            <div className="flex items-center gap-2">
                <Button size={'sm'} onClick={save} disabled={saving}>
                    {saving ? 'Saving…' : 'Save access'}
                </Button>
                <Button
                    size={'sm'}
                    variant="outline"
                    onClick={() => router.get(backUrl)}
                    disabled={saving}
                >
                    Cancel
                </Button>
            </div>
        </div>
    );
}
