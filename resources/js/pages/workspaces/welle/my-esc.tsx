import {
    escRate as escRateRoute,
    learning as learningRoute,
    meditation as meditationRoute,
    movement as movementRoute,
    pillarBreakdown as pillarBreakdownRoute,
} from '@/actions/App/Http/Controllers/API/Workspace/WelleStatsController';
import PageHeader from '@/components/common/PageHeader';
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
import { Sparkles } from 'lucide-react';
import { useMemo } from 'react';

interface Props {
    workspace: {
        id: number;
        name: string;
        slug: string;
    };
}

/**
 * My ESC — the signed-in user's own Extreme Self Care record.
 *
 * The cards are fetched over XHR rather than shared by the page, so the page
 * renders at once and each card skeletons on its own. The day-by-day record
 * under them is still to come.
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

                <div className="mb-6">
                    <PillarBreakdown
                        stat={breakdown}
                        loading={breakdownLoading}
                    />
                </div>

                <div className="rounded-[14px] border border-dashed border-black/10 bg-white p-10 text-center dark:border-white/10 dark:bg-zinc-900">
                    <span className="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-stone-100 dark:bg-zinc-800">
                        <Sparkles className="h-5 w-5 text-gray-400 dark:text-gray-500" />
                    </span>
                    <p className="text-[13px] font-semibold text-gray-800 dark:text-gray-100">
                        More to come here
                    </p>
                    <p className="mx-auto mt-1 max-w-md text-[12px] text-gray-400 dark:text-gray-500">
                        Your day-by-day ESC record will live under these cards.
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
