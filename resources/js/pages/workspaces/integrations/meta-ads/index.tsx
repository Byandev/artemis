import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import DatePicker from '@/components/ui/date-picker';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import axios from 'axios';
import clsx from 'clsx';
import { formatDate } from 'date-fns';
import flatpickr from 'flatpickr';
import { omit } from 'lodash';
import type { LucideIcon } from 'lucide-react';
import {
    ChartColumn,
    Check,
    ChevronDown,
    Download,
    Flag,
    Goal,
    Image as ImageIcon,
    Layers,
    LayoutGrid,
    Loader2,
    Megaphone,
    Play,
    Search,
    Target,
    Type,
    UserPlus,
    UserRound,
    Wallet,
    X,
} from 'lucide-react';
import moment from 'moment';
import {
    Dispatch,
    ReactNode,
    SetStateAction,
    useEffect,
    useMemo,
    useState,
} from 'react';
import { InlineOwner, OwnerOption } from '../components/inline-owner';
import {
    ColumnVisibilityMenu,
    GridFilter,
    INSIGHTS_OPTIONS,
    InsightFilterBuilder,
    InsightsMetrics,
    ObjectiveOption,
    StatusLabel,
    buildInsightsColumns,
    deserializeGridFilters,
    formatBudget,
    serializeDateFilters,
    serializeMetricFilters,
    useColumnPresets,
} from './_shared';
import { RowTimelineModal, TimelineTarget } from './row-timeline-modal';

import DateOption = flatpickr.Options.DateOption;

type GroupBy =
    | 'ad_name'
    | 'ad'
    | 'campaign'
    | 'ad_set'
    | 'account'
    | 'page'
    | 'page_owner'
    | 'optimization_goal'
    | 'campaign_objective';

const GROUP_BY_OPTIONS: {
    value: GroupBy;
    label: string;
    icon: LucideIcon;
    hint?: string;
}[] = [
    {
        value: 'ad_name',
        label: 'Ad Name',
        icon: Type,
        hint: 'Ads sharing a name, collapsed',
    },
    { value: 'ad', label: 'Ad Id', icon: ImageIcon, hint: 'One row per ad' },
    {
        value: 'campaign',
        label: 'Campaign',
        icon: Megaphone,
        hint: 'Rolled up per campaign',
    },
    {
        value: 'ad_set',
        label: 'Ad Set',
        icon: Layers,
        hint: 'Rolled up per ad set',
    },
    {
        value: 'account',
        label: 'Ad Account',
        icon: Wallet,
        hint: 'Rolled up per ad account',
    },
    {
        value: 'page',
        label: 'Page',
        icon: Flag,
        hint: 'The page the ad set promotes',
    },
    {
        value: 'page_owner',
        label: 'Page Owner',
        icon: UserRound,
        hint: 'Who owns that page',
    },
    {
        value: 'optimization_goal',
        label: 'Optimization Goal',
        icon: Target,
        hint: "The ad set's optimization goal",
    },
    {
        value: 'campaign_objective',
        label: 'Campaign Objective',
        icon: Goal,
        hint: "The campaign's objective",
    },
];

/** Whether the grouped dimension carries a per-row status + (for ads) a thumbnail. */
const HAS_STATUS: Record<GroupBy, boolean> = {
    ad_name: false,
    ad: true,
    campaign: true,
    ad_set: true,
    account: false,
    page: false,
    page_owner: false,
    optimization_goal: false,
    campaign_objective: false,
};

interface Row extends InsightsMetrics {
    // Meta ad/campaign/ad-set ids are bigints beyond JS's safe-integer range,
    // so the server sends them as strings — never coerce back to a number.
    id: string;
    name: string | null;
    status?: string | null;
    effective_status?: string | null;
    thumbnail_url?: string | null;
    image_url?: string | null;
    video_id?: number | string | null;
    media_type?: 'video' | 'image' | null;
    ads_count?: number;
    // Present only when grouping by campaign / ad set (the budget lives on the
    // entity, not the insights aggregate). Bigint-safe so kept as string|number.
    daily_budget?: number | string | null;
    lifetime_budget?: number | string | null;
    // Internal creator tag — only sent for the `ad` (single-ad) grouping.
    creator_id?: number | null;
    creator_name?: string | null;
}

/**
 * Whether a creative is a video. Prefer the server's media_type (object_type
 * VIDEO || video_id) and fall back to video_id when it isn't present.
 */
function isVideoCreative(r: {
    media_type?: string | null;
    video_id?: number | string | null;
}): boolean {
    return r.media_type ? r.media_type === 'video' : r.video_id != null;
}

interface AccountOption {
    id: string;
    name: string;
}

interface Props {
    workspace: Workspace;
    accounts: AccountOption[];
    members: OwnerOption[];
    selectedAccounts: string[];
    /** Campaign objectives available to the Filters builder's value picker. */
    objectives?: ObjectiveOption[];
    dateRange: { since: string; until: string };
    query?: {
        sort?: string | null;
        groupBy?: GroupBy;
        perPage?: number | string;
        page?: number | string;
        since?: string;
        until?: string;
        filter?: { search?: string };
        metricFilters?: unknown[];
        dateFilters?: unknown[];
    };
}

function adDetailUrl(slug: string, adId: number | string) {
    return `/workspaces/${slug}/integrations/meta/ads-manager/ads/${adId}/detail`;
}

function dataUrl(slug: string) {
    return `/workspaces/${slug}/integrations/meta/ads-manager/data`;
}

function creatorUrl(slug: string, adId: string) {
    return `/workspaces/${slug}/integrations/meta/ads-manager/ads/${adId}/creator`;
}

function bulkCreatorUrl(slug: string) {
    return `/workspaces/${slug}/integrations/meta/ads-manager/ads/creator/bulk`;
}

const creatorOf = (row: Row): OwnerOption | null =>
    row.creator_id != null
        ? { id: row.creator_id, name: row.creator_name ?? '' }
        : null;

