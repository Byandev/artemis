import PageHeader from '@/components/common/PageHeader';
import {
    BalanceHistorySection,
    CashFlowSection,
    ExpenseBreakdownSection,
    IncomeBreakdownSection,
    KpiSection,
    ProfitabilitySection,
    ReconciliationSection,
    TopMovementsSection,
} from '@/components/finance/dashboard/sections';
import { type DashboardRange } from '@/components/finance/dashboard/use-finance-stat';
import DatePicker from '@/components/ui/date-picker';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link } from '@inertiajs/react';
import { format, startOfMonth } from 'date-fns';
import { useEffect, useMemo, useState } from 'react';

interface AccountRow {
    id: number;
    name: string;
    currency: string;
    opening_balance: number | string;
    is_active: boolean;
    balance: number;
}

interface Props {
    workspace: Workspace;
    accounts: AccountRow[];
}

const fmt = (v: number) =>
    Number(v).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const today = () => format(new Date(), 'yyyy-MM-dd');
const monthStart = () => format(startOfMonth(new Date()), 'yyyy-MM-dd');
const isDate = (v: unknown): v is string =>
    typeof v === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(v);

export default function FinanceDashboard({ workspace, accounts }: Props) {
    const base = `/workspaces/${workspace.slug}/finance`;
    const canViewAccounts = usePermission(PERMISSIONS.ViewFinanceAccounts);

    const active = useMemo(
        () => accounts.filter((a) => a.is_active),
        [accounts],
    );

    // Persist the picked range per workspace so a refresh keeps the filter
    // instead of snapping back to month-to-date. Computed once so the picker's
    // defaultDate stays referentially stable.
    const storageKey = `finance-dashboard-range:${workspace.slug}`;
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
        return { start: monthStart(), end: today() };
    }, [storageKey]);

    const [range, setRange] = useState<DashboardRange>(initialRange);
    const defaultDate = useMemo(
        () => [initialRange.start, initialRange.end] as never,
        [initialRange],
    );

    useEffect(() => {
        try {
            localStorage.setItem(storageKey, JSON.stringify(range));
        } catch {
            // Ignore storage errors (quota / private mode).
        }
    }, [storageKey, range]);

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Live Cashflow`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Live Cashflow"
                    description="Overview of accounts, activity, and unreconciled remittances."
                    stackActionsOnMobile
                >
                    <DatePicker
                        id="finance-dashboard-range"
                        mode="range"
                        defaultDate={defaultDate}
                        onChange={(dates: Date[]) => {
                            if (dates.length === 2) {
                                setRange({
                                    start: format(dates[0], 'yyyy-MM-dd'),
                                    end: format(dates[1], 'yyyy-MM-dd'),
                                });
                            }
                        }}
                    />
                </PageHeader>

                <KpiSection slug={workspace.slug} range={range} />

                {/* Section 2 — trends */}
                <div className="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-2">
                    <CashFlowSection slug={workspace.slug} range={range} />
                    <BalanceHistorySection
                        slug={workspace.slug}
                        range={range}
                    />
                </div>

                {/* Section 5 — profitability (cross-domain) */}
                <div className="mt-3">
                    <ProfitabilitySection slug={workspace.slug} range={range} />
                </div>

                {/* Section 3 — breakdowns */}
                <div className="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-2">
                    <ExpenseBreakdownSection
                        slug={workspace.slug}
                        range={range}
                    />
                    <IncomeBreakdownSection
                        slug={workspace.slug}
                        range={range}
                    />
                </div>

                <div className="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <TopMovementsSection
                            slug={workspace.slug}
                            range={range}
                        />
                    </div>
                    {/* Section 4 — reconciliation */}
                    <ReconciliationSection
                        slug={workspace.slug}
                        range={range}
                        remittancesUrl={`${base}/remittances?filter[unreconciled]=1`}
                    />
                </div>

                <div className="mt-6 rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <div className="border-b border-black/6 px-5 py-3 font-mono text-[11px] font-medium tracking-wider text-gray-400 uppercase dark:border-white/6 dark:text-gray-500">
                        Accounts
                    </div>
                    <div className="divide-y divide-black/6 dark:divide-white/6">
                        {active.length === 0 && (
                            <div className="px-5 py-6 text-center text-[12px] text-gray-400">
                                No active accounts yet.
                            </div>
                        )}
                        {active.map((a) =>
                            canViewAccounts ? (
                                <Link
                                    key={a.id}
                                    href={`${base}/accounts/${a.id}?from=live-cashflow`}
                                    className="flex items-center justify-between px-5 py-3"
                                >
                                    <AccountRowContent account={a} />
                                </Link>
                            ) : (
                                <div
                                    key={a.id}
                                    className="flex items-center justify-between px-5 py-3"
                                >
                                    <AccountRowContent account={a} />
                                </div>
                            ),
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function AccountRowContent({ account }: { account: AccountRow }) {
    return (
        <>
            <div className="flex flex-col gap-0.5">
                <span className="text-[13px] font-medium text-gray-800 dark:text-gray-100">
                    {account.name}
                </span>
                <span className="font-mono text-[10px] tracking-wider text-gray-400 uppercase">
                    {account.currency}
                </span>
            </div>
            <span
                className={`font-mono text-[13px] font-medium ${account.balance >= 0 ? 'text-gray-700 dark:text-gray-200' : 'text-red-500'}`}
            >
                {fmt(account.balance)}
            </span>
        </>
    );
}
