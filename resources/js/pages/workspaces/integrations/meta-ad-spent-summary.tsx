import AdSpentRoasTable, {
    RoasRow,
    ViewColumn,
} from '@/components/metrics/AdSpentRoasTable';
import PageHeader from '@/components/common/PageHeader';
import DatePicker from '@/components/ui/date-picker';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import flatpickr from 'flatpickr';
import moment from 'moment';
import { useMemo } from 'react';
import DateOption = flatpickr.Options.DateOption;

interface Props {
    workspace: { id: number; name: string; slug: string };
    filters: { start_date: string; end_date: string; views: string[] };
    rows: RoasRow[];
}

// Selectable status views (checkboxes) → matches AdSpentSummaryController keys.
const STATUS_VIEWS: ViewColumn[] = [
    { key: 'shipped_out', label: 'Shipped Out' },
    { key: 'odz_inc', label: 'ODZ/INC' },
    { key: 'in_transit', label: 'In Transit' },
    { key: 'on_delivery', label: 'On Delivery' },
    { key: 'returned', label: 'Returned' },
    { key: 'delivered', label: 'Delivered' },
];

export default function AdSpentSummary({ workspace, filters, rows }: Props) {
    const baseUrl = `/workspaces/${workspace.slug}/integrations/meta/ad-spent-summary`;

    const defaultDate = useMemo(
        () => [filters.start_date, filters.end_date] as never as DateOption,
        [filters.start_date, filters.end_date],
    );

    const activeViews = useMemo(
        () => STATUS_VIEWS.filter((v) => filters.views.includes(v.key)),
        [filters.views],
    );

    // Any control change reloads immediately — no submit button.
    const reload = (next: {
        start?: string;
        end?: string;
        views?: string[];
    }) => {
        router.get(
            baseUrl,
            {
                start_date: next.start ?? filters.start_date,
                end_date: next.end ?? filters.end_date,
                views: next.views ?? filters.views,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['rows', 'filters'],
            },
        );
    };

    const toggleView = (key: string) => {
        const views = filters.views.includes(key)
            ? filters.views.filter((v) => v !== key)
            : [...filters.views, key];
        reload({ views });
    };

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Adspent ROAS Summary`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Adspent ROAS Summary"
                    description="Daily orders, ad spend and ROAS across the selected range."
                />

                {/* Filters */}
                <div className="mb-4 flex flex-col gap-4 rounded-xl border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900">
                    <div className="flex flex-wrap items-end gap-4">
                        <div className="flex flex-col gap-1.5">
                            <span className="text-[11px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                                Date Range
                            </span>
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

                    {/* Status view checkboxes */}
                    <div className="flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-black/6 pt-3 dark:border-white/6">
                        {STATUS_VIEWS.map((v) => {
                            const checked = filters.views.includes(v.key);
                            return (
                                <label
                                    key={v.key}
                                    className="flex cursor-pointer items-center gap-2 text-[12px] text-gray-700 dark:text-gray-300"
                                >
                                    <input
                                        type="checkbox"
                                        checked={checked}
                                        onChange={() => toggleView(v.key)}
                                        className="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-600"
                                    />
                                    View {v.label}
                                </label>
                            );
                        })}
                    </div>
                </div>

                <AdSpentRoasTable rows={rows} views={activeViews} />
            </div>
        </AppLayout>
    );
}
