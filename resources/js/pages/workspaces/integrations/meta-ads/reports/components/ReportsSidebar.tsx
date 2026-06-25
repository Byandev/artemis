import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { Link, router } from '@inertiajs/react';
import { Archive, ArchiveRestore, Plus, Search, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { defaultConfig, type ReportListItem } from '../types';

/** Create a fresh report directly (no modal / no choices) and open it. */
export function createReport(baseUrl: string) {
    router.post(baseUrl, {
        name: 'Untitled report',
        kind: 'top_performers',
        config: defaultConfig('top_performers', []),
    });
}

interface Props {
    reports: ReportListItem[];
    archivedReports: ReportListItem[];
    /** Currently open report; null on the index landing. */
    activeId?: number | null;
    baseUrl: string;
}

/**
 * Minimal left navigation for the Reports area. "All" lists current reports
 * (hover to archive); "Archive" lists soft-deleted reports that can be restored
 * or permanently deleted. Only the list scrolls.
 */
export function ReportsSidebar({
    reports,
    archivedReports,
    activeId = null,
    baseUrl,
}: Props) {
    const [tab, setTab] = useState<'all' | 'archived'>('all');
    const [search, setSearch] = useState('');
    const [deleteTarget, setDeleteTarget] = useState<ReportListItem | null>(
        null,
    );

    const archive = (r: ReportListItem) =>
        router.delete(`${baseUrl}/${r.id}`, { preserveScroll: true });

    const restore = (r: ReportListItem) =>
        router.post(`${baseUrl}/${r.id}/restore`, {}, { preserveScroll: true });

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(`${baseUrl}/${deleteTarget.id}/force`, {
            preserveScroll: true,
            onFinish: () => setDeleteTarget(null),
        });
    };

    const list = useMemo(() => {
        const source = tab === 'all' ? reports : archivedReports;
        const q = search.trim().toLowerCase();
        if (!q) return source;
        return source.filter((r) => r.name.toLowerCase().includes(q));
    }, [tab, search, reports, archivedReports]);

    const tabClass = (selected: boolean) =>
        `border-b-[1.5px] pb-1.5 text-[11px] font-medium transition-colors ${
            selected
                ? 'border-emerald-500 text-gray-800 dark:text-gray-100'
                : 'border-transparent text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300'
        }`;

    return (
        <aside className="hidden w-52 shrink-0 border-r border-black/6 lg:block dark:border-white/6">
            <div className="flex h-full flex-col">
                {/* Header: label + add */}
                <div className="flex shrink-0 items-center justify-between px-3 pt-4 pb-2">
                    <span className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                        Reports
                    </span>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <button
                                onClick={() => createReport(baseUrl)}
                                className="flex h-6 w-6 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-stone-100 hover:text-emerald-600 dark:hover:bg-zinc-800"
                            >
                                <Plus className="h-4 w-4" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent>New report</TooltipContent>
                    </Tooltip>
                </div>

                {/* Tabs (underline style) */}
                <div className="flex shrink-0 gap-4 border-b border-black/6 px-3 dark:border-white/6">
                    <button
                        onClick={() => setTab('all')}
                        className={tabClass(tab === 'all')}
                    >
                        All {reports.length > 0 && `(${reports.length})`}
                    </button>
                    <button
                        onClick={() => setTab('archived')}
                        className={tabClass(tab === 'archived')}
                    >
                        Archive{' '}
                        {archivedReports.length > 0 &&
                            `(${archivedReports.length})`}
                    </button>
                </div>

                {/* Search */}
                <div className="shrink-0 px-2 pt-2">
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-2 h-3 w-3 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search…"
                            className="h-7 w-full rounded-md bg-transparent pr-2 pl-7 text-[11px] text-gray-700 outline-none placeholder:text-gray-400 focus:bg-stone-50 dark:text-gray-200 dark:placeholder:text-gray-500 dark:focus:bg-zinc-800"
                        />
                    </div>
                </div>

                {/* List (only scrolling region) */}
                <nav className="custom-scrollbar flex-1 overflow-y-auto px-1.5 pt-1 pb-2">
                    {list.length === 0 ? (
                        <p className="px-2 py-5 text-center text-[11px] text-gray-400 dark:text-gray-600">
                            {search.trim()
                                ? 'No matches'
                                : tab === 'all'
                                  ? 'No reports yet'
                                  : 'No archived reports'}
                        </p>
                    ) : (
                        list.map((r) => {
                            const isActive = r.id === activeId;
                            return (
                                <div
                                    key={r.id}
                                    className={`group relative rounded-md transition-colors ${
                                        isActive
                                            ? 'bg-emerald-500/10'
                                            : 'hover:bg-stone-100 dark:hover:bg-zinc-800/70'
                                    }`}
                                >
                                    {tab === 'all' ? (
                                        <Link
                                            href={`${baseUrl}/${r.id}`}
                                            preserveScroll
                                            className={`block truncate px-2.5 py-1.5 pr-7 text-[12px] ${
                                                isActive
                                                    ? 'font-medium text-emerald-700 dark:text-emerald-300'
                                                    : 'text-gray-600 dark:text-gray-300'
                                            }`}
                                        >
                                            {r.name}
                                        </Link>
                                    ) : (
                                        <div className="truncate px-2.5 py-1.5 pr-12 text-[12px] text-gray-500 dark:text-gray-400">
                                            {r.name}
                                        </div>
                                    )}

                                    {/* Row actions */}
                                    <div className="absolute top-1/2 right-1 flex -translate-y-1/2 items-center gap-0.5">
                                        {tab === 'all' ? (
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <button
                                                        onClick={() =>
                                                            archive(r)
                                                        }
                                                        className="flex h-6 w-6 items-center justify-center rounded text-gray-400 opacity-0 transition-all group-hover:opacity-100 hover:bg-amber-500/10 hover:text-amber-600"
                                                    >
                                                        <Archive className="h-3.5 w-3.5" />
                                                    </button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    Archive report
                                                </TooltipContent>
                                            </Tooltip>
                                        ) : (
                                            <>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <button
                                                            onClick={() =>
                                                                restore(r)
                                                            }
                                                            className="flex h-6 w-6 items-center justify-center rounded text-gray-400 transition-colors hover:bg-emerald-500/10 hover:text-emerald-600"
                                                        >
                                                            <ArchiveRestore className="h-3.5 w-3.5" />
                                                        </button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        Restore report
                                                    </TooltipContent>
                                                </Tooltip>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <button
                                                            onClick={() =>
                                                                setDeleteTarget(
                                                                    r,
                                                                )
                                                            }
                                                            className="flex h-6 w-6 items-center justify-center rounded text-gray-400 transition-colors hover:bg-red-500/10 hover:text-red-500"
                                                        >
                                                            <Trash2 className="h-3.5 w-3.5" />
                                                        </button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        Delete permanently
                                                    </TooltipContent>
                                                </Tooltip>
                                            </>
                                        )}
                                    </div>
                                </div>
                            );
                        })
                    )}
                </nav>
            </div>

            <AlertDialog
                open={deleteTarget !== null}
                onOpenChange={(o) => !o && setDeleteTarget(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Delete report permanently?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            “{deleteTarget?.name}” will be permanently removed.
                            This cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={confirmDelete}
                            className="bg-red-600 hover:bg-red-700"
                        >
                            Delete permanently
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </aside>
    );
}
