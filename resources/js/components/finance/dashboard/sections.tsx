import {
    ArrowLeftRight,
    Gauge,
    ListOrdered,
    PieChart,
    TrendingDown,
    TrendingUp,
    Wallet2,
} from 'lucide-react';
import BalanceHistoryChart from './balance-history-chart';
import CashFlowChart from './cash-flow-chart';
import CategoryDonut from './category-donut';
import KpiCards from './kpi-cards';
import ProfitabilityTiles from './profitability-tiles';
import ReconciliationPanel from './reconciliation-panel';
import {
    ChartSkeleton,
    DonutSkeleton,
    KpiRowSkeleton,
    ReconciliationSkeleton,
    TableSkeleton,
} from './skeletons';
import StatPanel, { EmptyState } from './stat-panel';
import TopMovementsTable from './top-movements-table';
import {
    type BalanceHistory,
    type CashFlowSeries,
    type CategorySlice,
    type FinanceKpis,
    type Profitability,
    type Reconciliation,
    type TopMovement,
} from './types';
import { type DashboardRange, useFinanceStat } from './use-finance-stat';

interface SectionProps {
    slug: string;
    range: DashboardRange;
}

const headerIcon = 'h-3.5 w-3.5 text-gray-400';

export function KpiSection({ slug, range }: SectionProps) {
    const { data, loading, error, refetch } = useFinanceStat<FinanceKpis>(
        slug,
        'kpis',
        range,
    );

    if (error) {
        return (
            <div className="rounded-[14px] border border-red-500/20 bg-red-50/60 px-4 py-3 dark:border-red-400/20 dark:bg-red-500/5">
                <button
                    type="button"
                    onClick={refetch}
                    className="font-mono text-[12px] text-red-600 dark:text-red-300"
                >
                    Couldn’t load KPIs — retry
                </button>
            </div>
        );
    }

    if (loading || !data) return <KpiRowSkeleton />;

    return <KpiCards kpis={data} />;
}

export function CashFlowSection({ slug, range }: SectionProps) {
    const state = useFinanceStat<CashFlowSeries>(slug, 'cash-flow', range);
    return (
        <StatPanel
            title="Cash Flow"
            icon={<ArrowLeftRight className={headerIcon} />}
            state={state}
            skeleton={<ChartSkeleton height={300} />}
            isEmpty={(d) => d.points.length === 0}
        >
            {(d) => <CashFlowChart data={d} />}
        </StatPanel>
    );
}

export function BalanceHistorySection({ slug, range }: SectionProps) {
    const state = useFinanceStat<BalanceHistory>(
        slug,
        'balance-history',
        range,
    );
    return (
        <StatPanel
            title="Cumulative Net Cash Flow"
            icon={<Wallet2 className={headerIcon} />}
            state={state}
            skeleton={<ChartSkeleton height={300} />}
            isEmpty={(d) => d.points.length === 0}
        >
            {(d) => <BalanceHistoryChart data={d} />}
        </StatPanel>
    );
}

export function ExpenseBreakdownSection({ slug, range }: SectionProps) {
    const state = useFinanceStat<CategorySlice[]>(
        slug,
        'expense-breakdown',
        range,
    );
    return (
        <StatPanel
            title="Expenses by Category"
            icon={<TrendingDown className={headerIcon} />}
            state={state}
            skeleton={<DonutSkeleton />}
            isEmpty={(d) => d.length === 0}
            emptyMessage="No expenses in this period."
        >
            {(d) => <CategoryDonut data={d} totalLabel="Expenses" />}
        </StatPanel>
    );
}

export function IncomeBreakdownSection({ slug, range }: SectionProps) {
    const state = useFinanceStat<CategorySlice[]>(
        slug,
        'income-breakdown',
        range,
    );
    return (
        <StatPanel
            title="Income by Source"
            icon={<TrendingUp className={headerIcon} />}
            state={state}
            skeleton={<DonutSkeleton />}
            isEmpty={(d) => d.length === 0}
            emptyMessage="No income in this period."
        >
            {(d) => <CategoryDonut data={d} totalLabel="Income" />}
        </StatPanel>
    );
}

export function TopMovementsSection({ slug, range }: SectionProps) {
    const state = useFinanceStat<TopMovement[]>(slug, 'top-movements', range);
    return (
        <StatPanel
            title="Top Movements"
            icon={<ListOrdered className={headerIcon} />}
            state={state}
            skeleton={<TableSkeleton rows={8} />}
            isEmpty={(d) => d.length === 0}
            emptyMessage="No transactions in this period."
        >
            {(d) => <TopMovementsTable data={d} />}
        </StatPanel>
    );
}

export function ProfitabilitySection({ slug, range }: SectionProps) {
    const state = useFinanceStat<Profitability>(slug, 'profitability', range);
    return (
        <StatPanel
            title="Profitability"
            icon={<Gauge className={headerIcon} />}
            state={state}
            skeleton={<KpiRowSkeleton />}
            isEmpty={(d) => d.revenue === 0 && d.ad_spend === 0}
            emptyMessage="No orders or ad spend in this period."
        >
            {(d) => <ProfitabilityTiles data={d} />}
        </StatPanel>
    );
}

export function ReconciliationSection({
    slug,
    range,
    remittancesUrl,
}: SectionProps & { remittancesUrl: string }) {
    const state = useFinanceStat<Reconciliation>(slug, 'reconciliation', range);
    return (
        <StatPanel
            title="Unreconciled Remittances"
            icon={<PieChart className={headerIcon} />}
            action={
                state.data ? (
                    <span className="font-mono text-[11px] text-gray-400">
                        {state.data.total_count} open
                    </span>
                ) : undefined
            }
            state={state}
            skeleton={<ReconciliationSkeleton />}
        >
            {(d) =>
                d.total_count === 0 ? (
                    <EmptyState message="All remittances reconciled." />
                ) : (
                    <ReconciliationPanel
                        data={d}
                        remittancesUrl={remittancesUrl}
                    />
                )
            }
        </StatPanel>
    );
}
