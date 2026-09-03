import { Skeleton } from '@/components/ui/skeleton';
import {
    type LucideIcon,
    MessageSquare,
    Package,
    RotateCcw,
    Send,
    TrendingUp,
} from 'lucide-react';
import { useParcelJourneyData } from './use-parcel-journey-data';

interface Card {
    label: string;
    /** Path segment under .../rts/parcel-journey/. */
    endpoint: string;
    icon: LucideIcon;
    iconClass: string;
    bgClass: string;
}

const CARDS: Card[] = [
    {
        label: 'Tracked Orders',
        endpoint: 'kpi/tracked-orders',
        icon: Package,
        iconClass: 'text-blue-500 dark:text-blue-400',
        bgClass: 'bg-blue-50 dark:bg-blue-500/10',
    },
    {
        label: 'SMS Sent',
        endpoint: 'kpi/sms-sent',
        icon: Send,
        iconClass: 'text-amber-500 dark:text-amber-400',
        bgClass: 'bg-amber-50 dark:bg-amber-500/10',
    },
    {
        label: 'Chat Sent',
        endpoint: 'kpi/chat-sent',
        icon: MessageSquare,
        iconClass: 'text-violet-500 dark:text-violet-400',
        bgClass: 'bg-violet-50 dark:bg-violet-500/10',
    },
    {
        label: 'Total Sent',
        endpoint: 'kpi/total-sent',
        icon: TrendingUp,
        iconClass: 'text-emerald-500 dark:text-emerald-400',
        bgClass: 'bg-emerald-50 dark:bg-emerald-500/10',
    },
];

/**
 * The four parcel journey figures, each on its own request. They used to ride
 * along with the Inertia page render, which meant the whole page — templates
 * and per-shop table included — waited on six aggregate queries before showing
 * anything.
 */
export default function ParcelJourneyStatCards({
    workspaceSlug,
    startDate,
    endDate,
}: {
    workspaceSlug: string;
    startDate: string;
    endDate: string;
}) {
    return (
        <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {CARDS.map((card) => (
                <StatCard
                    key={card.endpoint}
                    card={card}
                    workspaceSlug={workspaceSlug}
                    startDate={startDate}
                    endDate={endDate}
                />
            ))}
        </div>
    );
}

function StatCard({
    card,
    workspaceSlug,
    startDate,
    endDate,
}: {
    card: Card;
    workspaceSlug: string;
    startDate: string;
    endDate: string;
}) {
    const { data, loading, error, refetch } = useParcelJourneyData<{
        value: number;
    }>(workspaceSlug, card.endpoint, {
        start_date: startDate,
        end_date: endDate,
    });
    const value = data?.value ?? null;
    const Icon = card.icon;

    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                        {card.label}
                    </p>
                    {/* The skeleton keeps the card's height, so changing the
                        date range doesn't reflow the page — and a stale number
                        can't be misread as the fresh one. */}
                    {loading ? (
                        <Skeleton className="mt-1.5 h-[26px] w-20" />
                    ) : error || value === null ? (
                        <>
                            <p className="mt-1.5 text-[22px] font-semibold tracking-tight text-gray-300 dark:text-gray-600">
                                —
                            </p>
                            <button
                                type="button"
                                onClick={refetch}
                                className="mt-1 flex cursor-pointer items-center gap-1.5 text-[11px] text-red-500 hover:underline dark:text-red-400"
                            >
                                <RotateCcw className="h-3 w-3" />
                                Failed — retry
                            </button>
                        </>
                    ) : (
                        <p className="mt-1.5 text-[22px] font-semibold tracking-tight text-gray-800 tabular-nums dark:text-gray-100">
                            {value.toLocaleString()}
                        </p>
                    )}
                </div>
                <div className={`rounded-[10px] p-2 ${card.bgClass}`}>
                    <Icon className={`h-4 w-4 ${card.iconClass}`} />
                </div>
            </div>
        </div>
    );
}
