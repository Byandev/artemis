import PageHeader from '@/components/common/PageHeader';
import AdSpentRoasTable, {
    RoasRow,
} from '@/components/metrics/AdSpentRoasTable';
import DatePicker from '@/components/ui/date-picker';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import flatpickr from 'flatpickr';
import moment from 'moment';
import { useMemo } from 'react';
import DateOption = flatpickr.Options.DateOption;

interface Props {
    workspace: { id: number; name: string; slug: string };
    filters: { start_date: string; end_date: string };
    rows: RoasRow[];
}

export default function AdSpentSummary({ workspace, filters, rows }: Props) {
    const baseUrl = `/workspaces/${workspace.slug}/integrations/meta/ad-spent-summary`;

    const defaultDate = useMemo(
        () => [filters.start_date, filters.end_date] as never as DateOption,
        [filters.start_date, filters.end_date],
    );

    // Any control change reloads immediately — no submit button.
    const reload = (start: string, end: string) => {
        router.get(
            baseUrl,
            { start_date: start, end_date: end },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['rows', 'filters'],
            },
        );
    };

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Adspent ROAS Summary`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Adspent ROAS Summary"
                    description="Daily orders, sales, ad spend and ROAS across the selected range."
                />

                {/* Date range — right-aligned above the table. */}
                <div className="mb-4 flex justify-end">
                    <DatePicker
                        id="adspent-roas-date-range"
                        mode="range"
                        placeholder="Select date range"
                        defaultDate={defaultDate}
                        onChange={(dates) => {
                            if (dates.length === 2) {
                                reload(
                                    moment(dates[0]).format('YYYY-MM-DD'),
                                    moment(dates[1]).format('YYYY-MM-DD'),
                                );
                            }
                        }}
                    />
                </div>

                <AdSpentRoasTable rows={rows} />
            </div>
        </AppLayout>
    );
}
