import type { RtsQueryParams } from '@/components/rts/rts-shared';
import {
    RefreshButton,
    RtsEmptyState,
    buildBaseParams,
} from '@/components/rts/rts-shared';
import { Loader2, Minus, Plus, RotateCcw } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import HeatMapCanvas from './HeatMapCanvas';
import {
    BORDER_COLOR,
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

const DEFAULT_CENTER: [number, number] = [121.75, 13];

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

    const [zoom, setZoom] = useState(1);
    const [center, setCenter] = useState<[number, number]>(DEFAULT_CENTER);

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
    const handleMoveEnd = useCallback(
        (position: { coordinates: [number, number]; zoom: number }) => {
            setCenter(position.coordinates);
            setZoom(position.zoom);
        },
        [],
    );

    const resetView = () => {
        setCenter(DEFAULT_CENTER);
        setZoom(1);
    };

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

    return (
        <div className="rounded-2xl border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
            <div className="flex flex-col gap-4 border-b border-black/6 px-5 py-4 dark:border-white/6">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h2 className="text-[14px] font-semibold text-gray-900 dark:text-gray-100">
                            RTS Heat Map
                        </h2>
                        <p className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                            {modeConfig.short} by{' '}
                            {groupBy === 'region' ? 'island group' : 'province'}
                        </p>
                    </div>
                    <RefreshButton onClick={fetchData} loading={loading} />
                </div>

                <div className="flex flex-wrap items-center gap-3">
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
                </div>

                {totals && (
                    <div className="flex flex-wrap gap-x-6 gap-y-1 text-[12px] text-gray-500 dark:text-gray-400">
                        <Stat
                            label="Orders"
                            value={totals.orders.toLocaleString()}
                        />
                        <Stat label="Sales" value={peso(totals.sales)} />
                        <Stat
                            label="Returned"
                            value={`${totals.returning_count.toLocaleString()} · ${peso(totals.returning_amount)}`}
                        />
                        <Stat
                            label="RTS rate"
                            value={`${totals.rts_rate_percentage}%`}
                        />
                        <Stat
                            label="Areas plotted"
                            value={`${rows.length.toLocaleString()} of ${(
                                totals.mapped_areas + totals.unmapped_areas
                            ).toLocaleString()}`}
                        />
                    </div>
                )}
            </div>

            <div className="grid grid-cols-1 gap-0 lg:grid-cols-[minmax(0,1fr)_260px]">
                <div className="relative border-b border-black/6 p-3 lg:border-r lg:border-b-0 dark:border-white/6">
                    {geoError ? (
                        <RtsEmptyState message={geoError} height="h-[520px]" />
                    ) : busy ? (
                        <div className="flex h-[520px] flex-col items-center justify-center gap-3 text-[12px] text-gray-400">
                            <Loader2 className="h-5 w-5 animate-spin" />
                            {geoData ? 'Loading RTS data…' : 'Loading map…'}
                        </div>
                    ) : rows.length === 0 ? (
                        <RtsEmptyState
                            message={emptyMessage}
                            height="h-[520px]"
                        />
                    ) : (
                        <>
                            <div className="mx-auto max-h-[70vh] max-w-[560px]">
                                <HeatMapCanvas
                                    geoData={geoData as PhGeoJson}
                                    rowsByGid={rowsByGid}
                                    colorFor={colorFor}
                                    isDark={isDark}
                                    zoom={zoom}
                                    center={center}
                                    onMoveEnd={handleMoveEnd}
                                    onEnter={handleEnter}
                                    onMove={handleMove}
                                    onLeave={handleLeave}
                                />
                            </div>

                            <div className="absolute top-5 right-5 flex flex-col gap-1">
                                <ZoomButton
                                    label="Zoom in"
                                    onClick={() =>
                                        setZoom((z) => Math.min(12, z * 1.5))
                                    }
                                >
                                    <Plus className="h-3.5 w-3.5" />
                                </ZoomButton>
                                <ZoomButton
                                    label="Zoom out"
                                    onClick={() =>
                                        setZoom((z) => Math.max(1, z / 1.5))
                                    }
                                >
                                    <Minus className="h-3.5 w-3.5" />
                                </ZoomButton>
                                <ZoomButton
                                    label="Reset view"
                                    onClick={resetView}
                                >
                                    <RotateCcw className="h-3.5 w-3.5" />
                                </ZoomButton>
                            </div>

                            <Legend
                                buckets={scale.buckets}
                                groups={scale.groups}
                                isDark={isDark}
                            />
                        </>
                    )}
                </div>

                <div className="p-4">
                    {mode === 'rts-orders' ? (
                        <>
                            <h3 className="mb-1 text-[12px] font-semibold tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                CSR monitoring groups
                            </h3>
                            <p className="mb-3 text-[11px] text-gray-400 dark:text-gray-600">
                                High volume is {scale.highVolumeFrom}+ orders in
                                this range.
                            </p>

                            {rows.length === 0 ? (
                                <p className="text-[12px] text-gray-400">
                                    No data
                                </p>
                            ) : (
                                <div className="space-y-3">
                                    {CSR_GROUPS.map((group) => (
                                        <GroupPanel
                                            key={group.key}
                                            group={group}
                                            rows={grouped.get(group.key) ?? []}
                                            nameOf={nameOf}
                                        />
                                    ))}
                                </div>
                            )}
                        </>
                    ) : (
                        <>
                            <h3 className="mb-3 text-[12px] font-semibold tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                {groupBy === 'region'
                                    ? 'Regions'
                                    : 'Top provinces'}
                                <span className="ml-1 font-normal normal-case">
                                    (by rate)
                                </span>
                            </h3>

                            {ranked.length === 0 ? (
                                <p className="text-[12px] text-gray-400">
                                    No data
                                </p>
                            ) : (
                                <ol className="space-y-1">
                                    {ranked.map((row, index) => (
                                        <li
                                            key={row.region ?? row.gids[0]}
                                            className="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-stone-50 dark:hover:bg-white/5"
                                        >
                                            <span className="w-4 shrink-0 text-right text-[11px] text-gray-300 dark:text-gray-600">
                                                {index + 1}
                                            </span>
                                            <span
                                                className="h-2.5 w-2.5 shrink-0 rounded-sm"
                                                style={{
                                                    background: colorFor(row),
                                                }}
                                            />
                                            <span className="min-w-0 flex-1 truncate text-[12px] text-gray-700 dark:text-gray-300">
                                                {nameOf(row)}
                                                <span className="text-gray-400 dark:text-gray-600">
                                                    {' '}
                                                    ·{' '}
                                                    {row.orders.toLocaleString()}
                                                </span>
                                            </span>
                                            <span className="shrink-0 font-mono text-[12px] font-medium text-gray-900 tabular-nums dark:text-gray-100">
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
                        <p className="mt-4 border-t border-black/6 pt-3 text-[11px] leading-relaxed text-gray-400 dark:border-white/6 dark:text-gray-600">
                            {totals.unmapped_areas.toLocaleString()}{' '}
                            {totals.unmapped_areas === 1 ? 'area' : 'areas'}{' '}
                            could not be placed on the map — the address had no
                            province recorded. Those orders still count towards
                            the totals above, but are not shaded.
                        </p>
                    )}
                </div>
            </div>

            {hover && (
                <div
                    className="pointer-events-none fixed z-50 rounded-lg border border-black/10 bg-white px-3 py-2 shadow-lg dark:border-white/10 dark:bg-zinc-800"
                    style={{
                        left: hover.x + 14,
                        top: hover.y + 14,
                        transform:
                            hover.x > window.innerWidth - 220
                                ? 'translateX(-100%) translateX(-28px)'
                                : undefined,
                    }}
                >
                    <p className="text-[12px] font-semibold text-gray-900 dark:text-gray-100">
                        {nameOf(hover.row)}
                    </p>
                    {groupBy === 'province' && hover.row.province_id && (
                        <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                            {hover.row.province_id}
                        </p>
                    )}
                    <dl className="mt-1.5 grid grid-cols-[auto_auto] gap-x-4 gap-y-0.5 text-[11px]">
                        <TooltipRow
                            label="Orders"
                            value={`${hover.row.orders.toLocaleString()} · ${peso(hover.row.sales)}`}
                        />
                        <TooltipRow
                            label="Delivered"
                            value={`${hover.row.delivered_count.toLocaleString()} · ${peso(hover.row.delivered_amount)}`}
                        />
                        <TooltipRow
                            label="Returned"
                            value={`${hover.row.returning_count.toLocaleString()} · ${peso(hover.row.returning_amount)}`}
                        />
                        <TooltipRow
                            label="RTS rate"
                            value={`${hover.row.rts_rate_percentage}% by value`}
                        />
                    </dl>
                    {hoverGroup && (
                        <div
                            className="mt-2 border-t border-black/8 pt-1.5 dark:border-white/10"
                            style={{ maxWidth: '13rem' }}
                        >
                            <p
                                className="text-[11px] font-semibold"
                                style={{ color: hoverGroup.color }}
                            >
                                {hoverGroup.label}
                            </p>
                            <p className="text-[10px] leading-snug text-gray-500 dark:text-gray-400">
                                {hoverGroup.strategy}
                            </p>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

/** "12 Aug 2026" — readable in an empty-state sentence. */
const day = (iso: string) =>
    new Date(`${iso}T00:00:00`).toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

const Stat = ({ label, value }: { label: string; value: string }) => (
    <span>
        {label}{' '}
        <span className="font-mono font-medium text-gray-900 tabular-nums dark:text-gray-100">
            {value}
        </span>
    </span>
);

const TooltipRow = ({ label, value }: { label: string; value: string }) => (
    <>
        <dt className="text-gray-400 dark:text-gray-500">{label}</dt>
        <dd className="text-right font-mono font-medium text-gray-900 tabular-nums dark:text-gray-100">
            {value}
        </dd>
    </>
);

const ZoomButton = ({
    label,
    onClick,
    children,
}: {
    label: string;
    onClick: () => void;
    children: React.ReactNode;
}) => (
    <button
        type="button"
        onClick={onClick}
        title={label}
        aria-label={label}
        className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/8 bg-white/90 text-gray-500 backdrop-blur transition-colors hover:bg-white hover:text-gray-900 dark:border-white/8 dark:bg-zinc-800/90 dark:text-gray-400 dark:hover:bg-zinc-800 dark:hover:text-gray-100"
    >
        {children}
    </button>
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
        className={`flex overflow-hidden rounded-lg border border-black/8 text-[12px] font-medium dark:border-white/8 ${disabled ? 'opacity-50' : ''}`}
        title={disabled ? disabledHint : undefined}
    >
        {options.map((option, index) => (
            <button
                key={option.value}
                type="button"
                disabled={disabled}
                onClick={() => onChange(option.value)}
                className={`px-3 py-1.5 transition-colors ${index > 0 ? 'border-l border-black/8 dark:border-white/8' : ''} ${disabled ? 'cursor-not-allowed' : ''} ${
                    option.value === value
                        ? 'bg-gray-100 text-gray-900 dark:bg-white/10 dark:text-gray-100'
                        : 'text-gray-400 hover:text-gray-700 dark:text-gray-500 dark:hover:text-gray-300'
                }`}
            >
                {option.label}
            </button>
        ))}
    </div>
);

/**
 * Two ramps when volume is in play: hue says how bad the RTS rate is, strength
 * says how many orders stand behind it. Splitting them keeps the legend readable
 * where a full bivariate grid would not fit in the corner.
 */
/** One CSR group and the areas that fall in it, as the monitoring sheet lists them. */
const GroupPanel = ({
    group,
    rows,
    nameOf,
}: {
    group: CsrGroup;
    rows: HeatRow[];
    nameOf: (row: HeatRow) => string;
}) => (
    <div>
        <div className="flex items-center gap-2">
            <span
                className="h-2.5 w-2.5 shrink-0 rounded-full"
                style={{ background: group.color }}
            />
            <span className="text-[11px] font-semibold text-gray-700 dark:text-gray-300">
                {group.label}
            </span>
            <span className="ml-auto font-mono text-[11px] text-gray-400 tabular-nums dark:text-gray-600">
                {rows.length}
            </span>
        </div>
        <p className="mt-0.5 mb-1 pl-[18px] text-[10px] leading-snug text-gray-400 dark:text-gray-600">
            {group.strategy}
        </p>
        {rows.length === 0 ? (
            <p className="pl-[18px] text-[11px] text-gray-300 dark:text-gray-700">
                None
            </p>
        ) : (
            <ul className="pl-[18px] text-[11px] text-gray-600 dark:text-gray-400">
                {rows.map((row) => (
                    <li
                        key={row.region ?? row.gids[0]}
                        className="flex items-baseline gap-2 py-px"
                    >
                        <span className="min-w-0 flex-1 truncate">
                            {nameOf(row)}
                        </span>
                        <span className="shrink-0 font-mono text-[10px] text-gray-400 tabular-nums dark:text-gray-600">
                            {row.rts_rate_percentage.toFixed(0)}% ·{' '}
                            {row.orders.toLocaleString()}
                        </span>
                    </li>
                ))}
            </ul>
        )}
    </div>
);

/**
 * The rate ramp is a scale, so it reads as a column of bands. The CSR groups are
 * categories with an action attached, so they read as a key.
 */
const Legend = ({
    buckets,
    groups,
    isDark,
}: {
    buckets: Bucket[] | null;
    groups: CsrGroup[] | null;
    isDark: boolean;
}) => (
    <div className="absolute bottom-5 left-5 rounded-lg border border-black/8 bg-white/90 px-3 py-2 backdrop-blur dark:border-white/8 dark:bg-zinc-800/90">
        <p className="mb-1.5 text-[10px] font-semibold tracking-wide text-gray-400 uppercase dark:text-gray-500">
            {groups ? 'CSR group' : 'RTS rate · by value'}
        </p>
        <div className="space-y-1">
            {(groups ?? buckets ?? []).map((entry) => (
                <div key={entry.label} className="flex items-center gap-2">
                    <span
                        className="h-3 w-3 shrink-0 rounded-sm"
                        style={{ background: entry.color }}
                    />
                    <span className="font-mono text-[10px] text-gray-600 tabular-nums dark:text-gray-400">
                        {entry.label}
                    </span>
                </div>
            ))}
            <div className="flex items-center gap-2 border-t border-black/6 pt-1 dark:border-white/6">
                <span
                    className="h-3 w-3 shrink-0 rounded-sm"
                    style={{
                        background: isDark
                            ? NO_DATA_COLOR.dark
                            : NO_DATA_COLOR.light,
                        outline: `1px solid ${isDark ? BORDER_COLOR.dark : BORDER_COLOR.light}`,
                    }}
                />
                <span className="font-mono text-[10px] text-gray-500 dark:text-gray-500">
                    {groups ? 'Not in priority set' : 'No data'}
                </span>
            </div>
        </div>
    </div>
);
