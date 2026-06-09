import PageHeader from '@/components/common/PageHeader';
import Filters, { FilterValue } from '@/components/filters/Filters';
import AdCard from '@/components/rts/AdCard';
import ConfirmedByCard from '@/components/rts/ConfirmedByCard';
import CxRtsCard from '@/components/rts/CxRtsCard';
import DeliveryAttemptsCard from '@/components/rts/DeliveryAttemptsCard';
import LocationCard from '@/components/rts/LocationCard';
import OrderFrequencyCard from '@/components/rts/OrderFrequencyCard';
import PriceCard from '@/components/rts/PriceCard';
import ProductCard from '@/components/rts/ProductCard';
import RiderCard from '@/components/rts/RiderCard';
import { RtsQueryParams } from '@/components/rts/rts-shared';
import DatePicker from '@/components/ui/date-picker';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';
import { formatDate } from 'date-fns';
import flatpickr from 'flatpickr';
import moment from 'moment';
import { useMemo, useState } from 'react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import DateOption = flatpickr.Options.DateOption;

interface Props {
    workspace: Workspace;
}

const FILTER_KEYS: (keyof FilterValue)[] = [
    'teamIds',
    'productIds',
    'shopIds',
    'pageIds',
    'userIds',
];

const STORAGE_KEY_PREFIX = 'rts-analytics-state:';

type PersistedState = { dateRange: string[]; filter: FilterValue };

function defaultState(): PersistedState {
    return {
        dateRange: [
            moment().startOf('month').format('YYYY-MM-DD'),
            moment().endOf('month').format('YYYY-MM-DD'),
        ],
        filter: {
            teamIds: [],
            productIds: [],
            shopIds: [],
            pageIds: [],
            userIds: [],
        },
    };
}

// Hydrate filters/date range from localStorage so they survive a browser
// refresh (without leaking into the URL). Scoped per workspace.
function loadState(workspaceSlug: string): PersistedState {
    const fallback = defaultState();

    try {
        const raw = localStorage.getItem(STORAGE_KEY_PREFIX + workspaceSlug);
        if (!raw) return fallback;

        const parsed = JSON.parse(raw);
        const dateRange =
            Array.isArray(parsed?.dateRange) && parsed.dateRange.length === 2
                ? parsed.dateRange.map(String)
                : fallback.dateRange;

        const filter = { ...fallback.filter };
        FILTER_KEYS.forEach((key) => {
            if (Array.isArray(parsed?.filter?.[key])) {
                filter[key] = parsed.filter[key];
            }
        });

        return { dateRange, filter };
    } catch {
        return fallback;
    }
}

export default function Analytics({ workspace }: Props) {
    const initialState = useMemo(
        () => loadState(workspace.slug),
        [workspace.slug],
    );
    const [dateRange, setDateRange] = useState(initialState.dateRange);
    const [filter, setFilter] = useState<FilterValue>(initialState.filter);

    // Persist to localStorage so a refresh restores the current filters.
    useEffect(() => {
        try {
            localStorage.setItem(
                STORAGE_KEY_PREFIX + workspace.slug,
                JSON.stringify({ dateRange, filter }),
            );
        } catch {
            // Ignore storage errors (e.g. quota / private mode).
        }
    }, [workspace.slug, dateRange, filter]);

    const queryParams: RtsQueryParams = useMemo(
        () => ({
            startDate: dateRange[0],
            endDate: dateRange[1],
            pageIds: filter.pageIds,
            shopIds: filter.shopIds,
        }),
        [dateRange, filter],
    );

    return (
        <AppLayout>
            <Head title={`${workspace.name} — RTS Analytics`} />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="RTS Analytics"
                    description={`${formatDate(new Date(dateRange[0]), 'MMM d')} – ${formatDate(new Date(dateRange[1]), 'MMM d, yyyy')}`}
                    stackActionsOnMobile
                >
                    <Filters
                        workspace={workspace}
                        onChange={setFilter}
                        initialValue={initialState.filter}
                    />
                    <DatePicker
                        id="rts-date-range"
                        mode="range"
                        onChange={(dates) => {
                            if (dates.length === 2) {
                                setDateRange([
                                    moment(dates[0]).format('YYYY-MM-DD'),
                                    moment(dates[1]).format('YYYY-MM-DD'),
                                ]);
                            }
                        }}
                        defaultDate={dateRange as never as DateOption}
                    />
                </PageHeader>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <PriceCard
                        workspaceSlug={workspace.slug}
                        queryParams={queryParams}
                    />
                    <DeliveryAttemptsCard
                        workspaceSlug={workspace.slug}
                        queryParams={queryParams}
                    />
                </div>

                <CxRtsCard
                    workspaceSlug={workspace.slug}
                    queryParams={queryParams}
                />
                <ProductCard
                    workspaceSlug={workspace.slug}
                    queryParams={queryParams}
                />
                <RiderCard
                    workspaceSlug={workspace.slug}
                    queryParams={queryParams}
                />
                <ConfirmedByCard
                    workspaceSlug={workspace.slug}
                    queryParams={queryParams}
                />
                <AdCard
                    workspaceSlug={workspace.slug}
                    queryParams={queryParams}
                />
                <OrderFrequencyCard
                    workspaceSlug={workspace.slug}
                    queryParams={queryParams}
                />
                <LocationCard
                    workspaceSlug={workspace.slug}
                    queryParams={queryParams}
                />
            </div>
        </AppLayout>
    );
}