/**
 * Inline + bulk creator tagging against a rows-in-state table. Optimistically
 * patches the given rows setter, then persists via the single / bulk endpoints,
 * rolling back on failure. Shared by the main grid and the ads-in-group modal.
 */
function useCreatorTagging(
    slug: string,
    members: OwnerOption[],
    setRows: Dispatch<SetStateAction<PaginatedData<Row> | null>>,
) {
    const [saving, setSaving] = useState<Record<string, boolean>>({});

    const patchRows = (ids: Set<string>, creator: OwnerOption | null) =>
        setRows((prev) =>
            prev
                ? {
                      ...prev,
                      data: prev.data.map((r) =>
                          ids.has(r.id)
                              ? {
                                    ...r,
                                    creator_id: creator?.id ?? null,
                                    creator_name: creator?.name ?? null,
                                }
                              : r,
                      ),
                  }
                : prev,
        );

    const assign = (row: Row, creatorId: number | null) => {
        const next = creatorId
            ? (members.find((m) => m.id === creatorId) ?? null)
            : null;
        const prev = creatorOf(row);
        patchRows(new Set([row.id]), next);
        setSaving((s) => ({ ...s, [row.id]: true }));
        axios
            .patch(creatorUrl(slug, row.id), { creator_id: creatorId })
            .catch(() => patchRows(new Set([row.id]), prev))
            .finally(() => setSaving((s) => ({ ...s, [row.id]: false })));
    };

    const bulkAssign = (
        ids: string[],
        creatorId: number | null,
        onDone?: () => void,
    ) => {
        if (ids.length === 0) return;
        const next = creatorId
            ? (members.find((m) => m.id === creatorId) ?? null)
            : null;
        let snapshot: PaginatedData<Row> | null = null;
        setRows((prev) => {
            snapshot = prev;
            return prev
                ? {
                      ...prev,
                      data: prev.data.map((r) =>
                          ids.includes(r.id)
                              ? {
                                    ...r,
                                    creator_id: next?.id ?? null,
                                    creator_name: next?.name ?? null,
                                }
                              : r,
                      ),
                  }
                : prev;
        });
        axios
            .post(bulkCreatorUrl(slug), { ad_ids: ids, creator_id: creatorId })
            .catch(() => setRows(snapshot))
            .finally(() => onDone?.());
    };

    return { saving, assign, bulkAssign };
}

/** Local multi-select state for the ad grid / modal, keyed by ad id. */
function useRowSelection() {
    const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());

    const toggleRow = (id: string) =>
        setSelectedIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });

    const toggleAll = (ids: string[], checked: boolean) =>
        setSelectedIds((prev) => {
            const next = new Set(prev);
            ids.forEach((id) => (checked ? next.add(id) : next.delete(id)));
            return next;
        });

    const clear = () => setSelectedIds(new Set());

    return { selectedIds, toggleRow, toggleAll, clear };
}

/**
 * Compact bulk-creator control for the selection bar: assign any member or
 * clear the creator across the currently selected ads.
 */
function BulkCreatorControl({
    members,
    onAssign,
}: {
    members: OwnerOption[];
    onAssign: (creatorId: number | null) => void;
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        return q
            ? members.filter((m) => m.name.toLowerCase().includes(q))
            : members;
    }, [members, search]);

    const pick = (id: number | null) => {
        onAssign(id);
        setOpen(false);
    };

    return (
        <Popover
            open={open}
            onOpenChange={(o) => {
                setOpen(o);
                if (o) setSearch('');
            }}
        >
            <PopoverTrigger asChild>
                <button
                    type="button"
                    className="flex h-7 items-center gap-1.5 rounded-lg border border-emerald-500/40 bg-emerald-50 px-2.5 font-mono text-[11px] font-medium text-emerald-700 transition-colors hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20"
                >
                    <UserPlus className="h-3.5 w-3.5" />
                    Set creator
                    <ChevronDown className="h-3 w-3 opacity-60" />
                </button>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-72 p-0">
                <div className="flex items-center gap-2 border-b border-black/8 px-3 dark:border-white/8">
                    <Search className="h-3.5 w-3.5 shrink-0 text-gray-400" />
                    <input
                        autoFocus
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search people..."
                        className="h-10 w-full bg-transparent font-mono text-[12px] text-gray-800 outline-none placeholder:text-gray-400 dark:text-gray-100 dark:placeholder:text-gray-600"
                    />
                </div>
                <div className="max-h-[280px] overflow-y-auto p-1.5">
                    {filtered.length === 0 ? (
                        <p className="px-2 py-4 text-center font-mono text-[12px] text-gray-400 dark:text-gray-600">
                            No people found.
                        </p>
                    ) : (
                        filtered.map((m) => (
                            <button
                                key={m.id}
                                type="button"
                                onClick={() => pick(m.id)}
                                className="flex w-full items-center gap-2.5 rounded-lg px-2 py-1.5 text-left font-mono text-[13px] text-gray-800 transition-colors hover:bg-stone-100 dark:text-gray-100 dark:hover:bg-white/5"
                            >
                                {m.name}
                            </button>
                        ))
                    )}
                </div>
                <div className="border-t border-black/8 p-1.5 dark:border-white/8">
                    <button
                        type="button"
                        onClick={() => pick(null)}
                        className="flex h-9 w-full items-center justify-center rounded-lg border border-black/8 px-3 font-mono text-[12px] font-medium text-rose-600 transition-colors hover:bg-rose-50 dark:border-white/8 dark:text-rose-400 dark:hover:bg-rose-500/10"
                    >
                        Clear creator
                    </button>
                </div>
            </PopoverContent>
        </Popover>
    );
}

/* ───────────────── Ads-in-group modal ───────────────── */

interface GroupTarget {
    groupBy: GroupBy;
    group: string; // entity id, or the name when groupBy === 'ad_name'
    label: string;
}

/**
 * The metric table itself — column presets, the columns menu, and the
 * paginated DataTable. Used by both the main page and the in-group modal,
 * which feed it rows from the shared data() API. Key it by groupBy so the
 * per-dimension column presets re-init when the dimension changes.
 */
