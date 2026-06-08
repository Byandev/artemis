import AskRtsWidget, { RtsData } from '@/components/ai/AskRtsWidget';
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

// Hydrate filters/date range from the URL so they survive a browser refresh.
function parseStateFromUrl(): { dateRange: string[]; filter: FilterValue } {
    const params = new URLSearchParams(window.location.search);
    const start = params.get('startDate');
    const end = params.get('endDate');

    const dateRange =
        start && end
            ? [start, end]
            : [
                  moment().startOf('month').format('YYYY-MM-DD'),
                  moment().endOf('month').format('YYYY-MM-DD'),
              ];

    const filter: FilterValue = {
        teamIds: [],
        productIds: [],
        shopIds: [],
        pageIds: [],
        userIds: [],
    };
    FILTER_KEYS.forEach((key) => {
        const raw = params.get(key);
        if (raw) {
            filter[key] = raw.split(',').filter(Boolean);
        }
    });

    return { dateRange, filter };
}

export default function Analytics({ workspace }: Props) {
    const initialState = useMemo(() => parseStateFromUrl(), []);
    const [dateRange, setDateRange] = useState(initialState.dateRange);
    const [filter, setFilter] = useState<FilterValue>(initialState.filter);

    // Keep the URL in sync so refreshing the page restores the current filters.
    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        params.set('startDate', dateRange[0]);
        params.set('endDate', dateRange[1]);
        FILTER_KEYS.forEach((key) => {
            const value = filter[key];
            if (value.length) {
                params.set(key, value.join(','));
            } else {
                params.delete(key);
            }
        });
        window.history.replaceState(
            null,
            '',
            `${window.location.pathname}?${params.toString()}`,
        );
    }, [dateRange, filter]);

    const queryParams: RtsQueryParams = useMemo(
        () => ({
            startDate: dateRange[0],
            endDate: dateRange[1],
            pageIds: filter.pageIds,
            shopIds: filter.shopIds,
        }),
        [dateRange, filter],
    );

    const [rtsData, setRtsData] = useState<RtsData>({
        price: [],
        products: [],
        riders: [],
        customerRisk: [],
        provinces: [],
        orderFrequency: [],
    });

    const onPriceLoaded = useCallback(
        (d: object[]) => setRtsData((prev) => ({ ...prev, price: d })),
        [],
    );
    const onProductsLoaded = useCallback(
        (d: object[]) => setRtsData((prev) => ({ ...prev, products: d })),
        [],
    );
    const onRidersLoaded = useCallback(
        (d: object[]) => setRtsData((prev) => ({ ...prev, riders: d })),
        [],
    );
    const onCustomerRiskLoaded = useCallback(
        (d: object[]) => setRtsData((prev) => ({ ...prev, customerRisk: d })),
        [],
    );
    const onProvincesLoaded = useCallback(
        (d: object[]) => setRtsData((prev) => ({ ...prev, provinces: d })),
        [],
    );
    const onOrderFreqLoaded = useCallback(
        (d: object[]) => setRtsData((prev) => ({ ...prev, orderFrequency: d })),
        [],
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
                        onDataLoaded={onPriceLoaded}
                    />
                    <DeliveryAttemptsCard
                        workspaceSlug={workspace.slug}
                        queryParams={queryParams}
                    />
                </div>

                <CxRtsCard
                    workspaceSlug={workspace.slug}
                    queryParams={queryParams}
                    onDataLoaded={onCustomerRiskLoaded}
                />
                <ProductCard
                    workspaceSlug={workspace.slug}
                    queryParams={queryParams}
                    onDataLoaded={onProductsLoaded}
                />
                <RiderCard
                    workspaceSlug={workspace.slug}
                    queryParams={queryParams}
                    onDataLoaded={onRidersLoaded}
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
                    onDataLoaded={onOrderFreqLoaded}
                />
                <LocationCard
                    workspaceSlug={workspace.slug}
                    queryParams={queryParams}
                    onDataLoaded={onProvincesLoaded}
                />
            </div>

            <AskRtsWidget
                workspace={workspace}
                dateRange={dateRange}
                data={rtsData}
            />
        </AppLayout>
    );
}
