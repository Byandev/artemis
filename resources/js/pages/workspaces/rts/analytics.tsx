import AppLayout from '@/layouts/app-layout';
import PageHeader from '@/components/common/PageHeader';
import DatePicker from '@/components/ui/date-picker';
import Filters, { FilterValue } from '@/components/filters/Filters';
import CxRtsCard from '@/components/rts/CxRtsCard';
import DeliveryAttemptsCard from '@/components/rts/DeliveryAttemptsCard';
import LocationCard from '@/components/rts/LocationCard';
import PriceCard from '@/components/rts/PriceCard';
import ProductCard from '@/components/rts/ProductCard';
import ConfirmedByCard from '@/components/rts/ConfirmedByCard';
import RiderCard from '@/components/rts/RiderCard';
import { RtsQueryParams } from '@/components/rts/rts-shared';
import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';
import { formatDate } from 'date-fns';
import flatpickr from 'flatpickr';
import DateOption = flatpickr.Options.DateOption;
import moment from 'moment';
import { useMemo, useState } from 'react';

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
                    <PriceCard workspaceSlug={workspace.slug} queryParams={queryParams} />
                    <DeliveryAttemptsCard workspaceSlug={workspace.slug} queryParams={queryParams} />
                </div>

                <CxRtsCard workspaceSlug={workspace.slug} queryParams={queryParams} />
                <ProductCard workspaceSlug={workspace.slug} queryParams={queryParams} />
                <RiderCard workspaceSlug={workspace.slug} queryParams={queryParams} />
                <ConfirmedByCard workspaceSlug={workspace.slug} queryParams={queryParams} />
                <LocationCard workspaceSlug={workspace.slug} queryParams={queryParams} />
            </div>
        </AppLayout>
    );
}