function GridTable({
    groupBy,
    groupLabel,
    rows,
    loading,
    sort,
    onFetch,
    onSelectAd,
    onOpenGroup,
    onOpenTimeline,
    // Creator tagging (only wired for the `ad` grouping + the ads-in-group modal).
    showCreator = false,
    members = [],
    canEditCreator = false,
    creatorSaving = {},
    onAssignCreator,
    // Bulk selection.
    selectedIds,
    onToggleRow,
    onToggleAll,
    onClearSelection,
    onBulkAssignCreator,
}: {
    groupBy: GroupBy;
    groupLabel: string;
    rows: PaginatedData<Row> | null;
    loading: boolean;
    sort: string | null;
    onFetch: (params?: { [key: string]: string | number | null }) => void;
    onSelectAd: (ad: Row) => void;
    onOpenGroup?: (row: Row) => void;
    onOpenTimeline?: (row: Row) => void;
    showCreator?: boolean;
    members?: OwnerOption[];
    canEditCreator?: boolean;
    creatorSaving?: Record<string, boolean>;
    onAssignCreator?: (row: Row, creatorId: number | null) => void;
    selectedIds?: Set<string>;
    onToggleRow?: (id: string) => void;
    onToggleAll?: (ids: string[], checked: boolean) => void;
    onClearSelection?: () => void;
    onBulkAssignCreator?: (creatorId: number | null) => void;
}) {
    const showThumbnail = groupBy === 'ad';
    const showStatus = HAS_STATUS[groupBy];
    const showAdsCount = groupBy !== 'ad';
    // Budget lives on the campaign / ad-set entity (daily or lifetime), so it's
    // only meaningful — and only sent by the server — for those two dimensions.
    const showBudget = groupBy === 'campaign' || groupBy === 'ad_set';
    // Selection is only offered alongside creator tagging.
    const selectable = showCreator && !!onToggleRow;

    const pageIds = rows?.data.map((r) => r.id) ?? [];
    const selectedCount = pageIds.filter((id) => selectedIds?.has(id)).length;
    const allSelected = pageIds.length > 0 && selectedCount === pageIds.length;

    const COLUMN_OPTIONS = [
        { id: 'name', label: groupLabel, category: 'General', required: true },
        ...(showStatus
            ? [{ id: 'status', label: 'Status', category: 'General' }]
            : []),
        ...(showBudget
            ? [{ id: 'budget', label: 'Budget', category: 'General' }]
            : []),
        ...(showCreator
            ? [{ id: 'creator', label: 'Creator', category: 'General' }]
            : []),
        ...INSIGHTS_OPTIONS,
    ];
    const {
        visibility: columnVisibility,
        setVisibility: setColumnVisibility,
        columnOrder,
        setColumnOrder,
        presets,
        savePreset,
        deletePreset,
        loadPreset,
        resetToDefault,
    } = useColumnPresets(
        `meta-ads-cols:${groupBy}`,
        Object.fromEntries(
            COLUMN_OPTIONS.map((o) => [o.id, !o.hiddenByDefault]),
        ),
        COLUMN_OPTIONS.map((o) => o.id),
    );

    const columns: ColumnDef<Row>[] = [
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) =>
                selectable ? (
                    <div className="flex items-center gap-2.5">
                        <input
                            type="checkbox"
                            aria-label="Select all ads on this page"
                            checked={allSelected}
                            ref={(el) => {
                                if (el)
                                    el.indeterminate =
                                        selectedCount > 0 && !allSelected;
                            }}
                            onChange={(e) =>
                                onToggleAll?.(pageIds, e.target.checked)
                            }
                            onClick={(e) => e.stopPropagation()}
                            className="h-3.5 w-3.5 shrink-0 rounded border-gray-300 accent-emerald-500"
                        />
                        <SortableHeader column={column} title={groupLabel} />
                    </div>
                ) : (
                    <SortableHeader column={column} title={groupLabel} />
                ),
            cell: ({ row }) => (
                <div className="flex items-start gap-3">
                    {selectable && (
                        <input
                            type="checkbox"
                            aria-label="Select ad"
                            checked={selectedIds?.has(row.original.id) ?? false}
                            onChange={() => onToggleRow?.(row.original.id)}
                            onClick={(e) => e.stopPropagation()}
                            className="mt-0.5 h-3.5 w-3.5 shrink-0 rounded border-gray-300 accent-emerald-500"
                        />
                    )}
                    {showThumbnail && (
                        <button
                            type="button"
                            onClick={(e) => {
                                e.stopPropagation();
                                onSelectAd(row.original);
                            }}
                            title="View creative"
                            className="group relative h-10 w-10 shrink-0 overflow-hidden rounded ring-emerald-500/40 transition-shadow hover:ring-2"
                        >
                            {row.original.thumbnail_url ||
                            row.original.image_url ? (
                                <img
                                    src={
                                        row.original.thumbnail_url ??
                                        row.original.image_url ??
                                        ''
                                    }
                                    alt=""
                                    className="h-10 w-10 object-cover"
                                />
                            ) : (
                                <div className="flex h-10 w-10 items-center justify-center bg-stone-100 text-gray-400 dark:bg-zinc-800 dark:text-gray-500">
                                    <ImageIcon className="h-4 w-4" />
                                </div>
                            )}
                            {isVideoCreative(row.original) && (
                                <span className="absolute inset-0 flex items-center justify-center bg-black/35">
                                    <Play className="h-3.5 w-3.5 fill-white text-white" />
                                </span>
                            )}
                        </button>
                    )}
                    <div className="min-w-0 flex-1">
                        <span className="block truncate text-[12px] font-medium text-gray-700 dark:text-gray-300">
                            {row.original.name ?? '—'}
                        </span>
                        {showAdsCount && row.original.ads_count != null && (
                            <span className="block font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                {row.original.ads_count}{' '}
                                {row.original.ads_count === 1 ? 'ad' : 'ads'}
                            </span>
                        )}
                    </div>
                    {onOpenTimeline && (
                        <button
                            type="button"
                            // The row itself opens the creative / ads drill-down,
                            // so the chart has to claim its own click.
                            onClick={(e) => {
                                e.stopPropagation();
                                onOpenTimeline(row.original);
                            }}
                            title="View timeline"
                            aria-label={`View the timeline for ${row.original.name ?? 'this row'}`}
                            className="mt-0.5 shrink-0 rounded-md p-1 text-gray-300 transition-colors hover:bg-stone-100 hover:text-emerald-600 dark:text-gray-600 dark:hover:bg-zinc-700 dark:hover:text-emerald-400"
                        >
                            <ChartColumn className="h-3.5 w-3.5" />
                        </button>
                    )}
                </div>
            ),
        },
        ...(showStatus
            ? [
                  {
                      id: 'status',
                      enableSorting: false,
                      header: () => (
                          <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                              Status
                          </span>
                      ),
                      cell: ({ row }: { row: { original: Row } }) => (
                          <StatusLabel
                              status={
                                  row.original.effective_status ??
                                  row.original.status ??
                                  null
                              }
                          />
                      ),
                  } as ColumnDef<Row>,
              ]
            : []),
        ...(showBudget
            ? [
                  {
                      id: 'budget',
                      enableSorting: false,
                      header: () => (
                          <span className="text-right font-mono text-[11px] text-gray-500 dark:text-gray-400">
                              Budget
                          </span>
                      ),
                      cell: ({ row }: { row: { original: Row } }) => {
                          const b = formatBudget(
                              row.original.daily_budget ?? null,
                              row.original.lifetime_budget ?? null,
                          );
                          return (
                              <div className="text-right font-mono text-[12px] text-gray-700 dark:text-gray-300">
                                  {b.value}
                                  {b.label && (
                                      <span className="block text-[10px] text-gray-400 dark:text-gray-500">
                                          {b.label}
                                      </span>
                                  )}
                              </div>
                          );
                      },
                  } as ColumnDef<Row>,
              ]
            : []),
        ...(showCreator
            ? [
                  {
                      id: 'creator',
                      enableSorting: false,
                      header: () => (
                          <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                              Creator
                          </span>
                      ),
                      cell: ({ row }: { row: { original: Row } }) => (
                          <InlineOwner
                              label="Creator"
                              owner={creatorOf(row.original)}
                              users={members}
                              canEdit={canEditCreator}
                              saving={creatorSaving[row.original.id] ?? false}
                              onAssign={(id) =>
                                  onAssignCreator?.(row.original, id)
                              }
                          />
                      ),
                  } as ColumnDef<Row>,
              ]
            : []),
        ...buildInsightsColumns<Row>(),
    ];

    const rowClick = showThumbnail
        ? (r: unknown) => onSelectAd(r as Row)
        : onOpenGroup
          ? (r: unknown) => onOpenGroup(r as Row)
          : undefined;

    return (
        <div className="relative overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
            <div className="flex items-center justify-between gap-2 border-b border-black/6 px-3 py-2.5 dark:border-white/6">
                {selectable && selectedCount > 0 ? (
                    <div className="flex items-center gap-2">
                        <span className="font-mono text-[11px] font-medium text-gray-600 dark:text-gray-300">
                            {selectedCount} selected
                        </span>
                        {canEditCreator && onBulkAssignCreator && (
                            <BulkCreatorControl
                                members={members}
                                onAssign={onBulkAssignCreator}
                            />
                        )}
                        <button
                            type="button"
                            onClick={onClearSelection}
                            className="flex h-7 items-center gap-1 rounded-lg px-2 font-mono text-[11px] text-gray-500 transition-colors hover:bg-stone-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200"
                        >
                            <X className="h-3 w-3" />
                            Clear
                        </button>
                    </div>
                ) : (
                    <span className="font-mono text-[10px] tracking-wide text-gray-400 dark:text-gray-500">
                        {(rows?.total ?? 0).toLocaleString()} {groupLabel}
                        {(rows?.total ?? 0) === 1 ? '' : 's'}
                    </span>
                )}
                <ColumnVisibilityMenu
                    options={COLUMN_OPTIONS}
                    value={columnVisibility}
                    onChange={setColumnVisibility}
                    columnOrder={columnOrder}
                    onColumnOrderChange={setColumnOrder}
                    presets={presets}
                    onSavePreset={savePreset}
                    onDeletePreset={deletePreset}
                    onLoadPreset={loadPreset}
                    onReset={resetToDefault}
                />
            </div>
            <div className="relative">
                {loading && (
                    <div className="absolute inset-0 z-10 flex items-center justify-center bg-white/60 dark:bg-zinc-900/60">
                        <Loader2 className="h-6 w-6 animate-spin text-gray-400" />
                    </div>
                )}
                <DataTable
                    columns={columns as ColumnDef<unknown>[]}
                    data={(rows?.data ?? []) as unknown[]}
                    initialSorting={toFrontendSort(sort)}
                    meta={rows ? { ...omit(rows, ['data']) } : undefined}
                    columnVisibility={columnVisibility}
                    onColumnVisibilityChange={setColumnVisibility}
                    columnOrder={columnOrder}
                    onColumnOrderChange={setColumnOrder}
                    onRowClick={rowClick}
                    onFetch={onFetch}
                />
            </div>
        </div>
    );
}

