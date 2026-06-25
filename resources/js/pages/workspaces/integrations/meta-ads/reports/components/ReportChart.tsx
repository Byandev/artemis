import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Legend,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import {
    formatMetricNumber,
    metricIsRatio,
    metricLabel,
    metricValue,
} from '../../_shared';
import { type ChartStyle, type ReportRow } from '../types';
import { chartColor } from './chart-constants';

type ChartPoint = Record<string, number | string>;

const ADS_KEY = '__ads';
const THUMB_KEY = '__thumb';

// Like SuperAds, the hover card always surfaces these core metrics even when
// they aren't plotted as bars, so the user reads the full picture on hover.
const TOOLTIP_CORE = ['spend', 'purchases', 'impressions'];

/** Pretty date range for the tooltip header, e.g. "Apr 1 – 23, 2026". */
function formatRange(since: string, until: string): string {
    try {
        const s = new Date(since);
        const u = new Date(until);
        return `${s.toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
        })} – ${u.toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        })}`;
    } catch {
        return `${since} – ${until}`;
    }
}

/** A creative thumbnail when present, otherwise the Meta channel badge. */
function AvatarBadge({ thumb, size = 22 }: { thumb?: string; size?: number }) {
    const half = size / 2;
    if (thumb) {
        return (
            <image
                href={thumb}
                x={-half}
                y={0}
                width={size}
                height={size}
                preserveAspectRatio="xMidYMid slice"
                style={{ clipPath: 'inset(0 round 5px)' }}
            />
        );
    }
    return (
        <g>
            <rect
                x={-half}
                y={0}
                width={size}
                height={size}
                rx={5}
                fill="var(--background, #fff)"
                stroke="var(--border)"
            />
            <text
                x={0}
                y={size * 0.68}
                textAnchor="middle"
                fontSize={size * 0.62}
                fontWeight={700}
                fill="#0081FB"
            >
                ∞
            </text>
        </g>
    );
}

/**
 * SuperAds-style hover card: avatar + item name + ads count + date range, then
 * the full metric list for the hovered group. Every metric is read straight off
 * the data point, so metrics that aren't plotted as bars (e.g. Impressions,
 * Purchases) still show their value. Plotted metrics carry their series colour;
 * the rest get a muted dot.
 */
