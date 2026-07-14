import PageHeader from '@/components/common/PageHeader';
import { Checkbox } from '@/components/ui/checkbox';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef, RowSelectionState } from '@tanstack/react-table';
import { debounce, omit } from 'lodash';
import {
    ChevronDown,
    HelpCircle,
    MessageSquare,
    MoreHorizontal,
    Package,
    Pencil,
    Plus,
    Search,
    Trash2,
    TriangleAlert,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    AdsBadge,
    FinalBadge,
    FormatBadge,
    InitialAvatar,
} from './components/atoms';
import { AssigneePicker } from './components/assignee-picker';
import { CreativeDetailSheet } from './components/creative-detail-sheet';
import CreativesDateFilter from './components/creatives-date-filter';
import CreativesFilter, {
    CreativesFilterValue,
} from './components/creatives-filter';
import { InlineAssignee } from './components/inline-assignee';
import {
    ADS_STATUS_LABELS,
    AdsStatus,
    Creative,
    FINAL_STATUS_LABELS,
    FinalStatus,
    PageProps,
} from './types';

/**
 * Date cell that flags when a creative's submission (created_at) doesn't match
 * its planned creative_date — late (submitted after) or early (submitted
 * before) — with an explanatory tooltip on the warning icon.
 */
function CreativeDateCell({ creative }: { creative: Creative }) {
    const status = creative.submission_status;
    const flagged = status === 'late' || status === 'early';

    const tooltip =
        status === 'late'
            ? 'Late submission — this creative was created after its scheduled date.'
            : 'Early submission — this creative was created before its scheduled date.';

    const createdLabel = creative.created_at
        ? `Created on ${creative.created_at}`
        : null;

    return (
        <span className="inline-flex items-center gap-1.5 font-mono text-[12px] text-gray-500 dark:text-gray-400">
            {creative.creative_date_label ?? creative.creative_date}
            {flagged && (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <span className="inline-flex cursor-help">
                            <TriangleAlert
                                className={
                                    status === 'late'
                                        ? 'h-3.5 w-3.5 text-amber-500'
                                        : 'h-3.5 w-3.5 text-sky-500'
                                }
                            />
                        </span>
                    </TooltipTrigger>
                    <TooltipContent className="max-w-56 text-[11px]">
                        {tooltip}
                        {createdLabel && (
                            <span className="mt-1 block text-gray-300 dark:text-gray-500">
                                {createdLabel}
                            </span>
                        )}
                    </TooltipContent>
                </Tooltip>
            )}
        </span>
    );
}