function GroupAdsModal({
    slug,
    target,
    dateRange,
    selectedAccounts,
    accountsTotal,
    members,
    canEditCreator,
    onClose,
    onSelectAd,
    onOpenTimeline,
}: {
    slug: string;
    target: GroupTarget | null;
    dateRange: { since: string; until: string };
    selectedAccounts: string[];
    accountsTotal: number;
    members: OwnerOption[];
    canEditCreator: boolean;
    onClose: () => void;
    onSelectAd: (ad: Row) => void;
    onOpenTimeline?: (row: Row) => void;
}) {
    const open = target != null;
    const [rows, setRows] = useState<PaginatedData<Row> | null>(null);
    const [loading, setLoading] = useState(false);
    const [sort, setSort] = useState<string | null>(null);
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(25);

    const {
        saving: creatorSaving,
        assign: assignCreator,
        bulkAssign: bulkAssignCreator,
    } = useCreatorTagging(slug, members, setRows);
    const { selectedIds, toggleRow, toggleAll, clear } = useRowSelection();

    // Reset paging + selection whenever a new group opens.
    useEffect(() => {
        setSort(null);
        setPage(1);
        setPerPage(25);
        clear();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [target?.groupBy, target?.group]);

    useEffect(() => {
        if (!target) {
            setRows(null);
            return;
        }
        let active = true;
        setLoading(true);
        const qs = new URLSearchParams();
        qs.set('scope_by', target.groupBy);
        qs.set('scope', target.group);
        qs.set('since', dateRange.since);
        qs.set('until', dateRange.until);
        if (selectedAccounts.length !== accountsTotal) {
            selectedAccounts.forEach((a) => qs.append('accounts[]', a));
        }
        if (sort) qs.set('sort', sort);
        qs.set('page', String(page));
        qs.set('per_page', String(perPage));
        fetch(`${dataUrl(slug)}?${qs.toString()}`, {
            headers: { Accept: 'application/json' },
        })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then((d: { rows: PaginatedData<Row> }) => {
                if (active) {
                    setRows(d.rows);
                    setLoading(false);
                }
            })
            .catch(() => active && setLoading(false));
        return () => {
            active = false;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        target?.groupBy,
        target?.group,
        dateRange.since,
        dateRange.until,
        sort,
        page,
        perPage,
        selectedAccounts,
        accountsTotal,
        slug,
    ]);

    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="relative flex h-[90vh] w-[95vw] max-w-[95vw] flex-col gap-0 overflow-hidden p-0 sm:max-w-[95vw]">
                <DialogTitle className="border-b border-black/6 px-5 py-3.5 text-[14px] font-semibold tracking-tight text-gray-800 dark:border-white/6 dark:text-gray-100">
                    {target?.label || 'Ads'}
                    <span className="ml-1.5 font-mono text-[11px] font-normal text-gray-400">
                        · ads
                    </span>
                </DialogTitle>
                <div className="flex-1 overflow-auto p-4">
                    <GridTable
                        groupBy="ad"
                        groupLabel="Ad"
                        rows={rows}
                        loading={loading}
                        sort={sort}
                        onFetch={(p) => {
                            if (p?.sort !== undefined)
                                setSort((p.sort as string | null) ?? null);
                            if (p?.page !== undefined)
                                setPage(Number(p.page) || 1);
                            if (p?.per_page !== undefined)
                                setPerPage(Number(p.per_page) || 25);
                        }}
                        onSelectAd={onSelectAd}
                        onOpenTimeline={onOpenTimeline}
                        showCreator
                        members={members}
                        canEditCreator={canEditCreator}
                        creatorSaving={creatorSaving}
                        onAssignCreator={assignCreator}
                        selectedIds={selectedIds}
                        onToggleRow={toggleRow}
                        onToggleAll={toggleAll}
                        onClearSelection={clear}
                        onBulkAssignCreator={(id) =>
                            bulkAssignCreator([...selectedIds], id, clear)
                        }
                    />
                </div>
            </DialogContent>
        </Dialog>
    );
}

/* ───────────────────── Creative detail drawer ───────────────── */

interface AdDetail {
    dimensions: {
        ad_status: string | null;
        optimization_goal: string | null;
        ad_name: string | null;
        ad_id: string;
        adset_name: string | null;
        campaign_name: string | null;
        account_name: string | null;
        ad_type: string;
        media_type: 'video' | 'image' | null;
        call_to_action: string | null;
    };
    preview: { src: string | null };
}

function DimRow({
    label,
    value,
    mono,
}: {
    label: string;
    value: ReactNode;
    mono?: boolean;
}) {
    return (
        <div className="grid grid-cols-[120px_1fr] gap-2 py-1.5">
            <dt className="text-[11px] text-gray-400 dark:text-gray-500">
                {label}
            </dt>
            <dd
                className={clsx(
                    'text-[12px] text-gray-700 dark:text-gray-200',
                    mono && 'font-mono text-[11px]',
                )}
            >
                {value ?? <span className="text-gray-300">—</span>}
            </dd>
        </div>
    );
}

function CreativeDetailDrawer({
    slug,
    ad,
    onClose,
}: {
    slug: string;
    ad: Row | null;
    onClose: () => void;
}) {
    const [detail, setDetail] = useState<AdDetail | null>(null);
    const [loading, setLoading] = useState(false);
    const [src, setSrc] = useState<string | null>(null);
    const [iframeLoaded, setIframeLoaded] = useState(false);

    useEffect(() => {
        if (!ad) return;
        let active = true;
        setLoading(true);
        setDetail(null);
        setSrc(null);
        setIframeLoaded(false);
        fetch(adDetailUrl(slug, ad.id), {
            headers: { Accept: 'application/json' },
        })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then((d: AdDetail) => {
                if (!active) return;
                setDetail(d);
                setSrc(d.preview?.src ?? null);
                setLoading(false);
            })
            .catch(() => active && setLoading(false));
        return () => {
            active = false;
        };
    }, [ad?.id, slug]); // eslint-disable-line react-hooks/exhaustive-deps

    const dim = detail?.dimensions;
    // Prefer the detail's media_type once loaded, else the row's signal.
    const isImage = dim?.media_type
        ? dim.media_type !== 'video'
        : ad
          ? !isVideoCreative(ad)
          : false;
    // Spinner while the detail request is in flight OR the iframe is still painting.
    const showSpinner = loading || (!!src && !iframeLoaded);

    return (
        <Sheet open={!!ad} onOpenChange={(o) => !o && onClose()}>
            <SheetContent
                side="right"
                className="w-full gap-0 overflow-y-auto p-0 sm:max-w-md"
            >
                <SheetHeader className="border-b border-black/6 px-4 py-3 dark:border-white/6">
                    <SheetTitle className="truncate pr-6 text-[14px] tracking-tight text-gray-800 dark:text-gray-100">
                        {ad?.name ?? 'Creative'}
                    </SheetTitle>
                </SheetHeader>

                <div className="p-4">
                    {/* Phone-style frame around the in-feed ad preview. */}
                    <div className="flex justify-center rounded-lg bg-stone-100 py-6 dark:bg-zinc-950">
                        <div className="w-[336px] overflow-hidden rounded-[2rem] border-[8px] border-zinc-800 bg-black shadow-xl dark:border-zinc-700">
                            <div className="flex h-6 items-center justify-center bg-zinc-900">
                                <div className="h-1 w-10 rounded-full bg-zinc-600" />
                            </div>
                            <div className="relative h-[560px] bg-white dark:bg-zinc-900">
                                {src && (
                                    <iframe
                                        key={src}
                                        title="Creative preview"
                                        src={src}
                                        onLoad={() => setIframeLoaded(true)}
                                        className={clsx(
                                            'h-full w-full border-0',
                                            !iframeLoaded && 'invisible',
                                        )}
                                        allowFullScreen
                                    />
                                )}
                                {showSpinner ? (
                                    <div className="absolute inset-0 flex items-center justify-center">
                                        <Loader2 className="h-6 w-6 animate-spin text-gray-400" />
                                    </div>
                                ) : (
                                    !src && (
                                        <div className="absolute inset-0 flex items-center justify-center px-6">
                                            <p className="text-center font-mono text-[12px] text-gray-400 dark:text-gray-500">
                                                Preview unavailable for this ad.
                                            </p>
                                        </div>
                                    )
                                )}
                            </div>
                        </div>
                    </div>

                    {isImage && ad?.image_url && (
                        <a
                            href={ad.image_url}
                            target="_blank"
                            rel="noreferrer"
                            className="mt-3 flex h-8 w-fit items-center gap-1.5 rounded-lg border border-black/6 px-3 font-mono text-[11px] text-gray-600 hover:border-black/12 hover:bg-stone-50 dark:border-white/6 dark:text-gray-300 dark:hover:bg-zinc-800"
                        >
                            <Download className="h-3.5 w-3.5" />
                            Download
                        </a>
                    )}

                    <p className="mt-5 mb-1 text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                        Dimensions
                    </p>
                    <dl className="divide-y divide-black/4 dark:divide-white/4">
                        <DimRow label="Ad status" value={dim?.ad_status} />
                        <DimRow
                            label="Optimization goal"
                            value={dim?.optimization_goal}
                            mono
                        />
                        <DimRow
                            label="Ad"
                            value={
                                dim && (
                                    <span>
                                        {dim.ad_name}
                                        <span className="block font-mono text-[10px] text-gray-400">
                                            ID {dim.ad_id}
                                        </span>
                                    </span>
                                )
                            }
                        />
                        <DimRow label="Adset" value={dim?.adset_name} />
                        <DimRow label="Campaign" value={dim?.campaign_name} />
                        <DimRow label="Account" value={dim?.account_name} />
                        <DimRow label="Ad type" value={dim?.ad_type} />
                        <DimRow
                            label="Call to action"
                            value={dim?.call_to_action}
                            mono
                        />
                    </dl>
                </div>
            </SheetContent>
        </Sheet>
    );
}

/* ───────────────────── Account multi-picker ─────────────────── */

interface AccountMultiPickerProps {
    accounts: AccountOption[];
    selected: string[];
    onChange: (next: string[]) => void;
}

function AccountMultiPicker({
    accounts,
    selected,
    onChange,
}: AccountMultiPickerProps) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    useEffect(() => {
        if (!open) setSearch('');
    }, [open]);

    const allSelected = selected.length === accounts.length;
    const q = search.toLowerCase();
    const filtered = accounts.filter(
        (a) => !q || a.name.toLowerCase().includes(q),
    );

    const toggle = (id: string) => {
        const set = new Set(selected);
        if (set.has(id)) set.delete(id);
        else set.add(id);
        onChange(accounts.filter((a) => set.has(a.id)).map((a) => a.id));
    };

    const toggleAll = () =>
        onChange(allSelected ? [] : accounts.map((a) => a.id));

    const label = allSelected
        ? 'All accounts'
        : selected.length === 0
          ? 'No accounts'
          : `${selected.length} of ${accounts.length} accounts`;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    className="h-9 gap-1.5 font-mono! text-[12px]!"
                >
                    <LayoutGrid className="h-3.5 w-3.5" />
                    {label}
                    <ChevronDown className="h-3 w-3 text-gray-400" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                className="w-72 p-0 font-mono text-[11px]"
            >
                <div className="border-b border-black/6 p-2 dark:border-white/6">
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 h-3 w-3 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            type="text"
                            placeholder="Search accounts..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            autoFocus
                            className="h-7 w-full rounded-md border border-black/6 bg-stone-50 pr-2 pl-7 font-mono text-[11px] outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-200"
                        />
                    </div>
                </div>

                <label className="flex cursor-pointer items-center gap-2 border-b border-black/6 px-3 py-2 hover:bg-stone-50 dark:border-white/6 dark:hover:bg-zinc-800/50">
                    <input
                        type="checkbox"
                        checked={allSelected}
                        onChange={toggleAll}
                        className="h-3.5 w-3.5 rounded border-gray-300 accent-emerald-500"
                    />
                    <span className="font-medium text-gray-700 dark:text-gray-300">
                        Select all
                    </span>
                </label>

                <div className="max-h-64 overflow-y-auto py-1">
                    {filtered.length === 0 && (
                        <p className="py-4 text-center text-gray-400 dark:text-gray-500">
                            No accounts match.
                        </p>
                    )}
                    {filtered.map((a) => {
                        const checked = selected.includes(a.id);
                        return (
                            <button
                                key={a.id}
                                type="button"
                                onClick={() => toggle(a.id)}
                                className="flex w-full items-center justify-between gap-2 px-3 py-1.5 text-left text-gray-700 transition-colors hover:bg-stone-100 dark:text-gray-300 dark:hover:bg-zinc-700"
                            >
                                <span className="truncate">{a.name}</span>
                                {checked && (
                                    <Check className="h-3 w-3 shrink-0 text-emerald-500" />
                                )}
                            </button>
                        );
                    })}
                </div>
            </PopoverContent>
        </Popover>
    );
}

