import { GameboardBackdrop } from '@/components/sales-targets/gameboard-backdrop';
import { GameboardKpis } from '@/components/sales-targets/gameboard-kpis';
import { LeaderTeam } from '@/components/sales-targets/gameboard-leader';
import { TeamPerformance } from '@/components/sales-targets/gameboard-teams';
import {
    formatLongDate,
    formatPeso,
} from '@/pages/workspaces/sales-marketing/sales-targets/shared';
import {
    ChevronLeft,
    ChevronRight,
    CircleCheck,
    Flame,
    Pause,
    Play,
    Square,
    SquareCheck,
    Trophy,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

const INTERVALS = [5, 10, 15, 30];

/**
 * The team bar reads against the target itself, so the axis stops at 100% — a
 * team past its goal sits full, and the exact figure is on the tile above.
 * Ticks include the 0 so the labels, spread edge to edge, land on the positions
 * they name.
 */
const SLIDE_SCALE_MAX = 100;
const SLIDE_TICKS = [0, 25, 50, 75, 100];

export interface SlideshowData {
    workspaceName: string;
    featured?: { name: string; date: string } | null;
    kpis: GameboardKpis | null;
    leader: LeaderTeam | null;
    teams: TeamPerformance[] | null;
}

interface Slide {
    key: string;
    render: () => React.ReactNode;
}

/** A headline figure on the overview slide, sized to be read across a room. */
function BigStat({
    label,
    value,
    caption,
    tone = 'ink',
}: {
    label: string;
    value: string;
    caption?: string;
    tone?: 'ink' | 'brand' | 'indigo' | 'amber';
}) {
    const toneClass = {
        ink: 'text-gray-900 dark:text-white',
        brand: 'text-brand-600 dark:text-brand-400',
        indigo: 'text-indigo-600 dark:text-indigo-400',
        amber: 'text-amber-600 dark:text-amber-500',
    }[tone];

    return (
        <div className="rounded-2xl border border-black/6 bg-white/85 px-6 py-5 shadow-[0_1px_2px_rgba(9,52,41,0.04),0_10px_30px_-14px_rgba(9,52,41,0.14)] dark:border-white/8 dark:bg-zinc-900/80 dark:shadow-[0_1px_2px_rgba(0,0,0,0.4),0_10px_30px_-14px_rgba(0,0,0,0.7)]">
            <p className="font-mono text-[11px] font-medium tracking-[0.16em] text-gray-500 uppercase lg:text-[13px] dark:text-gray-400">
                {label}
            </p>
            <p
                className={`mt-1 text-[30px] leading-none font-bold tabular-nums lg:text-[42px] ${toneClass}`}
            >
                {value}
            </p>
            {caption && (
                <p className="mt-1.5 text-[12px] text-gray-500 lg:text-[14px] dark:text-gray-400">
                    {caption}
                </p>
            )}
        </div>
    );
}

function Criterion({ met, label }: { met: boolean; label: string }) {
    const Icon = met ? SquareCheck : Square;

    return (
        <span className="flex items-center gap-2 text-[14px] lg:text-[17px]">
            <Icon
                className={`h-5 w-5 shrink-0 ${
                    met
                        ? 'text-brand-600 dark:text-brand-400'
                        : 'text-gray-300 dark:text-gray-600'
                }`}
            />
            <span
                className={
                    met
                        ? 'text-gray-700 dark:text-gray-200'
                        : 'text-gray-400 dark:text-gray-500'
                }
            >
                {label}
            </span>
        </span>
    );
}

/** Medal for the podium; everyone else gets their number. */
const MEDALS: Record<number, string> = { 1: '🥇', 2: '🥈', 3: '🥉' };

/** The name takes the podium's colour, so rank reads before you read the words. */
const podiumName: Record<number, string> = {
    1: 'text-amber-500',
    2: 'text-slate-400',
    3: 'text-orange-500 dark:text-orange-400',
};

/** One team, alone on the screen. */
function TeamSlide({ team, of }: { team: TeamPerformance; of: number }) {
    const achievement = team.achievement_pct ?? 0;
    const exceeded = team.above_target >= 0;

    const card =
        'rounded-2xl border border-black/6 bg-white/85 px-5 py-4 text-left shadow-[0_1px_2px_rgba(9,52,41,0.04),0_10px_30px_-14px_rgba(9,52,41,0.14)] dark:border-white/8 dark:bg-zinc-900/80 dark:shadow-[0_1px_2px_rgba(0,0,0,0.4),0_10px_30px_-14px_rgba(0,0,0,0.7)]';
    const cardLabel =
        'font-mono text-[11px] font-medium tracking-[0.16em] text-gray-500 uppercase lg:text-[13px] dark:text-gray-400';
    const cardValue =
        'mt-1 flex items-center gap-2 text-[26px] leading-none font-bold tabular-nums lg:text-[36px]';

    return (
        <div className="mx-auto w-full max-w-5xl text-center">
            <div className="text-[34px] leading-none lg:text-[44px]">
                {MEDALS[team.rank] ?? (
                    <span className="font-mono text-[26px] font-bold text-gray-400 lg:text-[32px] dark:text-gray-500">
                        #{team.rank}
                    </span>
                )}
            </div>

            <h2
                className={`my-0! mt-3 text-[40px]! leading-none font-bold tracking-tight uppercase lg:text-[64px]! ${
                    podiumName[team.rank] ?? 'text-gray-900 dark:text-white'
                }`}
            >
                {team.name}
            </h2>

            <p className="mt-3 text-[14px] text-gray-500 lg:text-[17px] dark:text-gray-400">
                Rank {team.rank} of {of}
            </p>

            <div className="mt-7 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div className={card}>
                    <p className={cardLabel}>Sales Target</p>
                    <p className={`${cardValue} text-gray-900 dark:text-white`}>
                        {formatPeso(team.target)}
                    </p>
                </div>
                <div className={card}>
                    <p className={cardLabel}>Current Sales</p>
                    <p
                        className={`${cardValue} text-brand-600 dark:text-brand-400`}
                    >
                        {formatPeso(team.sales)}
                    </p>
                </div>
                <div className={card}>
                    <p className={cardLabel}>Ads Budget</p>
                    <p className={`${cardValue} text-gray-900 dark:text-white`}>
                        {team.ad_budget === null
                            ? '—'
                            : formatPeso(team.ad_budget)}
                    </p>
                </div>

                <div className={card}>
                    <p className={cardLabel}>Target Achievement</p>
                    <p
                        className={`${cardValue} text-brand-600 dark:text-brand-400`}
                    >
                        {team.achievement_pct === null
                            ? '—'
                            : `${team.achievement_pct}%`}
                        {team.hit_target && (
                            <CircleCheck className="h-6 w-6 shrink-0 lg:h-8 lg:w-8" />
                        )}
                    </p>
                </div>
                <div className={card}>
                    <p className={cardLabel}>ROAS</p>
                    <p
                        className={`${cardValue} text-brand-600 dark:text-brand-400`}
                    >
                        {team.roas === null ? '—' : team.roas.toFixed(2)}
                        {team.hit_roas && (
                            <CircleCheck className="h-6 w-6 shrink-0 lg:h-8 lg:w-8" />
                        )}
                    </p>
                </div>
                <div className={card}>
                    <p className={cardLabel}>
                        {exceeded ? 'Above Target' : 'Below Target'}
                    </p>
                    <p
                        className={`${cardValue} ${
                            exceeded
                                ? 'text-amber-500'
                                : 'text-red-500 dark:text-red-400'
                        }`}
                    >
                        {formatPeso(Math.abs(team.above_target))}
                    </p>
                </div>
            </div>

            <div className="mt-7">
                <div className="h-3 overflow-hidden rounded-full bg-stone-200 dark:bg-zinc-800">
                    <div
                        className={`h-full rounded-full transition-[width] duration-700 ${
                            team.hit_target
                                ? 'bg-amber-400'
                                : 'bg-brand-500 dark:bg-brand-400'
                        }`}
                        style={{
                            width: `${Math.min(100, Math.max(0, (achievement / SLIDE_SCALE_MAX) * 100))}%`,
                        }}
                    />
                </div>
                <div className="mt-1.5 flex justify-between font-mono text-[10px] text-gray-400 tabular-nums lg:text-[12px] dark:text-gray-500">
                    {SLIDE_TICKS.map((tick) => (
                        <span key={tick}>{tick}%</span>
                    ))}
                </div>
            </div>

            <div className="mt-6 flex flex-wrap items-center justify-center gap-5">
                {team.qualified && (
                    <span className="flex items-center gap-2 rounded-full border border-brand-500/50 px-4 py-1.5 font-mono text-[13px] font-medium tracking-wider text-brand-600 uppercase lg:text-[15px] dark:border-brand-400/50 dark:text-brand-400">
                        <Trophy className="h-4 w-4 lg:h-5 lg:w-5" />
                        Qualified
                    </span>
                )}
                {team.hit_target && (
                    <span className="flex items-center gap-2 text-[13px] font-semibold text-amber-600 lg:text-[15px] dark:text-amber-500">
                        <Flame className="h-4 w-4 lg:h-5 lg:w-5" />
                        Target Breaker
                    </span>
                )}
            </div>
        </div>
    );
}

/**
 * Full-screen rotation of the board, for a TV nobody is sitting at.
 *
 * Slides are built from whatever loaded — a section whose request failed is
 * skipped rather than shown blank, so a broken panel costs one slide instead of
 * stalling the rotation on an empty screen.
 */
export function GameboardSlideshow({
    data,
    onClose,
}: {
    data: SlideshowData;
    onClose: () => void;
}) {
    const [index, setIndex] = useState(0);
    const [seconds, setSeconds] = useState(10);
    const [paused, setPaused] = useState(false);
    const [progress, setProgress] = useState(0);

    const { workspaceName, featured, kpis, leader, teams } = data;

    const slides: Slide[] = [];

    if (kpis) {
        slides.push({
            key: 'overview',
            render: () => (
                <div className="mx-auto w-full max-w-6xl text-center">
                    <p className="font-mono text-[13px] font-medium tracking-[0.22em] text-brand-600 uppercase lg:text-[16px] dark:text-brand-400">
                        {workspaceName}
                    </p>
                    <h1 className="my-0! mt-2 text-[38px]! leading-none font-bold tracking-tight text-gray-900 uppercase lg:text-[62px]! dark:text-white">
                        Double Digit Sales Gameboard
                    </h1>
                    {featured && (
                        <p className="mt-3 text-[18px] text-gray-600 lg:text-[24px] dark:text-gray-300">
                            {featured.name} · {formatLongDate(featured.date)}
                        </p>
                    )}
                    <p className="mt-1 text-[15px] text-brand-600 italic lg:text-[19px] dark:text-brand-400">
                        Race to the Daily Target
                    </p>

                    <div className="mt-8 grid grid-cols-1 gap-3 text-left sm:grid-cols-2 lg:grid-cols-4">
                        <BigStat
                            label="Total Sales"
                            value={formatPeso(kpis.total_sales)}
                            caption={`Target: ${formatPeso(kpis.target_sales)}`}
                            tone="brand"
                        />
                        <BigStat
                            label="Overall Achievement"
                            value={
                                kpis.achievement_pct === null
                                    ? '—'
                                    : `${kpis.achievement_pct}%`
                            }
                            caption={`${kpis.teams_at_target} of ${kpis.teams_total} teams at 100%+`}
                            tone="indigo"
                        />
                        <BigStat
                            label="Total Ads Budget"
                            value={
                                kpis.ad_budget === null
                                    ? '—'
                                    : formatPeso(kpis.ad_budget)
                            }
                            caption="Campaign budget"
                        />
                        <BigStat
                            label="Overall ROAS"
                            value={
                                kpis.roas === null ? '—' : kpis.roas.toFixed(2)
                            }
                            caption={`${kpis.teams_at_roas} of ${kpis.teams_total} teams at ${kpis.qualifying_roas.toFixed(2)}+`}
                            tone="indigo"
                        />
                    </div>

                    <div className="mt-8 flex flex-wrap items-start justify-center gap-10">
                        <div>
                            <p className="font-mono text-[11px] tracking-[0.16em] text-gray-500 uppercase lg:text-[13px] dark:text-gray-400">
                                Qualified Teams
                            </p>
                            <p className="mt-1 text-[28px] font-bold text-brand-600 tabular-nums lg:text-[36px] dark:text-brand-400">
                                {kpis.qualified_teams}
                            </p>
                        </div>
                        <div>
                            <p className="font-mono text-[11px] tracking-[0.16em] text-gray-500 uppercase lg:text-[13px] dark:text-gray-400">
                                {kpis.above_target >= 0
                                    ? 'Above Target'
                                    : 'Below Target'}
                            </p>
                            <p
                                className={`mt-1 text-[28px] font-bold tabular-nums lg:text-[36px] ${
                                    kpis.above_target >= 0
                                        ? 'text-amber-500'
                                        : 'text-red-500 dark:text-red-400'
                                }`}
                            >
                                {formatPeso(Math.abs(kpis.above_target))}
                            </p>
                        </div>
                        {leader && (
                            <div>
                                <p className="font-mono text-[11px] tracking-[0.16em] text-gray-500 uppercase lg:text-[13px] dark:text-gray-400">
                                    Current Leader
                                </p>
                                <p className="mt-1 text-[28px] font-bold text-brand-600 lg:text-[36px] dark:text-brand-400">
                                    {leader.name}
                                </p>
                            </div>
                        )}
                    </div>

                    {leader && (
                        <div className="mt-7 flex flex-wrap items-center justify-center gap-8">
                            <Criterion
                                met={leader.hit_target}
                                label="100%+ Sales Target Achievement"
                            />
                            <Criterion
                                met={leader.hit_roas}
                                label={`${leader.qualifying_roas.toFixed(2)}+ ROAS`}
                            />
                        </div>
                    )}
                </div>
            ),
        });
    }

    // Teams follow the main screen directly — the standings and the charts are
    // the wrap-up, after every team has had the screen to itself.
    const roster = teams ?? [];

    roster.forEach((team) => {
        slides.push({
            key: `team-${team.team_id}`,
            render: () => <TeamSlide team={team} of={roster.length} />,
        });
    });

    const count = slides.length;
    const safeIndex = count === 0 ? 0 : index % count;

    const go = useCallback(
        (step: number) => {
            setIndex((current) => {
                if (count === 0) return 0;

                return (current + step + count) % count;
            });
            setProgress(0);
        },
        [count],
    );

    // One animation frame loop drives both the countdown bar and the advance,
    // so what the bar shows is exactly when the slide will turn.
    useEffect(() => {
        if (paused || count <= 1) return;

        const duration = seconds * 1000;
        const started = performance.now();
        let frame = 0;

        const tick = (now: number) => {
            const ratio = Math.min(1, (now - started) / duration);
            setProgress(ratio);

            if (ratio >= 1) {
                go(1);

                return;
            }

            frame = requestAnimationFrame(tick);
        };

        frame = requestAnimationFrame(tick);

        return () => cancelAnimationFrame(frame);
    }, [safeIndex, paused, seconds, count, go]);

    // Presenter keys: the remote in someone's hand is usually arrows and space.
    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') onClose();
            if (event.key === 'ArrowRight') go(1);
            if (event.key === 'ArrowLeft') go(-1);
            if (event.key === ' ') {
                event.preventDefault();
                setPaused((value) => !value);
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [go, onClose]);

    const controlClass =
        'flex h-8 items-center justify-center rounded-lg border border-black/8 px-3 font-mono text-[11px] font-medium text-gray-600 transition-all hover:bg-stone-100 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/10';

    return (
        <div className="fixed inset-0 z-50 flex flex-col bg-stone-50 dark:bg-zinc-950">
            <GameboardBackdrop />

            <header className="relative z-10 shrink-0 border-b border-black/6 bg-white/70 backdrop-blur-md dark:border-white/8 dark:bg-zinc-950/70">
                <div className="flex items-center justify-between gap-4 px-5 py-3">
                    <div className="flex min-w-0 items-center gap-2">
                        <span className="truncate font-mono text-[12px] font-bold tracking-[0.16em] text-brand-600 uppercase dark:text-brand-400">
                            {workspaceName}
                        </span>
                        <span className="text-gray-300 dark:text-gray-600">
                            ·
                        </span>
                        <span className="font-mono text-[12px] tracking-[0.16em] text-gray-500 uppercase dark:text-gray-400">
                            TV Slideshow
                        </span>
                    </div>

                    <div className="flex items-center gap-1.5">
                        {INTERVALS.map((option) => (
                            <button
                                key={option}
                                onClick={() => {
                                    setSeconds(option);
                                    setProgress(0);
                                }}
                                className={`h-8 rounded-lg px-2.5 font-mono text-[11px] font-medium transition-all ${
                                    seconds === option
                                        ? 'border border-brand-500 text-brand-600 dark:border-brand-400 dark:text-brand-400'
                                        : 'border border-transparent text-gray-500 hover:bg-stone-100 dark:text-gray-400 dark:hover:bg-white/10'
                                }`}
                            >
                                {option}s
                            </button>
                        ))}

                        <span className="px-2 font-mono text-[11px] text-gray-500 tabular-nums dark:text-gray-400">
                            {count === 0 ? 0 : safeIndex + 1} / {count}
                        </span>

                        <button
                            onClick={() => setPaused((value) => !value)}
                            aria-label={paused ? 'Resume' : 'Pause'}
                            className={controlClass}
                        >
                            {paused ? (
                                <Play className="h-3.5 w-3.5" />
                            ) : (
                                <Pause className="h-3.5 w-3.5" />
                            )}
                        </button>
                        <button
                            onClick={() => go(-1)}
                            aria-label="Previous slide"
                            className={controlClass}
                        >
                            <ChevronLeft className="h-3.5 w-3.5" />
                        </button>
                        <button
                            onClick={() => go(1)}
                            aria-label="Next slide"
                            className={controlClass}
                        >
                            <ChevronRight className="h-3.5 w-3.5" />
                        </button>
                        <button
                            onClick={onClose}
                            aria-label="Exit slideshow"
                            className="flex h-8 w-8 items-center justify-center rounded-full border border-red-400/60 text-red-500 transition-all hover:bg-red-50 dark:hover:bg-red-500/10"
                        >
                            <X className="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>

                {/* Countdown to the next slide. */}
                <div
                    className="absolute bottom-0 left-0 h-0.5 bg-brand-500 dark:bg-brand-400"
                    style={{ width: `${progress * 100}%` }}
                />
            </header>

            <main className="relative z-10 flex flex-1 items-center justify-center overflow-y-auto px-6 py-8">
                {count === 0 ? (
                    <p className="text-[14px] text-gray-500 dark:text-gray-400">
                        Nothing to present yet.
                    </p>
                ) : (
                    slides[safeIndex].render()
                )}
            </main>

            {count > 1 && (
                <footer className="relative z-10 flex shrink-0 items-center justify-center gap-1.5 pb-5">
                    {slides.map((slide, i) => (
                        <button
                            key={slide.key}
                            onClick={() => {
                                setIndex(i);
                                setProgress(0);
                            }}
                            aria-label={`Go to slide ${i + 1}`}
                            className={`h-1.5 rounded-full transition-all ${
                                i === safeIndex
                                    ? 'w-6 bg-brand-500 dark:bg-brand-400'
                                    : 'w-1.5 bg-gray-300 hover:bg-gray-400 dark:bg-zinc-700 dark:hover:bg-zinc-600'
                            }`}
                        />
                    ))}
                </footer>
            )}
        </div>
    );
}
