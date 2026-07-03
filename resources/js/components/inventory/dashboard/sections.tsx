import {
    Activity,
    AlertTriangle,
    ArrowLeftRight,
    ClipboardList,
    PieChart,
    RotateCw,
    ScrollText,
    ShoppingCart,
    Truck,
} from 'lucide-react';
import AlertsFeed from './alerts-feed';
import FulfillmentDonut from './fulfillment-donut';
import KpiCards from './kpi-cards';
import MovementChart from './movement-chart';
import PoStatusFunnel from './po-status-funnel';
import RecentAdjustmentsTable from './recent-adjustments-table';
import ShrinkageChart from './shrinkage-chart';
import {
    AlertsSkeleton,
    ChartSkeleton,
    DonutSkeleton,
    KpiRowSkeleton,
    TableSkeleton,
} from './skeletons';
import StatPanel from './stat-panel';
import StockHealthTable from './stock-health-table';
import TopDiscrepanciesChart from './top-discrepancies-chart';
import {
    type Fulfillment,
    type InventoryAlert,
    type InventoryKpis,
    type MovementData,
    type PoStatusSlice,
    type RecentAdjustment,
    type StockHealthRow,
    type TopDiscrepancy,
    type TrendData,
    type UpcomingDelivery,
} from './types';
import UpcomingDeliveriesTable from './upcoming-deliveries-table';
import { type DashboardRange, useInventoryStat } from './use-inventory-stat';

interface SectionProps {
    slug: string;
    range: DashboardRange;
}

const headerIcon = 'h-3.5 w-3.5 text-gray-400';

export function KpiSection({ slug, range }: SectionProps) {
    const { data, loading, error, refetch } = useInventoryStat<InventoryKpis>(
        slug,
        'kpis',
        range,
    );

    if (error) {
        return (
            <div className="flex items-center justify-between gap-3 rounded-[14px] border border-red-500/20 bg-red-50/60 px-4 py-3 dark:border-red-400/20 dark:bg-red-500/5">
                <span className="flex items-center gap-2 font-mono text-[12px] text-gray-600 dark:text-gray-300">
                    <AlertTriangle className="h-4 w-4 text-red-500" />
                    Couldn’t load KPIs.
                </span>
                <button
                    type="button"
                    onClick={refetch}
                    className="inline-flex items-center gap-1.5 rounded-[8px] border border-black/8 bg-white px-3 py-1.5 font-mono text-[11px] font-medium text-gray-600 transition-colors hover:text-gray-800 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-300"
                >
                    <RotateCw className="h-3.5 w-3.5" />
                    Retry
                </button>
            </div>
        );
    }

    if (loading || !data) return <KpiRowSkeleton />;

    return <KpiCards kpis={data} />;
}

export function MovementSection({ slug, range }: SectionProps) {
    const state = useInventoryStat<MovementData>(slug, 'movement', range);
    return (
        <StatPanel
            title="Inventory Movement"
            icon={<ArrowLeftRight className={headerIcon} />}
            className="xl:col-span-2"
            state={state}
            skeleton={<ChartSkeleton height={320} />}
        >
            {(data) => <MovementChart data={data} />}
        </StatPanel>
    );
}

export function AlertsSection({ slug, range }: SectionProps) {
    const state = useInventoryStat<InventoryAlert[]>(slug, 'alerts', range);
    return (
        <StatPanel
            title="Alerts"
            icon={<AlertTriangle className={headerIcon} />}
            count={state.data?.length}
            state={state}
            skeleton={<AlertsSkeleton />}
        >
            {(data) => <AlertsFeed alerts={data} />}
        </StatPanel>
    );
}

export function PoStatusSection({ slug, range }: SectionProps) {
    const state = useInventoryStat<PoStatusSlice[]>(slug, 'po-status', range);
    return (
        <StatPanel
            title="Purchase Order Status"
            icon={<ShoppingCart className={headerIcon} />}
            state={state}
            skeleton={<ChartSkeleton />}
        >
            {(data) => <PoStatusFunnel data={data} />}
        </StatPanel>
    );
}

export function FulfillmentSection({ slug, range }: SectionProps) {
    const state = useInventoryStat<Fulfillment>(slug, 'fulfillment', range);
    return (
        <StatPanel
            title="Fulfillment Breakdown"
            icon={<PieChart className={headerIcon} />}
            state={state}
            skeleton={<DonutSkeleton />}
        >
            {(data) => <FulfillmentDonut data={data} />}
        </StatPanel>
    );
}

export function ShrinkageSection({ slug, range }: SectionProps) {
    const state = useInventoryStat<TrendData>(slug, 'shrinkage', range);
    return (
        <StatPanel
            title="Shrinkage Trend"
            icon={<Activity className={headerIcon} />}
            state={state}
            skeleton={<ChartSkeleton height={260} />}
        >
            {(data) => <ShrinkageChart data={data} />}
        </StatPanel>
    );
}

export function StockHealthSection({ slug, range }: SectionProps) {
    const state = useInventoryStat<StockHealthRow[]>(
        slug,
        'stock-health',
        range,
    );
    return (
        <StatPanel
            title="Stock Health / Reorder"
            icon={<ClipboardList className={headerIcon} />}
            className="xl:col-span-2"
            count={state.data?.length}
            state={state}
            skeleton={<TableSkeleton rows={8} />}
        >
            {(data) => <StockHealthTable rows={data} />}
        </StatPanel>
    );
}

export function UpcomingDeliveriesSection({ slug, range }: SectionProps) {
    const state = useInventoryStat<UpcomingDelivery[]>(
        slug,
        'upcoming-deliveries',
        range,
    );
    return (
        <StatPanel
            title="Upcoming Deliveries"
            icon={<Truck className={headerIcon} />}
            count={state.data?.length}
            state={state}
            skeleton={<TableSkeleton rows={6} />}
        >
            {(data) => <UpcomingDeliveriesTable rows={data} />}
        </StatPanel>
    );
}

export function RecentAdjustmentsSection({ slug, range }: SectionProps) {
    const state = useInventoryStat<RecentAdjustment[]>(
        slug,
        'recent-adjustments',
        range,
    );
    return (
        <StatPanel
            title="Recent Physical Count Adjustments"
            icon={<ScrollText className={headerIcon} />}
            count={state.data?.length}
            state={state}
            skeleton={<TableSkeleton rows={6} />}
        >
            {(data) => <RecentAdjustmentsTable rows={data} />}
        </StatPanel>
    );
}

export function TopDiscrepanciesSection({ slug, range }: SectionProps) {
    const state = useInventoryStat<TopDiscrepancy[]>(
        slug,
        'top-discrepancies',
        range,
    );
    return (
        <StatPanel
            title="Top SKUs by Discrepancy"
            icon={<Activity className={headerIcon} />}
            state={state}
            skeleton={<ChartSkeleton height={260} />}
        >
            {(data) => <TopDiscrepanciesChart data={data} />}
        </StatPanel>
    );
}