/* ───────────────────── Group-by selector ────────────────────── */

function GroupBySelect({
    value,
    onChange,
}: {
    value: GroupBy;
    onChange: (next: GroupBy) => void;
}) {
    const [open, setOpen] = useState(false);
    const active =
        GROUP_BY_OPTIONS.find((o) => o.value === value) ?? GROUP_BY_OPTIONS[0];

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    className="h-9 gap-1.5 font-mono! text-[12px]!"
                >
                    <span className="text-gray-400 dark:text-gray-500">
                        Group by
                    </span>
                    <active.icon className="h-3.5 w-3.5 text-gray-400 dark:text-gray-500" />
                    <span className="font-medium">{active.label}</span>
                    <ChevronDown className="h-3 w-3 text-gray-400" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                className="w-44 p-1 font-mono text-[11px]"
            >
                {GROUP_BY_OPTIONS.map((o) => (
                    <button
                        key={o.value}
                        type="button"
                        onClick={() => {
                            onChange(o.value);
                            setOpen(false);
                        }}
                        className="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-gray-700 transition-colors hover:bg-stone-100 dark:text-gray-300 dark:hover:bg-zinc-700"
                    >
                        <o.icon className="h-3.5 w-3.5 shrink-0 text-gray-400 dark:text-gray-500" />
                        <span className="flex-1">{o.label}</span>
                        {o.value === value && (
                            <Check className="h-3 w-3 shrink-0 text-emerald-500" />
                        )}
                    </button>
                ))}
            </PopoverContent>
        </Popover>
    );
}

