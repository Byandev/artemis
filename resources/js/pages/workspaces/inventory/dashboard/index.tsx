import PageHeader from '@/components/common/PageHeader';
import {
    AlertsSection,
    FulfillmentSection,
    KpiSection,
    MovementSection,
    PoStatusSection,
    RecentAdjustmentsSection,
    ShrinkageSection,
    StockHealthSection,
    TopDiscrepanciesSection,
    UpcomingDeliveriesSection,
} from '@/components/inventory/dashboard/sections';
import { type DashboardRange } from '@/components/inventory/dashboard/use-inventory-stat';
import DatePicker from '@/components/ui/date-picker';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';
import { format, subDays } from 'date-fns';
import flatpickr from 'flatpickr';
import { useEffect, useMemo, useState } from 'react';
import DateOption = flatpickr.Options.DateOption;

interface Props {
    workspace: Workspace;
}

const today = () => format(new Date(), 'yyyy-MM-dd');
const daysAgo = (n: number) => format(subDays(new Date(), n), 'yyyy-MM-dd');
const isDate = (v: unknown): v is string =>
    typeof v === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(v);

export default function InventoryDashboard({ workspace }: Props) {
    const slug = workspace.slug;
    const storageKey = `inventory-dashboard-range:${slug}`;

    // Restore the last-picked range (persisted below) so a page refresh keeps
    // the filter instead of snapping back to the default 30 days. Computed once
    // so the picker's defaultDate stays referentially stable.
    const initialRange = useMemo<DashboardRange>(() => {
        try {
            const saved = JSON.parse(
                localStorage.getItem(storageKey) ?? 'null',
            );
            if (saved && isDate(saved.start) && isDate(saved.end)) {
                return { start: saved.start, end: saved.end };
            }
        } catch {
            // Ignore unavailable / malformed storage.
        }
        return { start: daysAgo(29), end: today() };
    }, [storageKey]);

    const [range, setRange] = useState<DashboardRange>(initialRange);
    const defaultDate = useMemo(
        () => [initialRange.start, initialRange.end] as never as DateOption,
        [initialRange],
    );

    useEffect(() => {
        try {
            localStorage.setItem(storageKey, JSON.stringify(range));
        } catch {
            // Ignore storage errors (quota / private mode).
        }
    }, [storageKey, range]);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Inventory Dashboard',
            href: `/workspaces/${slug}/inventory/dashboard`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${workspace.name} - Inventory Dashboard`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Inventory Dashboard"
                    description="Stock health, movement, purchase orders and audit at a glance."
                    stackActionsOnMobile
                >
                    <DatePicker
                        id="inventory-dashboard-range"
                        mode="range"
                        defaultDate={defaultDate}
                        onChange={(dates) => {
                            if (dates.length === 2) {
                                setRange({
                                    start: format(dates[0], 'yyyy-MM-dd'),
                                    end: format(dates[1], 'yyyy-MM-dd'),
                                });
                            }
                        }}
                    />
                </PageHeader>

                <div className="flex flex-col gap-3">
                    <KpiSection slug={slug} range={range} />

                    <AlertsSection slug={slug} range={range} />

                    <MovementSection slug={slug} range={range} />

                    <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
                        <PoStatusSection slug={slug} range={range} />
                        <FulfillmentSection slug={slug} range={range} />
                    </div>

                    <ShrinkageSection slug={slug} range={range} />

                    <div className="grid grid-cols-1 gap-3 xl:grid-cols-3">
                        <StockHealthSection slug={slug} range={range} />
                        <UpcomingDeliveriesSection slug={slug} range={range} />
                    </div>

                    <div className="grid grid-cols-1 gap-3 xl:grid-cols-2">
                        <RecentAdjustmentsSection slug={slug} range={range} />
                        <TopDiscrepanciesSection slug={slug} range={range} />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
