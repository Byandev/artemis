import type { RtsQueryParams } from '@/components/rts/rts-shared';
import {
    RefreshButton,
    RtsEmptyState,
    buildBaseParams,
} from '@/components/rts/rts-shared';
import { Loader2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import HeatMapCanvas from './HeatMapCanvas';
import {
    CSR_GROUPS,
    HEAT_MODES,
    NO_DATA_COLOR,
    buildScale,
    peso,
    type Bucket,
    type CsrGroup,
    type CsrGroupKey,
    type HeatMode,
} from './color-scale';
import { loadPhGeoJson, type PhGeoJson } from './ph-geo';
import type { HeatGroupBy, HeatMapResponse, HeatRow } from './types';
import { useIsDark } from './use-is-dark';

interface Props {
    workspaceSlug: string;
    queryParams: RtsQueryParams;
}

export default function RtsHeatMap({ workspaceSlug, queryParams }: Props) {
    const isDark = useIsDark();

    const [groupBy, setGroupBy] = useState<HeatGroupBy>('province');
    const [selectedMode, setSelectedMode] = useState<HeatMode>('rts-orders');

    // The CSR groups rank each province against the others, which needs a field of
    // them — across five island groups "high volume" means nothing. Region view
    // therefore always shades on rate, and the toggle says why.
    const mode: HeatMode = groupBy === 'region' ? 'rts' : selectedMode;

    const [geoData, setGeoData] = useState<PhGeoJson | null>(null);
    const [geoError, setGeoError] = useState<string | null>(null);
    const [response, setResponse] = useState<HeatMapResponse | null>(null);
    const [loading, setLoading] = useState(true);

    const [hover, setHover] = useState<{
        row: HeatRow;
        x: number;
        y: number;
    } | null>(null);

    useEffect(() => {
        let active = true;

        loadPhGeoJson()
            .then((data) => active && setGeoData(data))
            .catch(
                (error: Error) =>
                    active &&
                    setGeoError(error.message || 'Map failed to load'),
            );

        return () => {
            active = false;
        };
    }, []);

    const paramsKey = JSON.stringify(queryParams);

    const fetchData = useCallback(() => {
        setLoading(true);

        const params = buildBaseParams(queryParams);
        params.append('group_by', groupBy);

        return fetch(
            `/workspaces/${workspaceSlug}/rts/heat-map/data?${params}`,
            { credentials: 'same-origin' },
        )
            .then((res) => (res.ok ? res.json() : null))
            .then((data: HeatMapResponse | null) => setResponse(data))
            .catch(() => setResponse(null))
            .finally(() => setLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [workspaceSlug, groupBy, paramsKey]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    const rows = useMemo(() => response?.rows ?? [], [response]);

    const modeConfig = useMemo(
        () => HEAT_MODES.find((m) => m.key === mode) ?? HEAT_MODES[0],
        [mode],
    );

    const scale = useMemo(
        () =>
            buildScale(
                rows.map((row) => row.orders),
                mode,
                isDark,
            ),
        [rows, mode, isDark],
    );

    // One entry per polygon: a province contributes its own, a region contributes
    // every province inside it, all pointing at the same row.
    const rowsByGid = useMemo(() => {
        const map = new Map<string, HeatRow>();
        rows.forEach((row) => row.gids.forEach((gid) => map.set(gid, row)));
        return map;
    }, [rows]);

    const colorFor = useCallback(
        (row: HeatRow) => scale.colorFor(row.rts_rate_percentage, row.orders),
        [scale],
    );

    // Plain RTS ranks on the rate alone; the CSR view lists every area under its
    // group instead, so an agent can look up where an order is going.
    const ranked = useMemo(
        () =>
            [...rows]
                .sort((a, b) => b.rts_rate_percentage - a.rts_rate_percentage)
                .slice(0, 12),
        [rows],
    );

    const grouped = useMemo(() => {
        const buckets = new Map<CsrGroupKey, HeatRow[]>(
            CSR_GROUPS.map((group) => [group.key, [] as HeatRow[]]),
        );

        rows.forEach((row) => {
            const key = scale.groupFor(row.rts_rate_percentage, row.orders);
            if (key) buckets.get(key)?.push(row);
        });

        buckets.forEach((list) => list.sort((a, b) => b.orders - a.orders));

        return buckets;
    }, [rows, scale]);

    // Stable so HeatMapCanvas never re-renders its ~1,650 paths on hover.
    const handleEnter = useCallback(
        (row: HeatRow | null, x: number, y: number) =>
            setHover(row ? { row, x, y } : null),
        [],
    );
    const handleMove = useCallback(
        (x: number, y: number) =>
            setHover((current) => (current ? { ...current, x, y } : null)),
        [],
    );
    const handleLeave = useCallback(() => setHover(null), []);

    const nameOf = (row: {
        region: string | null;
        province_name: string | null;
    }) => (groupBy === 'region' ? row.region : row.province_name) ?? 'Unknown';

    const totals = response?.totals;
    const busy = loading || (!geoData && !geoError);

    const hoverGroup = useMemo(() => {
        if (!hover) return null;

        const key = scale.groupFor(
            hover.row.rts_rate_percentage,
            hover.row.orders,
        );

        return CSR_GROUPS.find((group) => group.key === key) ?? null;
    }, [hover, scale]);

    /**
     * A blank map means either "nothing here ever" or "nothing here yet", and
     * "no results" leaves the reader unable to tell which.
     */
    const emptyMessage = useMemo(() => {
        const available = response?.available;

        if (!available?.first || !available.last) {
            return 'This workspace has no delivered or returned orders yet';
        }

        return `No RTS data between ${day(queryParams.startDate)} and ${day(queryParams.endDate)}. This workspace has data from ${day(available.first)} to ${day(available.last)}.`;
    }, [response, queryParams.startDate, queryParams.endDate]);

    const rateTone = (rate: number) =>
        rate > 22
            ? 'text-red-600 dark:text-red-400'
            : rate >= 15
              ? 'text-amber-600 dark:text-amber-400'
              : 'text-emerald-600 dark:text-emerald-400';

    return (
        <section className="overflow-hidden rounded-2xl bg-white ring-1 ring-black/[0.06] dark:bg-zinc-900 dark:ring-white/[0.07]">
            {/* Header ------------------------------------------------------ */}
            <header className="flex flex-col gap-5 px-6 pt-5 pb-5">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0">
                        <h2 className="text-[15px] font-semibold tracking-tight text-gray-900 dark:text-gray-50">
                            RTS Heat Map
                        </h2>
                        <p className="mt-1 text-[12px] text-gray-500 dark:text-gray-400">
                            {modeConfig.short} by{' '}
                            {groupBy === 'region' ? 'island group' : 'province'}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Segmented
                            options={[
                                { value: 'region', label: 'Region' },
                                { value: 'province', label: 'Province' },
                            ]}
                            value={groupBy}
                            onChange={(v) => setGroupBy(v as HeatGroupBy)}
                        />
                        <Segmented
                            options={HEAT_MODES.map((m) => ({
                                value: m.key,
                                label: m.label,
                            }))}
                            value={mode}
                            onChange={(v) => setSelectedMode(v as HeatMode)}
                            disabled={groupBy === 'region'}
                            disabledHint="CSR groups compare provinces against each other — switch to Province to use them"
                        />
                        <RefreshButton onClick={fetchData} loading={loading} />
                    </div>
                </div>

                {/* Headline figures. The rate leads because it is the one number
                    that is comparable between a big province and a small one. */}
                <div className="grid grid-cols-2 gap-px overflow-hidden rounded-xl bg-black/[0.06] sm:grid-cols-4 dark:bg-white/[0.07]">
                    <Kpi
                        label="RTS rate"
                        value={
                            totals
                                ? `${totals.rts_rate_percentage.toFixed(1)}%`
                                : '—'
                        }
                        tone={
                            totals
                                ? rateTone(totals.rts_rate_percentage)
                                : undefined
                        }
                        sub="of value shipped"
                    />
                    <Kpi
                        label="Returned"
                        value={
                            totals
                                ? totals.returning_count.toLocaleString()
                                : '—'
                        }
                        sub={totals ? peso(totals.returning_amount) : undefined}
                    />
                    <Kpi
                        label="Orders"
                        value={totals ? totals.orders.toLocaleString() : '—'}
                        sub={totals ? peso(totals.sales) : undefined}
                    />
                    <Kpi
                        label={groupBy === 'region' ? 'Regions' : 'Provinces'}
                        value={rows.length ? rows.length.toLocaleString() : '—'}
                        sub={
                            totals?.unmapped_areas
                                ? `${totals.unmapped_areas} unplaced`
                                : 'all placed'
                        }
                    />
                </div>
            </header>

            {/* Map + panel -------------------------------------------------- */}
            <div className="grid grid-cols-1 items-start border-t border-black/[0.06] lg:grid-cols-2 dark:border-white/[0.07]">
                <div className="relative border-b border-black/[0.06] bg-gradient-to-b from-stone-50/80 to-white p-4 lg:sticky lg:top-4 lg:self-start lg:border-r lg:border-b-0 dark:border-white/[0.07] dark:from-zinc-950/40 dark:to-zinc-900">
                    {geoError ? (
                        <RtsEmptyState message={geoError} height="h-[540px]" />
                    ) : busy ? (
                        <MapSkeleton
                            label={
                                geoData ? 'Loading RTS data…' : 'Loading map…'
                            }
                        />
                    ) : rows.length === 0 ? (
                        <RtsEmptyState
                            message={emptyMessage}
                            height="h-[540px]"
                        />
                    ) : (
                        <>
                            <div className="mx-auto max-h-[72vh] max-w-[520px]">
                                <HeatMapCanvas
                                    geoData={geoData as PhGeoJson}
                                    rowsByGid={rowsByGid}
                                    colorFor={colorFor}
                                    isDark={isDark}
                                    onEnter={handleEnter}
                                    onMove={handleMove}
                                    onLeave={handleLeave}
                                />
                            </div>

                            <Legend
                                buckets={scale.buckets}
                                groups={scale.groups}
                                counts={grouped}
                                isDark={isDark}
                            />
                        </>
                    )}
                </div>

                <aside className="p-5">
                    {mode === 'rts-orders' ? (
                        <>
                            <PanelHeading
                                title="CSR monitoring groups"
                                note={
                                    scale.highVolumeFrom
                                        ? `High volume = ${scale.highVolumeFrom}+ orders — 1.5× the ${scale.averageOrders}-order average`
                                        : undefined
                                }
                            />
                            {rows.length === 0 ? (
                                <EmptyNote />
                            ) : (
                                <div className="space-y-4">
                                    {CSR_GROUPS.map((group) => (
                                        <GroupPanel
                                            key={group.key}
                                            group={group}
                                            rows={grouped.get(group.key) ?? []}
                                            nameOf={nameOf}
                                            rateTone={rateTone}
                                        />
                                    ))}
                                </div>
                            )}
                        </>
                    ) : (
                        <>
                            <PanelHeading
                                title={
                                    groupBy === 'region'
                                        ? 'Regions'
                                        : 'Highest RTS rate'
                                }
                                note="Ranked by rate"
                            />
                            {ranked.length === 0 ? (
                                <EmptyNote />
                            ) : (
                                <ol className="-mx-2">
                                    {ranked.map((row, index) => (
                                        <li
                                            key={row.region ?? row.gids[0]}
                                            className="flex items-center gap-2.5 rounded-lg px-2 py-1.5 transition-colors hover:bg-black/[0.03] dark:hover:bg-white/[0.04]"
                                        >
                                            <span className="w-4 shrink-0 text-right font-mono text-[10px] text-gray-300 tabular-nums dark:text-gray-600">
                                                {index + 1}
                                            </span>
                                            <span
                                                className="h-2.5 w-2.5 shrink-0 rounded-full ring-1 ring-black/10 dark:ring-white/10"
                                                style={{
                                                    background: colorFor(row),
                                                }}
                                            />
                                            <span className="min-w-0 flex-1 truncate text-[12px] text-gray-700 dark:text-gray-300">
                                                {nameOf(row)}
                                            </span>
                                            <span className="shrink-0 font-mono text-[10px] text-gray-400 tabular-nums dark:text-gray-600">
                                                {row.orders.toLocaleString()}
                                            </span>
                                            <span
                                                className={`w-11 shrink-0 text-right font-mono text-[12px] font-semibold tabular-nums ${rateTone(row.rts_rate_percentage)}`}
                                            >
                                                {row.rts_rate_percentage.toFixed(
                                                    1,
                                                )}
                                                %
                                            </span>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </>
                    )}

                    {!!totals?.unmapped_areas && (
                        <p className="mt-5 border-t border-black/[0.06] pt-3 text-[11px] leading-relaxed text-gray-400 dark:border-white/[0.07] dark:text-gray-500">
                            {totals.unmapped_areas.toLocaleString()}{' '}
                            {totals.unmapped_areas === 1 ? 'area' : 'areas'}{' '}
                            could not be placed — no province on the address.
                            Still counted above, not shaded.
                        </p>
                    )}
                </aside>
            </div>

            {hover && (
                <Tooltip
                    row={hover.row}
                    x={hover.x}
                    y={hover.y}
                    title={nameOf(hover.row)}
                    subtitle={
                        groupBy === 'province' ? hover.row.province_id : null
                    }
                    group={hoverGroup}
                    rateTone={rateTone}
                />
            )}
        </section>
    );
}

/** "12 Aug 2026" — readable in an empty-state sentence. */
const day = (iso: string) =>
    new Date(`${iso}T00:00:00`).toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

/**
 * A headline figure. The number carries the weight; the label and the supporting
 * figure stay in muted ink so the eye lands on the value first.
 */
const Kpi = ({
    label,
    value,
    sub,
    tone,
}: {
    label: string;
    value: string;
    sub?: string;
    tone?: string;
}) => (
    <div className="bg-white px-4 py-3 dark:bg-zinc-900">
        <p className="text-[10px] font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
            {label}
        </p>
        <p
            className={`mt-1 font-mono text-[22px] leading-none font-semibold tracking-tight tabular-nums ${tone ?? 'text-gray-900 dark:text-gray-50'}`}
        >
            {value}
        </p>
        {sub && (
            <p className="mt-1.5 truncate font-mono text-[11px] text-gray-400 tabular-nums dark:text-gray-500">
                {sub}
            </p>
        )}
    </div>
);

const PanelHeading = ({ title, note }: { title: string; note?: string }) => (
    <div className="mb-4">
        <h3 className="text-[11px] font-semibold tracking-wide text-gray-400 uppercase dark:text-gray-500">
            {title}
        </h3>
        {note && (
            <p className="mt-1 text-[11px] text-gray-400 dark:text-gray-600">
                {note}
            </p>
        )}
    </div>
);

const EmptyNote = () => (
    <p className="text-[12px] text-gray-400 dark:text-gray-600">No data</p>
);

/** Shimmer rather than a spinner: the shape of what is coming, not a wait cursor. */
const MapSkeleton = ({ label }: { label: string }) => (
    <div className="flex h-[540px] flex-col items-center justify-center gap-4">
        <div className="h-56 w-36 animate-pulse rounded-[40%_60%_55%_45%/_45%_40%_60%_55%] bg-black/[0.06] dark:bg-white/[0.06]" />
        <p className="flex items-center gap-2 text-[12px] text-gray-400 dark:text-gray-500">
            <Loader2 className="h-3.5 w-3.5 animate-spin" />
            {label}
        </p>
    </div>
);

/** One CSR group and the areas in it, as the monitoring sheet lists them. */
const GroupPanel = ({
    group,
    rows,
    nameOf,
    rateTone,
}: {
    group: CsrGroup;
    rows: HeatRow[];
    nameOf: (row: HeatRow) => string;
    rateTone: (rate: number) => string;
}) => (
    <div className="overflow-hidden rounded-xl ring-1 ring-black/[0.06] dark:ring-white/[0.07]">
        <div
            className="flex items-center gap-2 px-3 py-2"
            style={{ background: `${group.color}14` }}
        >
            <span
                className="h-2.5 w-2.5 shrink-0 rounded-full"
                style={{ background: group.color }}
            />
            <span className="min-w-0 flex-1 truncate text-[11px] font-semibold text-gray-800 dark:text-gray-200">
                {group.label}
            </span>
            <span
                className="shrink-0 rounded-full px-1.5 py-0.5 font-mono text-[10px] font-semibold tabular-nums"
                style={{ background: `${group.color}22`, color: group.color }}
            >
                {rows.length}
            </span>
        </div>
        <p className="px-3 pt-2 text-[10px] leading-snug text-gray-400 dark:text-gray-500">
            {group.strategy}
        </p>
        {rows.length === 0 ? (
            <p className="px-3 py-2 text-[11px] text-gray-300 dark:text-gray-700">
                None
            </p>
        ) : (
            <ul className="px-1.5 py-1.5 sm:columns-2 sm:gap-x-2">
                {rows.map((row) => (
                    <li
                        key={row.region ?? row.gids[0]}
                        className="flex break-inside-avoid items-baseline gap-2 rounded-md px-1.5 py-1 transition-colors hover:bg-black/[0.03] dark:hover:bg-white/[0.04]"
                    >
                        <span className="min-w-0 flex-1 truncate text-[11px] text-gray-600 dark:text-gray-400">
                            {nameOf(row)}
                        </span>
                        <span className="shrink-0 font-mono text-[10px] text-gray-400 tabular-nums dark:text-gray-600">
                            {row.orders.toLocaleString()}
                        </span>
                        <span
                            className={`w-9 shrink-0 text-right font-mono text-[10px] font-semibold tabular-nums ${rateTone(row.rts_rate_percentage)}`}
                        >
                            {row.rts_rate_percentage.toFixed(0)}%
                        </span>
                    </li>
                ))}
            </ul>
        )}
    </div>
);

const Tooltip = ({
    row,
    x,
    y,
    title,
    subtitle,
    group,
    rateTone,
}: {
    row: HeatRow;
    x: number;
    y: number;
    title: string;
    subtitle: string | null;
    group: CsrGroup | null;
    rateTone: (rate: number) => string;
}) => (
    <div
        className="pointer-events-none fixed z-50 w-56 rounded-xl bg-white/95 p-3 shadow-xl ring-1 ring-black/10 backdrop-blur dark:bg-zinc-800/95 dark:ring-white/10"
        style={{
            left: x + 16,
            top: y + 16,
            transform:
                x > window.innerWidth - 260
                    ? 'translateX(-100%) translateX(-32px)'
                    : undefined,
        }}
    >
        <div className="flex items-baseline justify-between gap-2">
            <p className="min-w-0 truncate text-[12px] font-semibold text-gray-900 dark:text-gray-50">
                {title}
            </p>
            <span
                className={`shrink-0 font-mono text-[13px] font-semibold tabular-nums ${rateTone(row.rts_rate_percentage)}`}
            >
                {row.rts_rate_percentage.toFixed(1)}%
            </span>
        </div>
        {subtitle && (
            <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                {subtitle}
            </p>
        )}

        <dl className="mt-2 space-y-1 border-t border-black/[0.06] pt-2 text-[11px] dark:border-white/10">
            <TooltipRow
                label="Orders"
                value={`${row.orders.toLocaleString()} · ${peso(row.sales)}`}
            />
            <TooltipRow
                label="Delivered"
                value={`${row.delivered_count.toLocaleString()} · ${peso(row.delivered_amount)}`}
            />
            <TooltipRow
                label="Returned"
                value={`${row.returning_count.toLocaleString()} · ${peso(row.returning_amount)}`}
            />
        </dl>

        {group && (
            <div className="mt-2 border-t border-black/[0.06] pt-2 dark:border-white/10">
                <p
                    className="flex items-center gap-1.5 text-[11px] font-semibold"
                    style={{ color: group.color }}
                >
                    <span
                        className="h-2 w-2 shrink-0 rounded-full"
                        style={{ background: group.color }}
                    />
                    {group.label}
                </p>
                <p className="mt-0.5 text-[10px] leading-snug text-gray-500 dark:text-gray-400">
                    {group.strategy}
                </p>
            </div>
        )}
    </div>
);

const TooltipRow = ({ label, value }: { label: string; value: string }) => (
    <div className="flex items-baseline justify-between gap-3">
        <dt className="text-gray-400 dark:text-gray-500">{label}</dt>
        <dd className="font-mono text-gray-700 tabular-nums dark:text-gray-300">
            {value}
        </dd>
    </div>
);

const Segmented = ({
    options,
    value,
    onChange,
    disabled = false,
    disabledHint,
}: {
    options: { value: string; label: string }[];
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
    disabledHint?: string;
}) => (
    <div
        className={`flex rounded-lg bg-black/[0.04] p-0.5 text-[12px] font-medium dark:bg-white/[0.06] ${disabled ? 'opacity-50' : ''}`}
        title={disabled ? disabledHint : undefined}
    >
        {options.map((option) => (
            <button
                key={option.value}
                type="button"
                disabled={disabled}
                onClick={() => onChange(option.value)}
                className={`rounded-[6px] px-2.5 py-1 transition-all ${disabled ? 'cursor-not-allowed' : ''} ${
                    option.value === value
                        ? 'bg-white text-gray-900 shadow-sm dark:bg-zinc-700 dark:text-gray-50'
                        : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'
                }`}
            >
                {option.label}
            </button>
        ))}
    </div>
);

/**
 * The rate ramp is a scale, so it reads as a column of bands. The CSR groups are
 * categories with an action attached, so they read as a key with counts.
 */
const Legend = ({
    buckets,
    groups,
    counts,
    isDark,
}: {
    buckets: Bucket[] | null;
    groups: CsrGroup[] | null;
    counts: Map<CsrGroupKey, HeatRow[]>;
    isDark: boolean;
}) => (
    <div className="absolute bottom-6 left-6 rounded-xl bg-white/85 p-3 shadow-lg ring-1 ring-black/[0.06] backdrop-blur-sm dark:bg-zinc-800/85 dark:ring-white/10">
        <p className="mb-2 text-[10px] font-semibold tracking-wide text-gray-400 uppercase dark:text-gray-500">
            {groups ? 'CSR group' : 'RTS rate · by value'}
        </p>

        {groups ? (
            <div className="space-y-1.5">
                {groups.map((group) => (
                    <div key={group.key} className="flex items-center gap-2">
                        <span
                            className="h-3 w-3 shrink-0 rounded-[3px]"
                            style={{ background: group.color }}
                        />
                        <span className="text-[10px] text-gray-600 dark:text-gray-400">
                            {group.label}
                        </span>
                        <span className="ml-auto pl-2 font-mono text-[10px] text-gray-400 tabular-nums dark:text-gray-500">
                            {counts.get(group.key)?.length ?? 0}
                        </span>
                    </div>
                ))}
            </div>
        ) : (
            <div className="flex items-center gap-2">
                <div className="flex overflow-hidden rounded-[3px]">
                    {(buckets ?? []).map((bucket) => (
                        <span
                            key={bucket.label}
                            className="h-3 w-6"
                            style={{ background: bucket.color }}
                            title={bucket.label}
                        />
                    ))}
                </div>
                <span className="font-mono text-[10px] text-gray-400 tabular-nums dark:text-gray-500">
                    0 → 40%+
                </span>
            </div>
        )}

        {/* Only the rate scale calls out the grey. In the CSR view the four groups
            are the whole key, and an area with no orders is simply not in it. */}
        {!groups && (
            <div className="mt-2 flex items-center gap-2 border-t border-black/[0.06] pt-2 dark:border-white/10">
                <span
                    className="h-3 w-3 shrink-0 rounded-[3px]"
                    style={{
                        background: isDark
                            ? NO_DATA_COLOR.dark
                            : NO_DATA_COLOR.light,
                    }}
                />
                <span className="text-[10px] text-gray-500 dark:text-gray-500">
                    No data
                </span>
            </div>
        )}
    </div>
);
