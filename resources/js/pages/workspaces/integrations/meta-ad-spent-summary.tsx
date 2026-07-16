import AdSpentRoasTable, {
    RoasRow,
} from '@/components/metrics/AdSpentRoasTable';
import {
    DashboardTab,
    DashboardTabNav,
} from '@/components/sales-marketing/dashboard-tabs';
import DatePicker from '@/components/ui/date-picker';
import { MultiSelect, Option } from '@/components/ui/multi-select';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import flatpickr from 'flatpickr';
import moment from 'moment';
import { useMemo, useState } from 'react';
import DateOption = flatpickr.Options.DateOption;

interface Props {
    workspace: { id: number; name: string; slug: string };
    filters: { start_date: string; end_date: string; advertisers: string[] };
    advertiserOptions: Option[];
    rows: RoasRow[];
    // S&M dashboard tabs — this page is the "Ad Spent Summary" tab.
    tabs?: DashboardTab[];
    activeTab?: string;
}

export default function AdSpentSummary({
    workspace,
    filters,
    advertiserOptions,
    rows,
    tabs,
    activeTab,
}: Props) {
    const baseUrl = `/workspaces/${workspace.slug}/sales-marketing/dashboard/ad-spent-summary`;
    const hasTabs = !!tabs && tabs.length > 0;

    const [advertisers, setAdvertisers] = useState<string[]>(
        filters.advertisers ?? [],
    );

    const defaultDate = useMemo(
        () => [filters.start_date, filters.end_date] as never as DateOption,
        [filters.start_date, filters.end_date],
    );

    // Any control change reloads immediately — no submit button. Unspecified
    // params fall back to the current filter state.
    const reload = (next: {
        start?: string;
        end?: string;
        advertisers?: string[];
    }) => {
        router.get(
            baseUrl,
            {
                start_date: next.start ?? filters.start_date,
                end_date: next.end ?? filters.end_date,
                advertisers: next.advertisers ?? advertisers,
            },
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
                {hasTabs && <DashboardTabNav tabs={tabs!} active={activeTab} />}

                {/* Page title on the left, filters on the right. */}
                <div className="mt-4 mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h1 className="my-0! text-[22px]! font-semibold tracking-tight text-gray-800 dark:text-gray-100">
                        Ad Spent Summary
                    </h1>
                    <div className="flex flex-wrap items-center gap-2">
                        <MultiSelect
                            options={advertiserOptions}
                            selected={advertisers}
                            onChange={(next) => {
                                setAdvertisers(next);
                                reload({ advertisers: next });
                            }}
                            placeholder="All advertisers"
                            compact
                            className="w-56"
                        />
                        <DatePicker
                            id="adspent-roas-date-range"
                            mode="range"
                            placeholder="Select date range"
                            defaultDate={defaultDate}
                            onChange={(dates) => {
                                if (dates.length === 2) {
                                    reload({
                                        start: moment(dates[0]).format(
                                            'YYYY-MM-DD',
                                        ),
                                        end: moment(dates[1]).format(
                                            'YYYY-MM-DD',
                                        ),
                                    });
                                }
                            }}
                        />
                    </div>
                </div>

                <AdSpentRoasTable rows={rows} />
            </div>
        </AppLayout>
    );
}
