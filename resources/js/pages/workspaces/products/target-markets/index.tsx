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
import Pagination from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import ProductLayout from '@/pages/workspaces/products/partials/layout';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router, useForm } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronRight,
    Pencil,
    Plus,
    Search,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    BADGE_NEUTRAL,
    BTN_PRIMARY,
    BTN_SECONDARY,
    CARD,
    EMPTY,
    ICON_BTN,
    ICON_BTN_DANGER,
    INPUT,
    INSET,
    LABEL,
    MUTED,
    ROW_DIVIDE,
    ROW_HOVER,
    SECTION_BORDER,
} from '../lib/ui';
import TargetMarketEntryDialog from './components/entry-dialog';
import {
    type ParentOption,
    type TargetMarketCategory,
    type TargetMarketEntry,
} from './types';

interface Props {
    workspace: Workspace;
    categories: PaginatedData<TargetMarketCategory>;
    summary: { categories: number; sub_categories: number };
    parents: ParentOption[];
    query: { search: string; per_page: number };
}

/** Mirrors TargetMarketController::PER_PAGE. */
const PER_PAGE = [10, 25, 50, 100, 500];

const Index = ({ workspace, categories, summary, parents, query }: Props) => {
    const baseUrl = `/workspaces/${workspace.slug}/products/target-markets`;
    const canManage = usePermission(PERMISSIONS.ManageTargetMarkets);

    const [search, setSearch] = useState(query.search ?? '');
    const [expanded, setExpanded] = useState<number[]>([]);

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<TargetMarketEntry | null>(null);
    const [defaultParentId, setDefaultParentId] = useState<number | null>(null);
    const [deleting, setDeleting] = useState<TargetMarketEntry | null>(null);

    const { delete: destroy, processing } = useForm({});

    const rows = categories.data;
    const pageIds = useMemo(() => rows.map((row) => row.id), [rows]);

    // Debounced so typing doesn't fire a visit per keystroke. Skipped while the
    // box still matches what the server was asked for, which is the state right
    // after a visit lands — otherwise every page change would echo a search.
    useEffect(() => {
        if (search === (query.search ?? '')) return;

        const timer = setTimeout(() => {
            router.get(
                baseUrl,
                { search: search || undefined, per_page: query.per_page },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    function visit(params: Record<string, string | number | undefined>) {
        router.get(
            baseUrl,
            {
                search: search || undefined,
                per_page: query.per_page,
                ...params,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    function toggle(id: number) {
        setExpanded((prev) =>
            prev.includes(id) ? prev.filter((n) => n !== id) : [...prev, id],
        );
    }

    function openCreate(parentId: number | null) {
        setEditing(null);
        setDefaultParentId(parentId);
        setDialogOpen(true);
    }

    function openRename(entry: TargetMarketEntry) {
        setEditing(entry);
        setDialogOpen(true);
    }

    function confirmDelete() {
        if (!deleting) return;
        destroy(`${baseUrl}/${deleting.id}`, {
            preserveScroll: true,
            onSuccess: () => setDeleting(null),
        });
    }

    return (
        <ProductLayout
            workspace={workspace}
            title="Target Market"
            description="The markets the catalog is sold into, as categories and the sub categories underneath them."
            headerActions={
                canManage ? (
                    <button
                        type="button"
                        onClick={() => openCreate(null)}
                        className={BTN_PRIMARY}
                    >
                        <Plus className="h-3.5 w-3.5" />
                        Add Entry
                    </button>
                ) : undefined
            }
        >
            <Head title={`${workspace.name} - Target Market`} />

            <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center">
                <div className="relative flex-1">
                    <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-300 dark:text-gray-600" />
                    <input
                        type="search"
                        className={`${INPUT} pl-9`}
                        placeholder="Search a category..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </div>
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => setExpanded(pageIds)}
                        className={BTN_SECONDARY}
                    >
                        Expand all
                    </button>
                    <button
                        type="button"
                        onClick={() => setExpanded([])}
                        className={BTN_SECONDARY}
                    >
                        Collapse all
                    </button>
                </div>
            </div>

            {rows.length === 0 ? (
                <div className={EMPTY}>
                    <p className={MUTED}>
                        {query.search
                            ? `Nothing matches "${query.search}".`
                            : 'No target markets yet.'}
                    </p>
                    <p className="text-[12px] text-gray-400 dark:text-gray-500">
                        {query.search
                            ? 'Try a different category or sub category.'
                            : 'Add a category — Cardiovascular, Respiratory — then file sub categories under it.'}
                    </p>
                </div>
            ) : (
                <div className={`overflow-hidden ${CARD}`}>
                    <div
                        className={`flex items-center justify-between border-b ${SECTION_BORDER} px-4 py-3`}
                    >
                        <p className={LABEL}>Category</p>
                        <p className={LABEL}>
                            {summary.categories}{' '}
                            {summary.categories === 1
                                ? 'category'
                                : 'categories'}{' '}
                            · {summary.sub_categories} sub{' '}
                            {summary.sub_categories === 1
                                ? 'category'
                                : 'categories'}
                        </p>
                    </div>

                    <div className={ROW_DIVIDE}>
                        {rows.map((category) => {
                            const isOpen = expanded.includes(category.id);

                            return (
                                <div key={category.id}>
                                    <div
                                        className={`flex items-center gap-2 px-4 py-3 ${ROW_HOVER}`}
                                    >
                                        <button
                                            type="button"
                                            onClick={() => toggle(category.id)}
                                            aria-expanded={isOpen}
                                            aria-label={`${isOpen ? 'Collapse' : 'Expand'} ${category.name}`}
                                            className="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-black/[0.03] hover:text-gray-600 dark:hover:bg-white/[0.04] dark:hover:text-gray-300"
                                        >
                                            {isOpen ? (
                                                <ChevronDown className="h-4 w-4" />
                                            ) : (
                                                <ChevronRight className="h-4 w-4" />
                                            )}
                                        </button>

                                        <button
                                            type="button"
                                            onClick={() => toggle(category.id)}
                                            className="min-w-0 flex-1 text-left"
                                        >
                                            <span className="text-[13px] font-medium text-gray-800 dark:text-gray-100">
                                                {category.name}
                                            </span>
                                            <span
                                                className={`ml-2 ${BADGE_NEUTRAL} px-2 py-0.5 font-mono tabular-nums`}
                                            >
                                                {category.children_count}
                                            </span>
                                        </button>

                                        {canManage && (
                                            <div className="flex items-center gap-1.5">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        openCreate(category.id)
                                                    }
                                                    aria-label={`Add a sub category under ${category.name}`}
                                                    className={ICON_BTN}
                                                >
                                                    <Plus className="h-3.5 w-3.5" />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        openRename({
                                                            id: category.id,
                                                            name: category.name,
                                                            parent_id: null,
                                                        })
                                                    }
                                                    aria-label={`Rename ${category.name}`}
                                                    className={ICON_BTN}
                                                >
                                                    <Pencil className="h-3.5 w-3.5" />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setDeleting({
                                                            id: category.id,
                                                            name: category.name,
                                                            parent_id: null,
                                                            children_count:
                                                                category.children_count,
                                                        })
                                                    }
                                                    aria-label={`Delete ${category.name}`}
                                                    className={ICON_BTN_DANGER}
                                                >
                                                    <X className="h-3.5 w-3.5" />
                                                </button>
                                            </div>
                                        )}
                                    </div>

                                    {isOpen && (
                                        <div
                                            className={`border-t ${SECTION_BORDER} ${INSET}`}
                                        >
                                            {category.children.length === 0 ? (
                                                <p className="py-3 pr-4 pl-[48px] text-[12px] text-gray-400 dark:text-gray-500">
                                                    No sub categories yet.
                                                </p>
                                            ) : (
                                                category.children.map(
                                                    (child) => (
                                                        <div
                                                            key={child.id}
                                                            className="flex items-center gap-2 py-2.5 pr-4 pl-[48px]"
                                                        >
                                                            <span className="min-w-0 flex-1 text-[13px] text-gray-500 dark:text-gray-400">
                                                                {child.name}
                                                            </span>

                                                            {canManage && (
                                                                <div className="flex items-center gap-1.5">
                                                                    <button
                                                                        type="button"
                                                                        onClick={() =>
                                                                            openRename(
                                                                                child,
                                                                            )
                                                                        }
                                                                        aria-label={`Rename ${child.name}`}
                                                                        className={`bg-white dark:bg-zinc-900 ${ICON_BTN}`}
                                                                    >
                                                                        <Pencil className="h-3.5 w-3.5" />
                                                                    </button>
                                                                    <button
                                                                        type="button"
                                                                        onClick={() =>
                                                                            setDeleting(
                                                                                child,
                                                                            )
                                                                        }
                                                                        aria-label={`Delete ${child.name}`}
                                                                        className={
                                                                            ICON_BTN_DANGER
                                                                        }
                                                                    >
                                                                        <X className="h-3.5 w-3.5" />
                                                                    </button>
                                                                </div>
                                                            )}
                                                        </div>
                                                    ),
                                                )
                                            )}
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>

                    <div className={`border-t ${SECTION_BORDER} px-4 py-3`}>
                        <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                            <div className="flex items-center gap-3">
                                <div className="flex items-center gap-2">
                                    <span className={LABEL}>Rows</span>
                                    <Select
                                        value={String(categories.per_page)}
                                        onValueChange={(value) =>
                                            visit({
                                                per_page: Number(value),
                                                page: 1,
                                            })
                                        }
                                    >
                                        <SelectTrigger className="h-7 w-[72px] rounded-lg border border-black/6 bg-stone-100 px-2.5 font-mono! text-[11px]! dark:border-white/6 dark:bg-zinc-800">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent className="min-w-[72px]">
                                            {PER_PAGE.map((n) => (
                                                <SelectItem
                                                    key={n}
                                                    value={String(n)}
                                                    className="font-mono! text-[11px]!"
                                                >
                                                    {n}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="h-4 w-px bg-black/6 dark:bg-white/6" />
                                <p className="font-mono text-[11px] text-gray-400 tabular-nums dark:text-gray-500">
                                    Showing {categories.from ?? 0} to{' '}
                                    {categories.to ?? 0} of{' '}
                                    {categories.total.toLocaleString()} entries
                                </p>
                            </div>

                            <Pagination
                                currentPage={categories.current_page}
                                totalPages={categories.last_page}
                                onPageChange={(page) => visit({ page })}
                            />
                        </div>
                    </div>
                </div>
            )}

            {canManage && (
                <TargetMarketEntryDialog
                    open={dialogOpen}
                    onOpenChange={setDialogOpen}
                    baseUrl={baseUrl}
                    parents={parents}
                    editing={editing}
                    defaultParentId={defaultParentId}
                />
            )}

            <AlertDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Delete{' '}
                            {deleting?.parent_id === null
                                ? 'Category'
                                : 'Sub Category'}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Delete <strong>{deleting?.name}</strong>
                            {deleting?.parent_id === null &&
                            (deleting?.children_count ?? 0) > 0
                                ? ` and its ${deleting?.children_count} sub ${
                                      deleting?.children_count === 1
                                          ? 'category'
                                          : 'categories'
                                  }?`
                                : '?'}{' '}
                            This cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={processing}>
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction
                            onClick={confirmDelete}
                            disabled={processing}
                            className="bg-red-600 text-white hover:bg-red-700"
                        >
                            {processing ? 'Deleting…' : 'Delete'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </ProductLayout>
    );
};

export default Index;
