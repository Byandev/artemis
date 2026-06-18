import { Can } from '@/components/can';
import PageHeader from '@/components/common/PageHeader';
import { PERMISSIONS } from '@/constants/permissions';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Check, Loader2, Save, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

interface PageItem {
    id: number;
    name: string;
}

interface Props {
    workspace: Workspace;
    team: { id: number; name: string };
    pages: PageItem[];
    assignedPageIds: number[];
}

const BACK_BTN =
    'flex h-8 items-center gap-1.5 rounded-lg border border-black/8 bg-stone-100 px-3 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-200 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700';
const SAVE_BTN =
    'flex h-8 items-center gap-1.5 rounded-lg bg-brand-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-brand-700 disabled:opacity-40';

export default function TeamPages({
    workspace,
    team,
    pages,
    assignedPageIds,
}: Props) {
    const [selected, setSelected] = useState<Set<number>>(
        new Set(assignedPageIds),
    );
    const [search, setSearch] = useState('');
    const [saving, setSaving] = useState(false);

    const filtered = useMemo(
        () =>
            pages.filter((p) =>
                p.name.toLowerCase().includes(search.toLowerCase().trim()),
            ),
        [pages, search],
    );

    const hasChanges = useMemo(() => {
        if (selected.size !== assignedPageIds.length) return true;
        return assignedPageIds.some((id) => !selected.has(id));
    }, [selected, assignedPageIds]);

    const toggle = (id: number) => {
        setSelected((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    };

    const save = () => {
        setSaving(true);
        router.put(
            `/workspaces/${workspace.slug}/teams/${team.id}/pages`,
            { page_ids: Array.from(selected) },
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Team pages updated'),
                onError: () => toast.error('Failed to save'),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <AppLayout>
            <Head title={`${team.name} Pages — ${workspace.name}`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title={`${team.name} — Pages`}
                    description="Choose which pages this team owns. Members see orders, budgets and metrics only for these pages."
                    stackActionsOnMobile
                >
                    <Link
                        href={`/workspaces/${workspace.slug}/teams`}
                        className={BACK_BTN}
                    >
                        <ArrowLeft className="h-3.5 w-3.5" />
                        Back
                    </Link>
                    <Can permission={PERMISSIONS.EditTeams}>
                        <button
                            onClick={save}
                            disabled={saving || !hasChanges}
                            className={SAVE_BTN}
                        >
                            {saving ? (
                                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                            ) : (
                                <Save className="h-3.5 w-3.5" />
                            )}
                            Save
                        </button>
                    </Can>
                </PageHeader>

                {/* Search */}
                <div className="mb-3 flex items-center gap-2 rounded-lg border border-black/8 bg-white px-3 dark:border-white/8 dark:bg-zinc-900">
                    <Search className="h-4 w-4 text-gray-400" />
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search pages…"
                        className="h-9 w-full bg-transparent text-[13px] text-gray-700 outline-none placeholder:text-gray-400 dark:text-gray-200"
                    />
                </div>

                {/* List */}
                {filtered.length === 0 ? (
                    <div className="rounded-[14px] border border-black/6 bg-white py-16 text-center text-sm text-gray-400 dark:border-white/6 dark:bg-zinc-900">
                        No pages found.
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        {filtered.map((page) => {
                            const isSelected = selected.has(page.id);
                            return (
                                <button
                                    key={page.id}
                                    type="button"
                                    onClick={() => toggle(page.id)}
                                    className={[
                                        'flex items-center gap-3 rounded-xl border px-4 py-3 text-left transition-all',
                                        isSelected
                                            ? 'border-brand-300 bg-brand-50/50 dark:border-brand-500/30 dark:bg-brand-500/5'
                                            : 'border-black/6 bg-white hover:border-black/12 hover:bg-stone-50 dark:border-white/6 dark:bg-zinc-900 dark:hover:bg-zinc-800/50',
                                    ].join(' ')}
                                >
                                    <span
                                        className={[
                                            'flex h-5 w-5 shrink-0 items-center justify-center rounded-md border transition-all',
                                            isSelected
                                                ? 'border-brand-600 bg-brand-600 text-white'
                                                : 'border-black/15 bg-white dark:border-white/15 dark:bg-zinc-800',
                                        ].join(' ')}
                                    >
                                        {isSelected && (
                                            <Check className="h-3.5 w-3.5" />
                                        )}
                                    </span>
                                    <span className="truncate text-[13px] font-medium text-gray-800 dark:text-gray-200">
                                        {page.name}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                )}

                <p className="mt-3 px-1 font-mono text-[11px] text-gray-400">
                    {selected.size} of {pages.length} page
                    {pages.length === 1 ? '' : 's'} selected
                </p>
            </div>
        </AppLayout>
    );
}