export default function MetaAdsManager({
    workspace,
    accounts,
    members,
    selectedAccounts,
    objectives = [],
    dateRange,
    query,
}: Props) {
    const canEditCreator = usePermission(PERMISSIONS.ManageMetaAdsAccounts);

    const [groupBy, setGroupBy] = useState<GroupBy>(
        query?.groupBy ?? 'ad_name',
    );
    const [selected, setSelected] = useState<string[]>(selectedAccounts);
    const [range, setRange] = useState(dateRange);
    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    // Metric and date filters share one list and one dropdown — they differ
    // only in which query param carries them to the server.
    const [filters, setFilters] = useState<GridFilter[]>(() =>
        deserializeGridFilters(query?.metricFilters, query?.dateFilters),
    );
    // Creator filter: '' (all), 'unassigned', or a member id as string.
    const [creatorFilter, setCreatorFilter] = useState<string>('');
    const [sort, setSort] = useState<string | null>(query?.sort ?? null);
    const [page, setPage] = useState<number>(Number(query?.page ?? 1) || 1);
    const [perPage, setPerPage] = useState<number>(
        Number(query?.perPage ?? 25) || 25,
    );

    const [rows, setRows] = useState<PaginatedData<Row> | null>(null);
    const [loading, setLoading] = useState(true);
    const [previewAd, setPreviewAd] = useState<Row | null>(null);
    const [groupTarget, setGroupTarget] = useState<GroupTarget | null>(null);
    const [timelineTarget, setTimelineTarget] = useState<TimelineTarget | null>(
        null,
    );

    const {
        saving: creatorSaving,
        assign: assignCreator,
        bulkAssign: bulkAssignCreator,
    } = useCreatorTagging(workspace.slug, members, setRows);
    const { selectedIds, toggleRow, toggleAll, clear } = useRowSelection();

    // The creator column, filter, and selection are only meaningful per-ad.
    const showCreator = groupBy === 'ad';

    const groupLabel =
        GROUP_BY_OPTIONS.find((o) => o.value === groupBy)?.label ?? 'Ad Name';

    // Debounce only the search box; every other control fetches immediately.
    const [debouncedSearch, setDebouncedSearch] = useState(searchValue);
    useEffect(() => {
        const t = setTimeout(() => setDebouncedSearch(searchValue), 350);
        return () => clearTimeout(t);
    }, [searchValue]);

    // Single source of truth: build the query, push it to the URL (so refresh /
    // shared links restore the view), and fetch the grid rows from the API.
    useEffect(() => {
        const qs = new URLSearchParams();
        qs.set('group_by', groupBy);
        if (selected.length !== accounts.length) {
            selected.forEach((a) => qs.append('accounts[]', a));
        }
        qs.set('since', range.since);
        qs.set('until', range.until);
        if (sort) qs.set('sort', sort);
        if (debouncedSearch) qs.set('filter[search]', debouncedSearch);
        const mf = serializeMetricFilters(filters);
        if (mf) qs.set('metric_filters', mf);
        const df = serializeDateFilters(filters);
        if (df) qs.set('date_filters', df);
        // Creator filter is ad-level only.
        if (groupBy === 'ad' && creatorFilter) {
            qs.set('creator_id', creatorFilter);
        }
        qs.set('page', String(page));
        qs.set('per_page', String(perPage));

        window.history.replaceState(
            null,
            '',
            `${window.location.pathname}?${qs.toString()}`,
        );

        let active = true;
        setLoading(true);
        fetch(`${dataUrl(workspace.slug)}?${qs.toString()}`, {
            headers: { Accept: 'application/json' },
        })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then((d: { rows: PaginatedData<Row> }) => {
                if (active) {
                    setRows(d.rows);
                    setLoading(false);
                }
            })
            .catch(() => active && setLoading(false));
        return () => {
            active = false;
        };
    }, [
        groupBy,
        selected,
        range.since,
        range.until,
        sort,
        page,
        perPage,
        filters,
        creatorFilter,
        debouncedSearch,
        accounts.length,
        workspace.slug,
    ]);

    const onAccounts = (ids: string[]) => {
        setSelected(ids);
        setPage(1);
    };
    const onGroupBy = (next: GroupBy) => {
        setGroupBy(next);
        setPage(1);
        clear();
        // The creator filter only applies to the ad grouping.
        if (next !== 'ad') setCreatorFilter('');
    };
    const onCreatorFilter = (value: string) => {
        setCreatorFilter(value === 'all' ? '' : value);
        setPage(1);
        clear();
    };
    const onDateRange = (since: string, until: string) => {
        setRange({ since, until });
        setPage(1);
    };
    const onFilters = (next: GridFilter[]) => {
        setFilters(next);
        setPage(1);
    };
    const onSearch = (v: string) => {
        setSearchValue(v);
        setPage(1);
    };
    const onTableFetch = (params?: {
        [key: string]: string | number | null;
    }) => {
        if (params?.sort !== undefined)
            setSort((params.sort as string | null) ?? null);
        if (params?.page !== undefined) setPage(Number(params.page) || 1);
        if (params?.per_page !== undefined)
            setPerPage(Number(params.per_page) || 25);
    };

    return (
        <AppLayout>
            <Head title="Meta Ads · Ads Manager" />

            <div className="w-full p-4 md:p-6">
                <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="mb-1 font-mono text-[10px] font-medium tracking-[0.12em] text-emerald-600 uppercase dark:text-emerald-400">
                            Meta Ads
                        </p>
                        <h1 className="text-[26px] font-semibold tracking-tight text-gray-800 dark:text-gray-100">
                            Ads Manager
                        </h1>
                        <p className="mt-1 font-mono text-[11px] text-gray-400 dark:text-gray-500">
                            {formatDate(new Date(range.since), 'MMM d')} –{' '}
                            {formatDate(new Date(range.until), 'MMM d, yyyy')}
                        </p>
                    </div>

                    <DatePicker
                        id="meta-ads-date-range"
                        mode="range"
                        onChange={(dates) => {
                            if (dates.length === 2) {
                                onDateRange(
                                    moment(dates[0]).format('YYYY-MM-DD'),
                                    moment(dates[1]).format('YYYY-MM-DD'),
                                );
                            } else if (dates.length === 0) {
                                onDateRange(dateRange.since, dateRange.until);
                            }
                        }}
                        defaultDate={
                            [range.since, range.until] as never as DateOption
                        }
                    />
                </div>

                <div className="mb-4 flex flex-wrap items-center gap-2 rounded-2xl border border-black/6 bg-white/70 p-2 shadow-sm backdrop-blur-sm dark:border-white/6 dark:bg-zinc-900/70">
                    <AccountMultiPicker
                        accounts={accounts}
                        selected={selected}
                        onChange={onAccounts}
                    />
                    <GroupBySelect value={groupBy} onChange={onGroupBy} />
                    <div className="relative min-w-[180px] flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            type="text"
                            placeholder={`Search ${groupLabel.toLowerCase()}...`}
                            value={searchValue}
                            onChange={(e) => onSearch(e.target.value)}
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400 dark:focus:bg-zinc-900"
                        />
                    </div>
                    <div className="ml-auto flex items-center gap-2">
                        {showCreator && (
                            <Select
                                value={creatorFilter || 'all'}
                                onValueChange={onCreatorFilter}
                            >
                                <SelectTrigger className="h-9 w-[180px] font-mono text-[11px]">
                                    <SelectValue placeholder="All creators" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All creators
                                    </SelectItem>
                                    <SelectItem value="unassigned">
                                        Unassigned
                                    </SelectItem>
                                    {members.map((m) => (
                                        <SelectItem
                                            key={m.id}
                                            value={String(m.id)}
                                        >
                                            {m.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                        <InsightFilterBuilder
                            filters={filters}
                            onChange={onFilters}
                            groupBy={groupBy}
                            objectives={objectives}
                        />
                    </div>
                </div>

                <GridTable
                    key={groupBy}
                    groupBy={groupBy}
                    groupLabel={groupLabel}
                    rows={rows}
                    loading={loading}
                    sort={sort}
                    onFetch={onTableFetch}
                    onSelectAd={setPreviewAd}
                    onOpenTimeline={(row) =>
                        setTimelineTarget({
                            scopeBy: groupBy,
                            // ad_name groups by the name itself, so that's what
                            // identifies the group server-side.
                            scope:
                                groupBy === 'ad_name'
                                    ? (row.name ?? '')
                                    : String(row.id),
                            label: row.name ?? '—',
                        })
                    }
                    onOpenGroup={(row) =>
                        setGroupTarget({
                            groupBy,
                            group:
                                groupBy === 'ad_name'
                                    ? (row.name ?? '')
                                    : String(row.id),
                            label: row.name ?? '',
                        })
                    }
                    showCreator={showCreator}
                    members={members}
                    canEditCreator={canEditCreator}
                    creatorSaving={creatorSaving}
                    onAssignCreator={assignCreator}
                    selectedIds={selectedIds}
                    onToggleRow={toggleRow}
                    onToggleAll={toggleAll}
                    onClearSelection={clear}
                    onBulkAssignCreator={(id) =>
                        bulkAssignCreator([...selectedIds], id, clear)
                    }
                />
            </div>

            <CreativeDetailDrawer
                slug={workspace.slug}
                ad={previewAd}
                onClose={() => setPreviewAd(null)}
            />

            <RowTimelineModal
                slug={workspace.slug}
                target={timelineTarget}
                dateRange={range}
                selectedAccounts={selected}
                accountsTotal={accounts.length}
                groupLabel={groupLabel}
                onClose={() => setTimelineTarget(null)}
            />

            <GroupAdsModal
                slug={workspace.slug}
                target={groupTarget}
                dateRange={range}
                selectedAccounts={selected}
                accountsTotal={accounts.length}
                members={members}
                canEditCreator={canEditCreator}
                onClose={() => setGroupTarget(null)}
                onSelectAd={(ad) => setPreviewAd(ad)}
                onOpenTimeline={(row) =>
                    setTimelineTarget({
                        scopeBy: 'ad',
                        scope: String(row.id),
                        label: row.name ?? '—',
                    })
                }
            />
        </AppLayout>
    );
}
