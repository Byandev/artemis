import AdSpentRoasTable, {
    RoasRow,
} from '@/components/metrics/AdSpentRoasTable';
import DatePicker from '@/components/ui/date-picker';
import { MultiSelect, Option } from '@/components/ui/multi-select';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import flatpickr from 'flatpickr';
import moment from 'moment';
import { useCallback, useEffect, useMemo, useState } from 'react';
import DateOption = flatpickr.Options.DateOption;

interface Filters {
    start_date: string;
    end_date: string;
    advertisers: string[];
}

interface Props {
    workspace: { id: number; name: string; slug: string };
    /** The range the URL asked for, already resolved by the server. */
    filters: Filters;
    advertiserOptions: Option[];
}

export default function AdSpentSummary({
    workspace,
    filters: initialFilters,
    advertiserOptions,
}: Props) {
    const baseUrl = `/workspaces/${workspace.slug}/sales-marketing/ad-spent-summary`;
    const [filters, setFilters] = useState<Filters>(initialFilters);
    // Null until the first response, so the table skeletons instead of
    // claiming there is nothing for the range.
    const [rows, setRows] = useState<RoasRow[] | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(false);
    // Bumping the nonce re-runs the fetch — that's the retry.
    const [nonce, setNonce] = useState(0);
    const retry = useCallback(() => setNonce((n) => n + 1), []);

    const defaultDate = useMemo(
        () =>
            [
                initialFilters.start_date,
                initialFilters.end_date,
            ] as never as DateOption,
        [initialFilters.start_date, initialFilters.end_date],
    );

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError(false);

        axios
            .get<{ filters: Filters; rows: RoasRow[] }>(
                `/api/workspaces/${workspace.slug}/sales-marketing/ad-spent-summary`,
                { params: filters, signal: controller.signal },
            )
            .then((res) => setRows(res.data.rows))
            .catch((err) => {
                if (axios.isCancel(err)) return;
                console.error('ad spent summary: load failed', err);
                setError(true);
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });

        return () => controller.abort();
    }, [workspace.slug, filters, nonce]);

    // Any control change reloads immediately — no submit button. Unspecified
    // params fall back to the current filter state. The URL follows along so a
    // reload or a shared link opens on the same range.
    const reload = (next: Partial<Filters>) => {
        const merged = { ...filters, ...next };
        setFilters(merged);

        const qs = new URLSearchParams({
            start_date: merged.start_date,
            end_date: merged.end_date,
        });
        merged.advertisers.forEach((a) => qs.append('advertisers[]', a));

        router.replace({
            url: `${baseUrl}?${qs}`,
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Adspent ROAS Summary`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                {/* Page title on the left, filters on the right. */}
                <div className="mt-4 mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h1 className="my-0! text-[22px]! font-semibold tracking-tight text-gray-800 dark:text-gray-100">
                        Ad Spent Summary
                    </h1>
                    <div className="flex flex-wrap items-center gap-2">
                        <MultiSelect
                            options={advertiserOptions}
                            selected={filters.advertisers}
                            onChange={(next) => reload({ advertisers: next })}
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
                                        start_date: moment(dates[0]).format(
                                            'YYYY-MM-DD',
                                        ),
                                        end_date: moment(dates[1]).format(
                                            'YYYY-MM-DD',
                                        ),
                                    });
                                }
                            }}
                        />
                    </div>
                </div>

                {error && (
                    <p className="mb-3 font-mono text-[11px] text-gray-400 dark:text-gray-500">
                        Couldn't load the summary.{' '}
                        <button
                            onClick={retry}
                            className="text-brand-600 transition-colors hover:text-brand-700 dark:text-brand-400"
                        >
                            Retry
                        </button>
                    </p>
                )}

                {rows === null ? (
                    !error && (
                        <Skeleton className="h-[360px] w-full rounded-[14px]" />
                    )
                ) : (
                    <div
                        className={`transition-opacity ${loading ? 'opacity-50' : ''}`}
                    >
                        <AdSpentRoasTable rows={rows} />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
