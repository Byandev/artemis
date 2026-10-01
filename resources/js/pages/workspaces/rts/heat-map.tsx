import PageHeader from '@/components/common/PageHeader';
import Filters, { FilterValue } from '@/components/filters/Filters';
import RtsHeatMap from '@/components/rts/heat-map/RtsHeatMap';
import { RtsQueryParams } from '@/components/rts/rts-shared';
import DatePicker from '@/components/ui/date-picker';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';
import { formatDate } from 'date-fns';
import flatpickr from 'flatpickr';
import moment from 'moment';
import { useEffect, useMemo, useState } from 'react';
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

const STORAGE_KEY_PREFIX = 'rts-heat-map-state:v2:';

type PersistedState = { dateRange: string[]; filter: FilterValue };

// A trailing window rather than the current month: on the 1st or 2nd of a month
// there is almost nothing delivered or returned yet, and a heat map that opens
// blank reads as broken.
function defaultState(): PersistedState {
    return {
        dateRange: [
            moment().subtract(29, 'days').format('YYYY-MM-DD'),
            moment().format('YYYY-MM-DD'),
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

export default function HeatMap({ workspace }: Props) {
    const initialState = useMemo(
        () => loadState(workspace.slug),
        [workspace.slug],
    );
    const [dateRange, setDateRange] = useState(initialState.dateRange);
    const [filter, setFilter] = useState<FilterValue>(initialState.filter);

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
            teamIds: filter.teamIds,
        }),
        [dateRange, filter],
    );

    return (
        <AppLayout>
            <Head title={`${workspace.name} — RTS Heat Map`} />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="RTS Heat Map"
                    description={`${formatDate(new Date(dateRange[0]), 'MMM d')} – ${formatDate(new Date(dateRange[1]), 'MMM d, yyyy')}`}
                    stackActionsOnMobile
                >
                    <Filters
                        workspace={workspace}
                        onChange={setFilter}
                        initialValue={initialState.filter}
                    />
                    <DatePicker
                        id="rts-heat-map-date-range"
                        mode="range"
                        onChange={(dates) => {
                            if (dates.length === 2) {
                                setDateRange([
                                    moment(dates[0]).format('YYYY-MM-DD'),
                                    moment(dates[1]).format('YYYY-MM-DD'),
                                ]);
                            } else if (dates.length === 0) {
                                setDateRange(defaultState().dateRange);
                            }
                        }}
                        defaultDate={dateRange as never as DateOption}
                    />
                </PageHeader>

                <RtsHeatMap
                    workspaceSlug={workspace.slug}
                    queryParams={queryParams}
                />
            </div>
        </AppLayout>
    );
}
