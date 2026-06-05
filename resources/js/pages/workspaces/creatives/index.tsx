import PageHeader from '@/components/common/PageHeader';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import DatePicker from '@/components/ui/date-picker';
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
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { debounce, omit } from 'lodash';
import {
    ChevronDown,
    Clapperboard,
    FileImage,
    MessageSquare,
    MoreHorizontal,
    Package,
    Pencil,
    Plus,
    Search,
    SlidersHorizontal,
    Trash2,
    X,
} from 'lucide-react';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
    AdsBadge,
    FinalBadge,
    FormatBadge,
    InitialAvatar,
} from './components/atoms';
import { CreativeDetailSheet } from './components/creative-detail-sheet';
import { InlineAssignee } from './components/inline-assignee';
import {
    ADS_DOT,
    ADS_STATUS_LABELS,
    AdsStatus,
    Creative,
    FINAL_STATUS_LABELS,
    FinalStatus,
    PageProps,
} from './types';

export default function CreativesIndex({
    workspace,
    creatives,
    creators,
    reviewers,
    query,
}: PageProps) {
    const { auth } = usePage<SharedData>().props;
    const currentUserId = auth?.user?.id ?? 0;

    const canCreate = usePermission(PERMISSIONS.CreateCreatives);
    const canEdit = usePermission(PERMISSIONS.EditCreatives);
    const canDelete = usePermission(PERMISSIONS.DeleteCreatives);
    const canReview = usePermission(PERMISSIONS.ReviewCreatives);

    const [detailCreativeId, setDetailCreativeId] = useState<number | null>(
        null,
    );
    const [deleteId, setDeleteId] = useState<number | null>(null);

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

    const navigate = useCallback(
        (params: Record<string, string | number | null | undefined>) => {
            router.get(
                baseUrl,
                {
                    sort: query.sort,
                    page: 1,
                    per_page: query.per_page,
                    'filter[search]': query.filter?.search || undefined,
                    'filter[format]': query.filter?.format || undefined,
                    'filter[ads_status]': query.filter?.ads_status || undefined,
                    'filter[creator_id]': query.filter?.creator_id || undefined,
                    'filter[date_from]': query.filter?.date_from || undefined,
                    'filter[date_to]': query.filter?.date_to || undefined,
                    ...params,
                },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        },
        [baseUrl, query],
    );

    const dateRange = useMemo(() => {
        const from = query.filter?.date_from;
        const to = query.filter?.date_to;
        return from && to ? [from, to] : undefined;
    }, [query.filter?.date_from, query.filter?.date_to]);

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
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-gray-500 dark:text-gray-400">
                    {row.original.creative_date}
                </span>
            ),
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
                <SortableHeader column={column} title="Final" />
            ),
            cell: ({ row }) => {
                const c = row.original;
                if (!canEdit) return <FinalBadge status={c.final_status} />;
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
                    <span className="font-mono text-[12px] text-emerald-600 dark:text-emerald-400">
                        {row.original.approved_at}
                    </span>
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

    // Filter item style helper
    const filterItem = (active: boolean) =>
        `flex w-full items-center gap-2 rounded-[8px] px-2.5 py-1.5 font-mono text-[11px] transition-colors ${active ? 'bg-emerald-500/[0.08] text-emerald-600 dark:bg-emerald-500/[0.10] dark:text-emerald-400' : 'text-gray-600 hover:bg-stone-100 dark:text-gray-400 dark:hover:bg-zinc-800'}`;

    const activeFilterCount = [
        query.filter?.format,
        query.filter?.ads_status,
        query.filter?.creator_id,
    ].filter(Boolean).length;

    return (
        <AppLayout>
            <Head title="Creative Tracker" />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Creative Tracker"
                    description="Track creatives from ideation through review and launch"
                >
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

                    <DatePicker
                        id="creatives-date-range"
                        mode="range"
                        placeholder="All dates"
                        defaultDate={dateRange as never}
                        onChange={(dates) => {
                            if (dates.length === 2) {
                                const fmt = (d: Date) =>
                                    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
                                navigate({
                                    'filter[date_from]': fmt(dates[0]),
                                    'filter[date_to]': fmt(dates[1]),
                                    page: 1,
                                });
                            } else if (dates.length === 0) {
                                navigate({
                                    'filter[date_from]': undefined,
                                    'filter[date_to]': undefined,
                                    page: 1,
                                });
                            }
                        }}
                    />

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button className="inline-flex h-9 items-center gap-2 rounded-[10px] border border-black/8 bg-white px-3 font-mono text-[12px] font-medium text-gray-500 shadow-[0_1px_3px_rgba(0,0,0,0.06)] transition-colors hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-400 dark:shadow-none dark:hover:bg-zinc-800">
                                <SlidersHorizontal className="h-3.5 w-3.5" />
                                Filters
                                {activeFilterCount > 0 && (
                                    <span className="flex h-4 min-w-4 items-center justify-center rounded-full bg-emerald-500 px-1 font-mono text-[9px] font-bold text-white">
                                        {activeFilterCount}
                                    </span>
                                )}
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent className="w-52 p-2" align="end">
                            <p className="mb-1 px-2 font-mono text-[9px] font-medium tracking-widest text-gray-400 uppercase dark:text-gray-600">
                                Format
                            </p>
                            {(
                                [
                                    {
                                        key: '',
                                        icon: null,
                                        label: 'All formats',
                                    },
                                    {
                                        key: 'video',
                                        icon: (
                                            <Clapperboard className="h-3 w-3" />
                                        ),
                                        label: 'Video',
                                    },
                                    {
                                        key: 'image',
                                        icon: <FileImage className="h-3 w-3" />,
                                        label: 'Image',
                                    },
                                ] as {
                                    key: string;
                                    icon: React.ReactNode;
                                    label: string;
                                }[]
                            ).map(({ key, icon, label }) => (
                                <button
                                    key={key}
                                    className={filterItem(
                                        (query.filter?.format ?? '') === key,
                                    )}
                                    onClick={() =>
                                        navigate({
                                            'filter[format]': key || undefined,
                                            page: 1,
                                        })
                                    }
                                >
                                    {icon ?? <span className="h-3 w-3" />}
                                    {label}
                                </button>
                            ))}

                            <DropdownMenuSeparator className="my-2" />

                            <p className="mb-1 px-2 font-mono text-[9px] font-medium tracking-widest text-gray-400 uppercase dark:text-gray-600">
                                Ads Status
                            </p>
                            <button
                                className={filterItem(
                                    !query.filter?.ads_status,
                                )}
                                onClick={() =>
                                    navigate({
                                        'filter[ads_status]': undefined,
                                        page: 1,
                                    })
                                }
                            >
                                <span className="h-3 w-3" />
                                All statuses
                            </button>
                            {(
                                Object.entries(ADS_STATUS_LABELS) as [
                                    AdsStatus,
                                    string,
                                ][]
                            ).map(([v, l]) => (
                                <button
                                    key={v}
                                    className={filterItem(
                                        query.filter?.ads_status === v,
                                    )}
                                    onClick={() =>
                                        navigate({
                                            'filter[ads_status]': v,
                                            page: 1,
                                        })
                                    }
                                >
                                    <span
                                        className={`h-1.5 w-1.5 rounded-full ${ADS_DOT[v]}`}
                                    />
                                    {l}
                                </button>
                            ))}

                            {creators.length > 0 && (
                                <>
                                    <DropdownMenuSeparator className="my-2" />
                                    <p className="mb-1 px-2 font-mono text-[9px] font-medium tracking-widest text-gray-400 uppercase dark:text-gray-600">
                                        Creator
                                    </p>
                                    <button
                                        className={filterItem(
                                            !query.filter?.creator_id,
                                        )}
                                        onClick={() =>
                                            navigate({
                                                'filter[creator_id]': undefined,
                                                page: 1,
                                            })
                                        }
                                    >
                                        <span className="h-3 w-3" />
                                        All creators
                                    </button>
                                    {creators.map((c) => (
                                        <button
                                            key={c.id}
                                            className={filterItem(
                                                query.filter?.creator_id ===
                                                    String(c.id),
                                            )}
                                            onClick={() =>
                                                navigate({
                                                    'filter[creator_id]':
                                                        String(c.id),
                                                    page: 1,
                                                })
                                            }
                                        >
                                            <span className="flex h-3 w-3 shrink-0 items-center justify-center rounded-full bg-emerald-500/20 font-mono text-[8px] font-bold text-emerald-600 dark:text-emerald-400">
                                                {c.name.charAt(0).toUpperCase()}
                                            </span>
                                            <span className="truncate">
                                                {c.name}
                                            </span>
                                        </button>
                                    ))}
                                </>
                            )}

                            {activeFilterCount > 0 && (
                                <>
                                    <DropdownMenuSeparator className="my-2" />
                                    <button
                                        className="flex w-full items-center justify-center rounded-[8px] px-2.5 py-1.5 font-mono text-[11px] text-red-500 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/[0.08]"
                                        onClick={() =>
                                            navigate({
                                                'filter[format]': undefined,
                                                'filter[ads_status]': undefined,
                                                'filter[creator_id]': undefined,
                                                page: 1,
                                            })
                                        }
                                    >
                                        Clear all filters
                                    </button>
                                </>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={creatives.data}
                        meta={{ ...omit(creatives, ['data']) }}
                        initialSorting={initialSorting}
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
