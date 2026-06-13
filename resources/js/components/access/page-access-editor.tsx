import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { MultiSelect, Option } from '@/components/ui/multi-select';
import { router } from '@inertiajs/react';
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
 * Page-access editor: pick the pages a member/team may see. Empty = full access.
 */
export function PageAccessEditor({
    pages,
    initialPageIds,
    submitUrl,
    backUrl,
}: Props) {
    const options: Option[] = useMemo(
        () => pages.map((p) => ({ value: String(p.id), label: p.name })),
        [pages],
    );

    const [selected, setSelected] = useState<string[]>(
        initialPageIds.map(String),
    );
    const [saving, setSaving] = useState(false);

    const save = () => {
        setSaving(true);
        router.put(
            submitUrl,
            { page_ids: selected.map((v) => Number(v)) },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    return (
        <div className="max-w-2xl space-y-4">
            <div className="space-y-2">
                <Label>Pages</Label>
                <MultiSelect
                    options={options}
                    selected={selected}
                    onChange={setSelected}
                    placeholder="All pages (full access)"
                />
                <p className="text-xs text-zinc-500">
                    Leave empty for full access (no restriction). Everything
                    connected to the selected pages — orders, analytics, ads —
                    follows.
                </p>
            </div>

            <div className="flex items-center gap-2">
                <Button onClick={save} disabled={saving}>
                    {saving ? 'Saving…' : 'Save access'}
                </Button>
                <Button
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
