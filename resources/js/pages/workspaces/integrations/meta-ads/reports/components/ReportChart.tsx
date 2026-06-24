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
 * every plotted metric with its series color, value, and share of the total
 * across the visible groups.
 */
function ChartTooltip({
    active,
    payload,
    label,
    range,
    totals,
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
    totals: Record<string, number>;
}) {
    if (!active || !payload?.length) return null;

    const point = payload[0]?.payload;
    const ads = point?.[ADS_KEY] as number | undefined;
    const thumb = point?.[THUMB_KEY] as string | undefined;

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
                {payload.map((p) => {
                    const total = totals[p.name] ?? 0;
                    const share =
                        !metricIsRatio(p.name) && total > 0
                            ? `${Math.round((p.value / total) * 100)}%`
                            : null;
                    return (
                        <li
                            key={p.name}
                            className="flex items-center justify-between gap-4"
                        >
                            <span className="flex items-center gap-1.5 text-gray-500 dark:text-gray-400">
                                <span
                                    className="h-2 w-2 rounded-full"
                                    style={{ background: p.color }}
                                />
                                {metricLabel(p.name)}
                            </span>
                            <span className="flex items-center gap-1.5">
                                {share && (
                                    <span className="text-[10px] text-gray-400 dark:text-gray-500">
                                        {share}
                                    </span>
                                )}
                                <span className="font-mono text-gray-800 tabular-nums dark:text-gray-100">
                                    {formatMetricNumber(p.name, p.value)}
                                </span>
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

    const chartData: ChartPoint[] = rows.map((r) => {
        const point: ChartPoint = {
            name: r.name || '—',
            [ADS_KEY]: r.ads_count ?? 0,
            [THUMB_KEY]: r.thumbnail_url || r.image_url || '',
        };
        series.forEach((m) => {
            point[m] = metricValue(r, m);
        });
        return point;
    });

    // Per-metric totals across the visible groups → "share of total" in tooltip.
    const totals: Record<string, number> = {};
    series.forEach((m) => {
        totals[m] = chartData.reduce((s, p) => s + (Number(p[m]) || 0), 0);
    });

    const range = formatRange(since, until);
    const axis = {
        tick: { fontSize: 11, fill: 'var(--muted-foreground)' },
        stroke: 'var(--border)',
    };
    const TickComp = makeTick(rows);

    const common = (
        <>
            <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" />
            <XAxis
                dataKey="name"
                interval={0}
                height={84}
                tickLine={false}
                stroke="var(--border)"
                tick={<TickComp />}
            />
            <YAxis yAxisId="left" {...axis} width={56} />
            {rightIds.length > 0 && (
                <YAxis
                    yAxisId="right"
                    orientation="right"
                    {...axis}
                    width={48}
                />
            )}
            <Tooltip
                content={<ChartTooltip range={range} totals={totals} />}
                cursor={{ fill: 'var(--muted-foreground)', opacity: 0.08 }}
            />
            <Legend
                formatter={(v: string) => (
                    <span style={{ color: 'var(--muted-foreground)' }}>
                        {metricLabel(v)}
                        {rightIds.includes(v) ? ' ↗' : ''}
                    </span>
                )}
                wrapperStyle={{ fontSize: 12 }}
            />
        </>
    );

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
