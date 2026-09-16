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
import {
    DailyLogTable,
    type DailyLogStat,
} from '@/components/welle/DailyLogTable';
import {
    EscCalendar,
    type EscCalendarStat,
} from '@/components/welle/EscCalendar';
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
import { useMemo } from 'react';

interface Props {
    workspace: {
        id: number;
        name: string;
        slug: string;
    };
}

/**
 * My ESC — the signed-in user's own Extreme Self Care record: the month's
 * figures as cards, the three pillars against each other, and the month itself
 * day by day.
 *
 * Every piece is fetched over XHR rather than shared by the page, so the page
 * renders at once and each one skeletons on its own.
 */
export default function MyEsc({ workspace }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'My ESC',
            href: `/workspaces/${workspace.slug}/welle/my-esc`,
        },
    ];

    // The hook keys its request on the URL, and a route helper builds a new
    // string on every render — memoised so each card is fetched once rather
    // than on every one.
    const urls = useMemo(
        () => ({
            escRate: escRateRoute({ workspace: workspace.slug }).url,
            movement: movementRoute({ workspace: workspace.slug }).url,
            meditation: meditationRoute({ workspace: workspace.slug }).url,
            learning: learningRoute({ workspace: workspace.slug }).url,
            pillarBreakdown: pillarBreakdownRoute({ workspace: workspace.slug })
                .url,
            calendar: calendarRoute({ workspace: workspace.slug }).url,
            dailyLog: dailyLogRoute({ workspace: workspace.slug }).url,
        }),
        [workspace.slug],
    );

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
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My ESC" />

            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 sm:p-6">
                <PageHeader
                    title="My ESC"
                    description="Your own Extreme Self Care record from Welle"
                    divider={false}
                />

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
                    <PillarBreakdown
                        stat={breakdown}
                        loading={breakdownLoading}
                    />
                    <EscCalendar stat={calendar} loading={calendarLoading} />
                </div>

                <DailyLogTable stat={dailyLog} loading={dailyLogLoading} />
            </div>
        </AppLayout>
    );
}