function ChartTooltip({
    active,
    payload,
    label,
    range,
    metrics,
    colors,
}: {
    active?: boolean;
    payload?: {
        name: string;
        value: number;
        color: string;
        payload: ChartPoint;
    }[];
    label?: string;
    range: string;
    metrics: string[];
    colors: Record<string, string>;
}) {
    if (!active || !payload?.length) return null;

    const point = payload[0]?.payload;
    if (!point) return null;
    const ads = point[ADS_KEY] as number | undefined;
    const thumb = point[THUMB_KEY] as string | undefined;

    return (
        <div className="min-w-[240px] rounded-[10px] border border-black/8 bg-white px-3 py-2.5 text-xs shadow-lg dark:border-white/10 dark:bg-zinc-900">
            <div className="mb-2 flex items-center gap-2">
                {thumb ? (
                    <img
                        src={thumb}
                        alt=""
                        className="h-7 w-7 rounded object-cover"
                    />
                ) : (
                    <span className="flex h-7 w-7 items-center justify-center rounded border border-black/8 text-sm font-bold text-[#0081FB] dark:border-white/10">
                        ∞
                    </span>
                )}
                <div className="min-w-0">
                    <p className="truncate font-semibold text-gray-800 dark:text-gray-100">
                        {label}
                    </p>
                    <p className="text-[10px] text-gray-400 dark:text-gray-500">
                        {ads != null && `${ads.toLocaleString()} ads · `}
                        {range}
                    </p>
                </div>
            </div>
            <ul className="space-y-1">
                {metrics.map((m) => {
                    const value = Number(point[m] ?? 0);
                    const dot = colors[m] ?? 'var(--muted-foreground)';
                    return (
                        <li
                            key={m}
                            className="flex items-center justify-between gap-4"
                        >
                            <span className="flex items-center gap-1.5 text-gray-500 dark:text-gray-400">
                                <span
                                    className="h-2 w-2 rounded-full"
                                    style={{ background: dot }}
                                />
                                {metricLabel(m)}
                            </span>
                            <span className="font-mono text-gray-800 tabular-nums dark:text-gray-100">
                                {formatMetricNumber(m, value)}
                            </span>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

/** X-axis tick: avatar badge + entity name + ad count, like the SuperAds page. */
function makeTick(rows: ReportRow[]) {
    return function Tick(props: {
        x?: number;
        y?: number;
        payload?: { value: string; index: number };
    }) {
        const { x = 0, y = 0, payload } = props;
        const row = payload ? rows[payload.index] : undefined;
        const thumb = row?.thumbnail_url || row?.image_url || undefined;
        const name = payload?.value ?? '';
        const short = name.length > 14 ? `${name.slice(0, 13)}…` : name;

        return (
            <g transform={`translate(${x},${y})`}>
                <AvatarBadge thumb={thumb} />
                <text
                    y={38}
                    textAnchor="middle"
                    fontSize={11}
                    fill="var(--foreground)"
                >
                    {short}
                </text>
                {row?.ads_count != null && (
                    <text
                        y={52}
                        textAnchor="middle"
                        fontSize={10}
                        fill="var(--muted-foreground)"
                    >
                        {row.ads_count.toLocaleString()} ads
                    </text>
                )}
            </g>
        );
    };
}

/** Bar / Stacked Bar / Line / Area visualisation of the report's rows. */
export function ReportChart({
    chart,
    rows,
    metrics,
    since,
    until,
}: {
    chart: Exclude<ChartStyle, 'gallery'>;
    rows: ReportRow[];
    metrics: string[];
    since: string;
    until: string;
}) {
    const series = metrics.length > 0 ? metrics : ['spend'];

    // Ratio metrics (ROAS/Frequency/rates) plot on a secondary right axis so
    // they're readable next to large counts/spend. Stacked views (stacked bar /
    // area) keep a single axis — stacking across two scales is meaningless.
    const canDual = chart === 'bar' || chart === 'line';
    let rightIds = canDual ? series.filter((m) => metricIsRatio(m)) : [];
    // If every series is a ratio, keep them on the left (need a primary axis).
    if (rightIds.length === series.length) rightIds = [];
    const axisOf = (m: string) => (rightIds.includes(m) ? 'right' : 'left');

    // The hover card lists the plotted series first, then any core metrics that
    // aren't already plotted, so values like Impressions/Purchases always show.
    const tooltipMetrics = [
        ...series,
        ...TOOLTIP_CORE.filter((m) => !series.includes(m)),
    ];

    // Plotted metrics keep their series colour in the tooltip; the rest fall back
    // to a muted dot (handled in ChartTooltip).
    const colors: Record<string, string> = {};
    series.forEach((m, i) => {
        colors[m] = chartColor(i);
    });

    const chartData: ChartPoint[] = rows.map((r) => {
        const point: ChartPoint = {
            name: r.name || '—',
            [ADS_KEY]: r.ads_count ?? 0,
            [THUMB_KEY]: r.thumbnail_url || r.image_url || '',
        };
        tooltipMetrics.forEach((m) => {
            point[m] = metricValue(r, m);
        });
        return point;
    });

    const range = formatRange(since, until);
    const axis = {
        tick: { fontSize: 11, fill: 'var(--muted-foreground)' },
        stroke: 'var(--border)',
    };
    const TickComp = makeTick(rows);

    // NB: this must be an array, not a React Fragment. Recharts locates its
    // sub-components (Tooltip, Legend, axes) via React.Children, which iterates
    // arrays but does NOT descend into a Fragment — wrapping these in <>…</>
    // silently drops the tooltip/legend/axis label.
    const common = [
        <CartesianGrid
            key="grid"
            strokeDasharray="3 3"
            stroke="var(--border)"
        />,
        <XAxis
            key="x"
            dataKey="name"
            interval={0}
            height={84}
            tickLine={false}
            stroke="var(--border)"
            tick={<TickComp />}
        />,
        <YAxis key="y-left" yAxisId="left" {...axis} width={56} />,
        rightIds.length > 0 ? (
            <YAxis
                key="y-right"
                yAxisId="right"
                orientation="right"
                {...axis}
                width={48}
                label={{
                    value: rightIds.map(metricLabel).join(', '),
                    position: 'top',
                    offset: 12,
                    fontSize: 11,
                    fill: 'var(--muted-foreground)',
                }}
            />
        ) : null,
        <Tooltip
            key="tooltip"
            content={
                <ChartTooltip
                    range={range}
                    metrics={tooltipMetrics}
                    colors={colors}
                />
            }
            cursor={{ fill: 'var(--muted-foreground)', opacity: 0.08 }}
        />,
        <Legend
            key="legend"
            formatter={(v: string) => (
                <span style={{ color: 'var(--muted-foreground)' }}>
                    {metricLabel(v)}
                    {rightIds.includes(v) ? ' ↗' : ''}
                </span>
            )}
            wrapperStyle={{ fontSize: 12 }}
        />,
    ];

    return (
        <div className="rounded-2xl border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900">
            <ResponsiveContainer width="100%" height={480}>
                {chart === 'line' ? (
                    <LineChart data={chartData}>
                        {common}
                        {series.map((m, i) => (
                            <Line
                                key={m}
                                yAxisId={axisOf(m)}
                                type="monotone"
                                dataKey={m}
                                stroke={chartColor(i)}
                                strokeWidth={2}
                                dot={false}
                            />
                        ))}
                    </LineChart>
                ) : chart === 'area' ? (
                    <AreaChart data={chartData}>
                        {common}
                        {series.map((m, i) => (
                            <Area
                                key={m}
                                yAxisId={axisOf(m)}
                                type="monotone"
                                dataKey={m}
                                stroke={chartColor(i)}
                                fill={chartColor(i)}
                                fillOpacity={0.15}
                                strokeWidth={2}
                                stackId="stack"
                            />
                        ))}
                    </AreaChart>
                ) : (
                    <BarChart data={chartData}>
                        {common}
                        {series.map((m, i) => (
                            <Bar
                                key={m}
                                yAxisId={axisOf(m)}
                                dataKey={m}
                                fill={chartColor(i)}
                                stackId={
                                    chart === 'stacked_bar'
                                        ? 'stack'
                                        : undefined
                                }
                                radius={
                                    chart === 'stacked_bar'
                                        ? undefined
                                        : [3, 3, 0, 0]
                                }
                            />
                        ))}
                    </BarChart>
                )}
            </ResponsiveContainer>
        </div>
    );
}
