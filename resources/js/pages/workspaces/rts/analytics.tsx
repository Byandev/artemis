import AppLayout from '@/layouts/app-layout';
import PageHeader from '@/components/common/PageHeader';
import DatePicker from '@/components/ui/date-picker';
import Filters, { FilterValue } from '@/components/filters/Filters';
import CxRtsCard from '@/components/rts/CxRtsCard';
import DeliveryAttemptsCard from '@/components/rts/DeliveryAttemptsCard';
import LocationCard from '@/components/rts/LocationCard';
import PriceCard from '@/components/rts/PriceCard';
import ProductCard from '@/components/rts/ProductCard';
import AdCard from '@/components/rts/AdCard';
import ConfirmedByCard from '@/components/rts/ConfirmedByCard';
import OrderFrequencyCard from '@/components/rts/OrderFrequencyCard';
import RiderCard from '@/components/rts/RiderCard';
import { RtsQueryParams } from '@/components/rts/rts-shared';
import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';
import { formatDate } from 'date-fns';
import flatpickr from 'flatpickr';
import DateOption = flatpickr.Options.DateOption;
import moment from 'moment';
import { useCallback, useMemo, useState } from 'react';
import AskRtsWidget, { RtsData } from '@/components/ai/AskRtsWidget';

interface Props {
    workspace: Workspace;
}

export default function Analytics({ workspace }: Props) {
    const [dateRange, setDateRange] = useState([
        moment().startOf('month').format('YYYY-MM-DD'),
        moment().endOf('month').format('YYYY-MM-DD'),
    ]);
    const [filter, setFilter] = useState<FilterValue>({
        teamIds: [], productIds: [], shopIds: [], pageIds: [], userIds: [],
    });

    const queryParams: RtsQueryParams = useMemo(() => ({
        startDate: dateRange[0],
        endDate: dateRange[1],
        pageIds: filter.pageIds,
        shopIds: filter.shopIds,
    }), [dateRange, filter]);

    const [rtsData, setRtsData] = useState<RtsData>({
        price: [], products: [], riders: [], customerRisk: [], provinces: [], orderFrequency: [],
    });

    const onPriceLoaded        = useCallback((d: object[]) => setRtsData(prev => ({ ...prev, price: d })), []);
    const onProductsLoaded     = useCallback((d: object[]) => setRtsData(prev => ({ ...prev, products: d })), []);
    const onRidersLoaded       = useCallback((d: object[]) => setRtsData(prev => ({ ...prev, riders: d })), []);
    const onCustomerRiskLoaded = useCallback((d: object[]) => setRtsData(prev => ({ ...prev, customerRisk: d })), []);
    const onProvincesLoaded    = useCallback((d: object[]) => setRtsData(prev => ({ ...prev, provinces: d })), []);
    const onOrderFreqLoaded    = useCallback((d: object[]) => setRtsData(prev => ({ ...prev, orderFrequency: d })), []);

    return (
        <AppLayout>
            <Head title={`${workspace.name} — RTS Analytics`} />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title="RTS Analytics"
                    description={`${formatDate(new Date(dateRange[0]), 'MMM d')} – ${formatDate(new Date(dateRange[1]), 'MMM d, yyyy')}`}
                >
                    <Filters workspace={workspace} onChange={setFilter} />
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
                    <PriceCard workspaceSlug={workspace.slug} queryParams={queryParams} onDataLoaded={onPriceLoaded} />
                    <DeliveryAttemptsCard workspaceSlug={workspace.slug} queryParams={queryParams} />
                </div>

                <CxRtsCard workspaceSlug={workspace.slug} queryParams={queryParams} onDataLoaded={onCustomerRiskLoaded} />
                <ProductCard workspaceSlug={workspace.slug} queryParams={queryParams} onDataLoaded={onProductsLoaded} />
                <RiderCard workspaceSlug={workspace.slug} queryParams={queryParams} onDataLoaded={onRidersLoaded} />
                <ConfirmedByCard workspaceSlug={workspace.slug} queryParams={queryParams} />
                <AdCard workspaceSlug={workspace.slug} queryParams={queryParams} />
                <OrderFrequencyCard workspaceSlug={workspace.slug} queryParams={queryParams} onDataLoaded={onOrderFreqLoaded} />
                <LocationCard workspaceSlug={workspace.slug} queryParams={queryParams} onDataLoaded={onProvincesLoaded} />
            </div>

            <AskRtsWidget workspace={workspace} dateRange={dateRange} data={rtsData} />
        </AppLayout>
    );
}