export default function CreativesIndex({
    workspace,
    creatives,
    creators,
    approvers,
    products,
    reviewers,
    query,
}: PageProps) {
    const { auth } = usePage<SharedData>().props;
    const currentUserId = auth?.user?.id ?? 0;

    const canCreate = usePermission(PERMISSIONS.CreateCreatives);
    const canEdit = usePermission(PERMISSIONS.EditCreatives);
    const canDelete = usePermission(PERMISSIONS.DeleteCreatives);
    const canReview = usePermission(PERMISSIONS.ReviewCreatives);
    const canUpdateStatus = usePermission(PERMISSIONS.UpdateCreativeStatus);

    const [detailCreativeId, setDetailCreativeId] = useState<number | null>(
        null,
    );
    const [deleteId, setDeleteId] = useState<number | null>(null);

    // Bulk reviewer assignment: row selection + the reviewers to apply.
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
    const [bulkReviewerIds, setBulkReviewerIds] = useState<number[]>([]);
    const [bulkProcessing, setBulkProcessing] = useState(false);

    const selectedIds = useMemo(
        () => Object.keys(rowSelection).filter((id) => rowSelection[id]),
        [rowSelection],
    );

    const handleBulkAssign = (
        mode: 'add' | 'replace',
        reviewerIds: number[] = bulkReviewerIds,
    ) => {
        router.post(
            `${baseUrl}/bulk-reviewers`,
            {
                ids: selectedIds.map(Number),
                reviewer_ids: reviewerIds,
                mode,
            },
            {
                preserveScroll: true,
                onStart: () => setBulkProcessing(true),
                onFinish: () => setBulkProcessing(false),
                onSuccess: () => {
                    setRowSelection({});
                    setBulkReviewerIds([]);
                },
            },
        );
    };

    // Always derive from fresh props so reviews/remarks update without reopening
    const detailCreative =
        detailCreativeId !== null
            ? (creatives.data.find((c) => c.id === detailCreativeId) ?? null)
            : null;

    const baseUrl = `/workspaces/${workspace.slug}/creatives`;
    const initialSorting = useMemo(
        () => toFrontendSort(query.sort ?? null),
        [query.sort],
    );
    const [search, setSearch] = useState(query.filter?.search ?? '');

    // `navigate` is captured once by uncontrolled children (e.g. the flatpickr
    // DatePicker only re-binds its onChange when its own deps change). Reading
    // `query` straight from the closure would therefore go stale and clobber
    // filters set since the capture. Keep the latest `query` in a ref and read
    // through it so `navigate` has a stable identity yet always sees fresh
    // filters.
    const queryRef = useRef(query);
    queryRef.current = query;

    const navigate = useCallback(
        (params: Record<string, string | number | null | undefined>) => {
            const q = queryRef.current;
            // Carry over EVERY active filter (not a hardcoded subset) so nothing —
            // e.g. final_status — is dropped when paginating, sorting or searching.
            const filters: Record<string, string | undefined> = {};
            Object.entries(q.filter ?? {}).forEach(([key, value]) => {
                filters[`filter[${key}]`] = value || undefined;
            });
            router.get(
                baseUrl,
                {
                    sort: q.sort,
                    page: 1,
                    per_page: q.per_page,
                    'filter[search]': q.filter?.search || undefined,
                    'filter[format]': q.filter?.format || undefined,
                    'filter[ads_status]': q.filter?.ads_status || undefined,
                    'filter[final_status]':
                        q.filter?.final_status || undefined,
                    'filter[creator_id]': q.filter?.creator_id || undefined,
                    'filter[approved_by]': q.filter?.approved_by || undefined,
                    'filter[product_id]': q.filter?.product_id || undefined,
                    'filter[creative_date_from]':
                        q.filter?.creative_date_from || undefined,
                    'filter[creative_date_to]':
                        q.filter?.creative_date_to || undefined,
                    'filter[created_at_from]':
                        q.filter?.created_at_from || undefined,
                    'filter[created_at_to]':
                        q.filter?.created_at_to || undefined,
                    'filter[approved_at_from]':
                        q.filter?.approved_at_from || undefined,
                    'filter[approved_at_to]':
                        q.filter?.approved_at_to || undefined,
                    ...params,
                },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        },
        [baseUrl],
    );

    const debouncedSearch = useCallback(
        debounce(
            (value: string) =>
                navigate({ 'filter[search]': value || undefined, page: 1 }),
            400,
        ),
        [navigate],
    );

    useEffect(() => {
        if (search !== (query.filter?.search ?? '')) debouncedSearch(search);
        return () => debouncedSearch.cancel();
    }, [search]);

    const handleDelete = (id: number) => {
        router.delete(`${baseUrl}/${id}`, {
            onSuccess: () => setDeleteId(null),
        });
    };

    const columns: ColumnDef<Creative>[] = [
        ...(canEdit
            ? [
                  {
                      id: 'select',
                      enableSorting: false,
                      header: ({ table }) => (
                          <Checkbox
                              checked={
                                  table.getIsAllPageRowsSelected() ||
                                  (table.getIsSomePageRowsSelected() &&
                                      'indeterminate')
                              }
                              onCheckedChange={(value) =>
                                  table.toggleAllPageRowsSelected(!!value)
                              }
                              aria-label="Select all"
                          />
                      ),
                      cell: ({ row }) => (
                          <Checkbox
                              checked={row.getIsSelected()}
                              onCheckedChange={(value) =>
                                  row.toggleSelected(!!value)
                              }
                              aria-label="Select row"
                              onClick={(e) => e.stopPropagation()}
                          />
                      ),
                  } as ColumnDef<Creative>,
              ]
            : []),
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Creative" />
            ),
            cell: ({ row: { original: c } }) => (
                <div className="min-w-0">
                    <p className="text-[13px] font-medium text-black dark:text-gray-200">
                        {c.name}
                    </p>
                    {c.product && (
                        <span
                            title={c.product.title}
                            className="mt-1 inline-flex max-w-[180px] items-center gap-1 truncate rounded bg-indigo-50 px-1.5 py-0.5 font-mono text-[10px] font-medium text-indigo-600 dark:bg-indigo-500/[0.12] dark:text-indigo-400"
                        >
                            <Package className="h-2.5 w-2.5 shrink-0" />{' '}
                            <span className="truncate">{c.product.title}</span>
                        </span>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'creative_date',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Date" />
            ),
            cell: ({ row }) => <CreativeDateCell creative={row.original} />,
        },
        {
            accessorKey: 'format',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Format" />
            ),
            cell: ({ row }) => <FormatBadge format={row.original.format} />,
        },
        {
            accessorKey: 'creator',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Creator" />
            ),
            cell: ({ row: { original: c } }) =>
                c.creator ? (
                    <span title={c.creator.name} className="inline-flex">
                        <InitialAvatar
                            name={c.creator.name}
                            className="h-7 w-7 bg-emerald-500/[0.12] text-emerald-600 dark:text-emerald-400"
                        />
                    </span>
                ) : (
                    <span className="font-mono text-[12px] text-gray-300 dark:text-gray-700">
                        —
                    </span>
                ),
        },
        {
            accessorKey: 'assigned_reviewers',
            enableSorting: false,
            header: () => (
                <span className="font-mono text-[10px] font-medium tracking-wider text-gray-300 uppercase dark:text-gray-600">
                    Reviewers
                </span>
            ),
            cell: ({ row: { original: c } }) => (
                <InlineAssignee
                    creative={c}
                    reviewers={reviewers}
                    baseUrl={baseUrl}
                    canEdit={canEdit}
                />
            ),
        },
        {
            accessorKey: 'review_count',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Reviews" />
            ),
            cell: ({ row: { original: c } }) =>
                c.review_count > 0 ? (
                    <span className="inline-flex items-center gap-1 font-mono text-[11px] text-gray-500 dark:text-gray-400">
                        <MessageSquare className="h-3 w-3" /> {c.review_count}
                    </span>
                ) : (
                    <span className="font-mono text-[11px] text-gray-300 dark:text-gray-700">
                        —
                    </span>
                ),
        },
        {
            accessorKey: 'final_status',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Status" />
            ),
            cell: ({ row }) => {
                const c = row.original;
                if (!canUpdateStatus)
                    return <FinalBadge status={c.final_status} />;
                return (
                    <div onClick={(e) => e.stopPropagation()}>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button className="inline-flex cursor-pointer items-center gap-0.5 rounded transition-opacity hover:opacity-70">
                                    <FinalBadge status={c.final_status} />
                                    <ChevronDown className="h-3 w-3 text-gray-400" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start">
                                {(
                                    Object.entries(FINAL_STATUS_LABELS) as [
                                        FinalStatus,
                                        string,
                                    ][]
                                ).map(([v, l]) => (
                                    <DropdownMenuItem
                                        key={v}
                                        className="font-mono text-[12px]"
                                        onClick={() =>
                                            router.put(
                                                `${baseUrl}/${c.id}`,
                                                { final_status: v },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {l}
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                );
            },
        },
        {
            accessorKey: 'ads_status',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Ads" />
            ),
            cell: ({ row }) => {
                const c = row.original;
                if (!canUpdateStatus) return <AdsBadge status={c.ads_status} />;
                return (
                    <div onClick={(e) => e.stopPropagation()}>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button className="inline-flex cursor-pointer items-center gap-0.5 rounded transition-opacity hover:opacity-70">
                                    <AdsBadge status={c.ads_status} />
                                    <ChevronDown className="h-3 w-3 text-gray-400" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start">
                                {(
                                    Object.entries(ADS_STATUS_LABELS) as [
                                        AdsStatus,
                                        string,
                                    ][]
                                ).map(([v, l]) => (
                                    <DropdownMenuItem
                                        key={v}
                                        className="font-mono text-[12px]"
                                        onClick={() =>
                                            router.put(
                                                `${baseUrl}/${c.id}`,
                                                { ads_status: v },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {l}
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                );
            },
        },
        {
            accessorKey: 'approved_at',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Approved" />
            ),
            cell: ({ row }) =>
                row.original.approved_at ? (
                    <div className="min-w-0">
                        <span className="block font-mono text-[12px] text-emerald-600 dark:text-emerald-400">
                            {row.original.approved_at}
                        </span>
                        {row.original.approved_by && (
                            <span className="mt-0.5 block truncate font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                {row.original.approved_by.name}
                            </span>
                        )}
                    </div>
                ) : (
                    <span className="font-mono text-[12px] text-gray-300 dark:text-gray-700">
                        —
                    </span>
                ),
        },
        {
            id: 'actions',
            enableSorting: false,
            header: () => null,
            cell: ({ row: { original: c } }) =>
                canEdit || canDelete ? (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button
                                className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 transition-all hover:border-black/12 hover:bg-stone-100 hover:text-gray-600 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-500 dark:hover:bg-zinc-700 dark:hover:text-gray-300"
                                onClick={(e) => e.stopPropagation()}
                            >
                                <MoreHorizontal className="h-3.5 w-3.5" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {canEdit && (
                                <DropdownMenuItem
                                    className="font-mono text-[12px]"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        router.visit(`${baseUrl}/${c.id}/edit`);
                                    }}
                                >
                                    <Pencil className="mr-2 h-3.5 w-3.5" /> Edit
                                </DropdownMenuItem>
                            )}
                            {canEdit && canDelete && <DropdownMenuSeparator />}
                            {canDelete && (
                                <DropdownMenuItem
                                    className="font-mono text-[12px] text-red-500 focus:text-red-500"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        setDeleteId(c.id);
                                    }}
                                >
                                    <Trash2 className="mr-2 h-3.5 w-3.5" />{' '}
                                    Delete
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                ) : null,
        },
    ];

    // Filter values arrive as comma-separated strings; expand them into arrays for
    // the multi-select UI.
    const parseList = (v?: string) => (v ? v.split(',').filter(Boolean) : []);

    const filterValue: CreativesFilterValue = useMemo(
        () => ({
            formats: parseList(query.filter?.format),
            ads_statuses: parseList(query.filter?.ads_status),
            final_statuses: parseList(query.filter?.final_status),
            creator_ids: parseList(query.filter?.creator_id),
            approver_ids: parseList(query.filter?.approved_by),
            product_ids: parseList(query.filter?.product_id),
        }),
        [query.filter],
    );

    const formatOptions = [
        { key: 'video', label: 'Video' },
        { key: 'image', label: 'Image' },
    ];
    const adsStatusOptions = (
        Object.entries(ADS_STATUS_LABELS) as [AdsStatus, string][]
    ).map(([key, label]) => ({ key, label }));
    const finalStatusOptions = (
        Object.entries(FINAL_STATUS_LABELS) as [FinalStatus, string][]
    ).map(([key, label]) => ({ key, label }));
    const creatorOptions = creators.map((c) => ({
        key: String(c.id),
        label: c.name,
    }));
    const approverOptions = approvers.map((a) => ({
        key: String(a.id),
        label: a.name,
    }));
    const productOptions = products.map((p) => ({
        key: String(p.id),
        label: p.title,
    }));

    const applyFilters = (v: CreativesFilterValue) =>
        navigate({
            'filter[format]': v.formats.join(',') || undefined,
            'filter[ads_status]': v.ads_statuses.join(',') || undefined,
            'filter[final_status]': v.final_statuses.join(',') || undefined,
            'filter[creator_id]': v.creator_ids.join(',') || undefined,
            'filter[approved_by]': v.approver_ids.join(',') || undefined,
            'filter[product_id]': v.product_ids.join(',') || undefined,
            page: 1,
        });

    return (
        <AppLayout>
            <Head title="Creative Tracker" />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Creative Tracker"
                    description="Track creatives from ideation through review and launch"
                >
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <a
                                href="https://drive.google.com/file/d/18ov38v52BFzI64dt3iyzxBlULJ9ZkBta/view?usp=sharing"
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label="Watch the setup tutorial"
                                className="flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition-all hover:bg-stone-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-zinc-800 dark:hover:text-gray-100"
                            >
                                <HelpCircle className="h-5 w-5" />
                            </a>
                        </TooltipTrigger>
                        <TooltipContent>Watch the setup tutorial</TooltipContent>
                    </Tooltip>
                    {canCreate && (
                        <button
                            onClick={() => router.visit(`${baseUrl}/create`)}
                            className="flex h-9 items-center rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                        >
                            <Plus className="mr-1.5 h-3.5 w-3.5" /> New Creative
                        </button>
                    )}
                </PageHeader>

                {/* Toolbar */}
                <div className="mb-3 flex items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                            placeholder="Search name, headline..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>

                    <div className="flex-1" />

                    <CreativesDateFilter
                        filter={query.filter}
                        onChange={(params) => navigate({ ...params, page: 1 })}
                    />

                    <CreativesFilter
                        value={filterValue}
                        formatOptions={formatOptions}
                        adsStatusOptions={adsStatusOptions}
                        finalStatusOptions={finalStatusOptions}
                        creatorOptions={creatorOptions}
                        approverOptions={approverOptions}
                        productOptions={productOptions}
                        onApply={applyFilters}
                    />
                </div>

                {canEdit && selectedIds.length > 0 && (
                    <div className="mb-3 flex flex-wrap items-center gap-3 rounded-[12px] border border-emerald-500/20 bg-emerald-50/60 px-4 py-2.5 dark:border-emerald-400/20 dark:bg-emerald-500/5">
                        <span className="font-mono text-[12px] font-medium text-gray-700 dark:text-gray-200">
                            {selectedIds.length} selected
                        </span>
                        <div className="w-64">
                            <AssigneePicker
                                reviewers={reviewers}
                                selectedIds={bulkReviewerIds}
                                onChange={setBulkReviewerIds}
                            />
                        </div>
                        <div className="flex items-center gap-2">
                            <button
                                onClick={() => handleBulkAssign('add')}
                                disabled={
                                    bulkProcessing ||
                                    bulkReviewerIds.length === 0
                                }
                                title="Add the selected reviewers to every selected creative"
                                className="flex h-8 items-center rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                            >
                                Assign
                            </button>
                            <button
                                onClick={() => handleBulkAssign('replace', [])}
                                disabled={bulkProcessing}
                                title="Remove all reviewers from every selected creative"
                                className="flex h-8 items-center rounded-lg border border-black/8 bg-white px-3.5 font-mono! text-[12px]! font-medium text-rose-600 transition-all hover:bg-rose-50 disabled:opacity-50 dark:border-white/8 dark:bg-zinc-800 dark:text-rose-400 dark:hover:bg-rose-500/10"
                            >
                                Unassign
                            </button>
                            <button
                                onClick={() => {
                                    setRowSelection({});
                                    setBulkReviewerIds([]);
                                }}
                                disabled={bulkProcessing}
                                className="flex h-8 items-center rounded-lg px-2 font-mono! text-[12px]! font-medium text-gray-500 transition-all hover:text-gray-700 disabled:opacity-50 dark:text-gray-400 dark:hover:text-gray-200"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                )}

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={creatives.data}
                        meta={{ ...omit(creatives, ['data']) }}
                        initialSorting={initialSorting}
                        getRowId={(row) => String(row.id)}
                        {...(canEdit
                            ? {
                                  rowSelection,
                                  onRowSelectionChange: setRowSelection,
                              }
                            : {})}
                        onFetch={(params) =>
                            navigate({
                                sort: (params?.sort as string) ?? undefined,
                                page: (params?.page as number) ?? undefined,
                                per_page:
                                    (params?.per_page as number) ?? undefined,
                            })
                        }
                        onRowClick={(row) => setDetailCreativeId(row.id)}
                    />
                </div>
            </div>

            {detailCreative && (
                <CreativeDetailSheet
                    creative={detailCreative}
                    workspace={workspace}
                    currentUserId={currentUserId}
                    canEdit={canEdit}
                    canReview={canReview}
                    onEdit={() =>
                        router.visit(`${baseUrl}/${detailCreative.id}/edit`)
                    }
                    onClose={() => setDetailCreativeId(null)}
                />
            )}

            {/* Delete confirmation */}
            <Dialog
                open={deleteId !== null}
                onOpenChange={(v) => !v && setDeleteId(null)}
            >
                <DialogContent className="gap-0 overflow-hidden border-none p-0 shadow-2xl sm:max-w-sm dark:bg-zinc-900 [&_[data-default-close=true]]:hidden">
                    <div className="relative border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
                        <DialogHeader>
                            <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                                Delete Creative
                            </DialogTitle>
                        </DialogHeader>
                        <DialogClose className="absolute top-4 right-4 inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-black/5 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-white/6 dark:hover:text-gray-300">
                            <X className="h-4 w-4" />
                            <span className="sr-only">Close</span>
                        </DialogClose>
                    </div>
                    <div className="px-5 py-4">
                        <p className="font-mono text-[12px] text-gray-500 dark:text-gray-400">
                            Permanently deletes the creative along with all
                            reviews and campaign data.
                        </p>
                    </div>
                    <div className="flex items-center justify-end gap-2 border-t border-black/6 bg-stone-50/50 px-5 py-3 dark:border-white/6 dark:bg-white/2">
                        <button
                            type="button"
                            onClick={() => setDeleteId(null)}
                            className="flex h-9 items-center rounded-lg border border-black/8 bg-white px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            onClick={() =>
                                deleteId !== null && handleDelete(deleteId)
                            }
                            className="flex h-9 items-center rounded-lg bg-red-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-red-700"
                        >
                            Delete
                        </button>
                    </div>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
