import {
    calendar as calendarRoute,
    dailyLog as dailyLogRoute,
    escRate as escRateRoute,
    learning as learningRoute,
    meditation as meditationRoute,
    movement as movementRoute,
    pillarBreakdown as pillarBreakdownRoute,
} from '@/actions/App/Http/Controllers/API/Workspace/WelleStatsController';
import PageHeader from '@/components/common/PageHeader';
import { ConnectWellePrompt } from '@/components/welle/ConnectWellePrompt';
import {
    DailyLogTable,
    type DailyLogStat,
} from '@/components/welle/DailyLogTable';
import {
    EscCalendar,
    type EscCalendarStat,
} from '@/components/welle/EscCalendar';
import {
    MonthPicker,
    monthFromUrl,
    rememberMonth,
} from '@/components/welle/MonthPicker';
import {
    PillarBreakdown,
    type PillarBreakdownStat,
} from '@/components/welle/PillarBreakdown';
import {
    DaysWithPillarStatCard,
    EscRateStatCard,
    type DaysWithPillarStat,
    type EscRateStat,
} from '@/components/welle/WelleStatCards';
import { useWelleStat } from '@/hooks/use-welle-stat';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';

interface Props {
    workspace: {
        id: number;
        name: string;
        slug: string;
    };
    /**
     * Whether this person has connected their own Welle account — shared by the
     * page rather than read off a card, so an unconnected page opens on the
     * setup prompt instead of a screen of skeletons that cannot fill.
     */
    connected: boolean;
}

type PageWorkspace = Props['workspace'];

/**
 * My ESC — the signed-in user's own Extreme Self Care record: the month's
 * figures as cards, the three pillars against each other, and the month itself
 * day by day.
 *
 * Every piece is fetched over XHR rather than shared by the page, so the page
 * renders at once and each one skeletons on its own. They all read the month
 * the picker names, so changing it moves the whole page together.
 *
 * All of that is behind a connected Welle account: without one the page is the
 * setup prompt and nothing else, and no request goes out for figures that
 * cannot exist yet.
 */
export default function MyEsc({ workspace, connected }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'My ESC',
            href: `/workspaces/${workspace.slug}/welle/my-esc`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My ESC" />

            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 sm:p-6">
                {connected ? (
                    <EscDashboard workspace={workspace} />
                ) : (
                    <>
                        <PageHeader
                            title="My ESC"
                            description="Your own Extreme Self Care record from Welle"
                            divider={false}
                        />
                        <ConnectWellePrompt workspaceSlug={workspace.slug} />
                    </>
                )}
            </div>
        </AppLayout>
    );
}

/**
 * The page proper — the month's cards, the pillars against each other, the
 * calendar and the day-by-day log.
 *
 * Its own component so the month state and the seven requests it keys exist
 * only once there is an account to answer them; mounted from an unconnected
 * page, hooks cannot simply be skipped.
 */
function EscDashboard({ workspace }: { workspace: PageWorkspace }) {
    // Seeded from the URL and written back to it, so a reload comes back to
    // the month that was being read rather than to this one.
    const [month, setMonth] = useState(monthFromUrl);

    const pickMonth = (next: string) => {
        setMonth(next);
        rememberMonth(next);
    };

    // The hook keys its request on the URL, and a route helper builds a new
    // string on every render — memoised so each card is fetched once rather
    // than on every one.
    const urls = useMemo(() => {
        const args = { workspace: workspace.slug };
        const query = { query: { month } };

        return {
            escRate: escRateRoute(args, query).url,
            movement: movementRoute(args, query).url,
            meditation: meditationRoute(args, query).url,
            learning: learningRoute(args, query).url,
            pillarBreakdown: pillarBreakdownRoute(args, query).url,
            calendar: calendarRoute(args, query).url,
            dailyLog: dailyLogRoute(args, query).url,
        };
    }, [workspace.slug, month]);

    // One call per card, one endpoint behind each.
    const [escRate, escRateLoading] = useWelleStat<EscRateStat>(urls.escRate);
    const [movement, movementLoading] = useWelleStat<DaysWithPillarStat>(
        urls.movement,
    );
    const [meditation, meditationLoading] = useWelleStat<DaysWithPillarStat>(
        urls.meditation,
    );
    const [learning, learningLoading] = useWelleStat<DaysWithPillarStat>(
        urls.learning,
    );
    const [breakdown, breakdownLoading] = useWelleStat<PillarBreakdownStat>(
        urls.pillarBreakdown,
    );
    const [calendar, calendarLoading] = useWelleStat<EscCalendarStat>(
        urls.calendar,
    );
    const [dailyLog, dailyLogLoading] = useWelleStat<DailyLogStat>(
        urls.dailyLog,
    );

    return (
        <>
            <PageHeader
                title="My ESC"
                description="Your own Extreme Self Care record from Welle"
                divider={false}
                stackActionsOnMobile
            >
                <MonthPicker value={month} onChange={pickMonth} />
            </PageHeader>

            <div className="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <EscRateStatCard stat={escRate} loading={escRateLoading} />
                <DaysWithPillarStatCard
                    pillar="movement"
                    stat={movement}
                    loading={movementLoading}
                />
                <DaysWithPillarStatCard
                    pillar="meditation"
                    stat={meditation}
                    loading={meditationLoading}
                />
                <DaysWithPillarStatCard
                    pillar="learning"
                    stat={learning}
                    loading={learningLoading}
                />
            </div>

            <div className="mb-6 grid grid-cols-1 gap-3 lg:grid-cols-2">
                <PillarBreakdown stat={breakdown} loading={breakdownLoading} />
                <EscCalendar stat={calendar} loading={calendarLoading} />
            </div>

            <DailyLogTable stat={dailyLog} loading={dailyLogLoading} />
        </>
    );
}
