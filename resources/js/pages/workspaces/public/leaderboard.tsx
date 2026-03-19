import { useEffect, useMemo, useState } from 'react';

interface LeaderboardRow {
    csr_id: string;
    name: string;
    total_orders: number;
    total_sales: number;
    rank: number;
}

type PeriodTab = 'Daily' | 'Weekly' | 'Monthly';

interface MedalIconProps {
    rank: number;
}

interface LeaderboardCardProps {
    rank: number;
    initials: string;
    name: string;
    sales: string;
    orders: string;
}

interface LeaderboardEntryProps {
    rank: number;
    initials: string;
    name: string;
    amount: string;
    orders: string;
}

const OldDesignLoader = ({ visible }: { visible: boolean }) => {
    return (
        <div
            className={`fixed inset-0 z-50 flex flex-col items-center justify-center bg-linear-to-br from-[#0f001f] to-[#3b006a] transition-opacity duration-500 ${
                visible ? 'opacity-100' : 'pointer-events-none opacity-0'
            }`}
        >
            <h1
                className={`text-4xl font-bold tracking-[0.25em] text-white transition-all duration-500 md:text-5xl ${
                    visible ? 'translate-y-0 opacity-100' : 'translate-y-1 opacity-0'
                }`}
            >
                CSR LEADERBOARD
            </h1>
            <div
                className={`mt-5 h-[60px] w-[60px] animate-spin rounded-full border-[6px] border-white/20 border-t-violet-500 transition-all duration-500 ${
                    visible ? 'scale-100 opacity-100' : 'scale-95 opacity-0'
                }`}
            ></div>
        </div>
    );
};

const MedalIcon = ({ rank }: MedalIconProps) => {
    const medals: Record<number, { color: string; icon: string }> = {
        1: { color: 'text-yellow-400', icon: '🥇' },
        2: { color: 'text-gray-300', icon: '🥈' },
        3: { color: 'text-amber-600', icon: '🥉' },
    };

    const medal = medals[rank] || { color: 'text-gray-400', icon: `#${rank}` };

    return (
        <div
            className={`absolute -top-20 left-1/2 -translate-x-1/2 transform text-3xl drop-shadow-lg ${medal.color}`}
        >
            {medal.icon}
        </div>
    );
};

const LeaderboardCard = ({
    rank,
    initials,
    name,
    sales,
    orders,
}: LeaderboardCardProps) => {
    const cardHeight = rank === 1 ? 'h-60' : rank === 2 ? 'h-54' : 'h-54';

    return (
        <div
            className={`relative mt-24 w-60 ${cardHeight} rounded-2xl border-t-4 border-violet-400 bg-white/5 p-6 backdrop-blur-sm transition-all hover:scale-105 hover:border-white/20 hover:shadow-2xl hover:shadow-white/10`}
        >
            <div className="absolute top-1/2 left-0 h-20 w-10 -translate-y-1/2 bg-gradient-to-r from-violet-400/5 to-transparent blur-md"></div>
            <div className="absolute top-1/2 right-0 h-20 w-10 -translate-y-1/2 bg-gradient-to-l from-violet-400/5 to-transparent blur-md"></div>
            <div className="absolute -top-10 left-1/2 h-24 w-24 -translate-x-1/2 transform rounded-full bg-violet-400/20 blur-xl"></div>

            <MedalIcon rank={rank} />

            <div className="absolute -top-8 left-1/2 -translate-x-1/2 transform">
                <div className="relative">
                    <div
                        className={`flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-violet-400 to-violet-600 text-3xl font-bold text-white shadow-xl ring-4 drop-shadow-lg ${
                            rank === 1
                                ? 'shadow-yellow-400/20 ring-yellow-400/50'
                                : rank === 2
                                  ? 'shadow-gray-300/20 ring-gray-300/50'
                                  : 'shadow-amber-600/20 ring-amber-600/50'
                        }`}
                    >
                        {initials}
                    </div>
                </div>
            </div>

            <div className="relative z-10 mt-12 flex flex-col items-center">
                <p className="text-center text-lg font-semibold text-white drop-shadow">
                    {name}
                </p>

                <div className="mt-4 flex justify-center gap-8">
                    <div className="text-center">
                        <p className="text-xs text-gray-400">Sales</p>
                        <p className="text-sm font-medium text-white drop-shadow">
                            {sales}
                        </p>
                    </div>
                    <div className="text-center">
                        <p className="text-xs text-gray-400">Orders</p>
                        <p className="text-sm font-medium text-white drop-shadow">
                            {orders}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
};

const LeaderboardEntry = ({
    rank,
    initials,
    name,
    amount,
    orders,
}: LeaderboardEntryProps) => {
    return (
        <div className="group relative overflow-hidden rounded-xl border border-white/5 bg-white/5 p-4 backdrop-blur-sm transition-all hover:bg-white/10 hover:shadow-xl hover:shadow-white/5">
            <div className="absolute top-0 left-0 h-10 w-full bg-gradient-to-b from-white/10 to-transparent"></div>
            <div className="absolute bottom-0 left-0 h-10 w-full bg-gradient-to-t from-violet-400/10 to-transparent"></div>
            <div className="absolute top-0 left-0 h-full w-1 bg-gradient-to-b from-transparent via-violet-400/20 to-transparent"></div>

            <div className="relative z-10 flex items-center gap-3">
                <span className="flex h-6 w-6 items-center justify-center rounded-full bg-white/5 text-center text-sm font-medium text-gray-400 ring-1 ring-white/10">
                    {rank}
                </span>
                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-violet-500 to-violet-700 text-xs font-bold text-white shadow-lg">
                    {initials}
                </div>
                <span className="flex-1 text-sm text-white drop-shadow">
                    {name}
                </span>
                <span className="text-sm font-medium text-white drop-shadow">
                    {amount}
                </span>
                <span className="text-sm font-medium text-white/70 drop-shadow">
                    {orders} orders
                </span>
            </div>

            <div className="absolute bottom-0 left-0 h-0.5 w-0 bg-gradient-to-r from-violet-400 to-violet-300 transition-all duration-300 group-hover:w-full"></div>
        </div>
    );
};

export default function Leaderboard() {
    const [period, setPeriod] = useState<PeriodTab>('Daily');
    const [users, setUsers] = useState<LeaderboardRow[]>([]);
    const [loading, setLoading] = useState(true);
    const [loaderMounted, setLoaderMounted] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const workspaceSlug = useMemo(() => {
        if (typeof window === 'undefined') return 'developers-workspace';
        const slug = new URLSearchParams(window.location.search).get('workspace');
        return slug || 'developers-workspace';
    }, []);

    const apiBase = useMemo(() => {
        if (typeof window === 'undefined') return '';
        const envBase = (import.meta.env.VITE_API_BASE_URL as string | undefined)?.trim();
        if (envBase) return envBase.replace(/\/$/, '');
        if (window.location.port === '5173') return 'http://localhost';
        return '';
    }, []);

    const getInitials = (name: string): string => {
        return name
            .split(' ')
            .map((n: string) => n[0])
            .join('');
    };

    const formatSales = (sales: number): string => {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(sales);
    };

    const formatOrders = (orders: number): string => orders.toString();

    const formatDate = (date: Date): string => {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    };

    const periodDateRange = (tab: PeriodTab): { start: string; end: string } => {
        const now = new Date();

        if (tab === 'Daily') {
            const today = formatDate(now);
            return { start: today, end: today };
        }

        if (tab === 'Weekly') {
            const start = new Date(now);
            const day = (start.getDay() + 6) % 7;
            start.setDate(start.getDate() - day);
            const end = new Date(start);
            end.setDate(start.getDate() + 6);
            return { start: formatDate(start), end: formatDate(end) };
        }

        const start = new Date(now.getFullYear(), now.getMonth(), 1);
        const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        return { start: formatDate(start), end: formatDate(end) };
    };

    const parseJsonResponse = async (res: Response, label: string) => {
        const text = await res.text();
        if (!text) return {};
        try {
            return JSON.parse(text);
        } catch {
            const preview = text.slice(0, 80).replace(/\s+/g, ' ');
            throw new Error(`${label} returned non-JSON response (${res.status}): ${preview}`);
        }
    };

    useEffect(() => {
        const load = async () => {
            setLoading(true);
            setError(null);

            try {
                const range = periodDateRange(period);
                const perfUrl = `${apiBase}/api/public/workspaces/${workspaceSlug}/csrs/performance?start_date=${encodeURIComponent(range.start)}&end_date=${encodeURIComponent(range.end)}`;

                const res = await fetch(perfUrl, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const json = await parseJsonResponse(res, 'Leaderboard API');
                if (!res.ok) throw new Error(json.message || 'Failed to load leaderboard');

                const rows = Array.isArray(json.data) ? json.data : [];
                const normalized = rows.map((row: Partial<LeaderboardRow>) => ({
                    csr_id: String(row.csr_id ?? ''),
                    name: row.name || '-',
                    total_orders: Number(row.total_orders || 0),
                    total_sales: Number(row.total_sales || 0),
                    rank: Number(row.rank || 0),
                }));

                setUsers(normalized);
            } catch (err) {
                const message = err instanceof Error ? err.message : 'Failed to load leaderboard';
                setError(message);
                setUsers([]);
            } finally {
                setLoading(false);
            }
        };

        void load();
    }, [period, workspaceSlug, apiBase]);

    useEffect(() => {
        let timeoutId: ReturnType<typeof setTimeout> | null = null;
        if (loading) {
            setLoaderMounted(true);
        } else {
            timeoutId = setTimeout(() => setLoaderMounted(false), 500);
        }

        return () => {
            if (timeoutId) clearTimeout(timeoutId);
        };
    }, [loading]);

    const topThree: LeaderboardRow[] = [users[1], users[0], users[2]].filter(
        (user): user is LeaderboardRow => Boolean(user),
    );
    const restOfUsers: LeaderboardRow[] = users.slice(3);

    return (
        <div className="relative h-screen overflow-hidden bg-violet-900">
            <div className="absolute -top-20 -right-10 h-100 w-100">
                <div className="absolute inset-0 rounded-full bg-linear-to-bl from-violet-900 via-violet-800 to-violet-300 opacity-80 blur-3xl"></div>
                <div className="absolute inset-0 animate-pulse rounded-full border-2 border-white/30"></div>
                <div className="absolute inset-4 rounded-full border border-white/20 blur-sm"></div>
                <div className="absolute inset-8 rounded-full border border-white/10 blur-md"></div>
                <div className="absolute inset-0 rounded-full bg-linear-to-tr from-transparent via-white/20 to-white/40 blur-2xl"></div>
                <div className="absolute top-10 right-10 h-2 w-2 animate-ping rounded-full bg-white"></div>
                <div className="absolute top-20 right-20 h-1 w-1 animate-pulse rounded-full bg-white/80"></div>
            </div>

            <div className="absolute top-20 left-10 h-64 w-64">
                <div className="absolute inset-0 rounded-full bg-white/20 shadow-[0px_0px_80px_20px_rgba(255,255,255,0.3)] blur-2xl"></div>
                <div className="absolute inset-0 rounded-full border border-white/30"></div>
            </div>

            <div className="absolute -bottom-20 -left-10 h-100 w-100">
                <div className="absolute inset-0 rounded-full bg-linear-to-tr from-violet-900 via-violet-800 to-violet-300 opacity-80 blur-3xl"></div>
                <div className="absolute inset-0 animate-pulse rounded-full border-2 border-white/30"></div>
                <div className="absolute inset-4 rounded-full border border-white/20 blur-sm"></div>
                <div className="absolute inset-8 rounded-full border border-white/10 blur-md"></div>
                <div className="absolute inset-0 rounded-full bg-linear-to-bl from-transparent via-white/20 to-white/40 blur-2xl"></div>
                <div className="absolute bottom-10 left-10 h-2 w-2 animate-ping rounded-full bg-white"></div>
                <div className="absolute bottom-20 left-20 h-1 w-1 animate-pulse rounded-full bg-white/80"></div>
            </div>

            <div className="absolute right-0 bottom-20 h-64 w-64">
                <div className="absolute inset-1 rounded-full bg-white/20 shadow-white blur-2xl"></div>
                <div className="absolute inset-0 rounded-full border border-white/30"></div>
            </div>

            <div className="absolute inset-0 bg-linear-to-t from-violet-950/50 to-transparent"></div>

            <div className="absolute top-1/4 right-1/4 h-1 w-1 animate-pulse rounded-full bg-white/50"></div>
            <div className="absolute bottom-1/3 left-1/3 h-1 w-1 animate-pulse rounded-full bg-white/50 delay-300"></div>
            <div className="absolute top-2/3 right-1/3 h-1 w-1 animate-pulse rounded-full bg-white/50 delay-700"></div>

            <div className="relative z-10 h-full overflow-y-auto">
                <div className="flex flex-col items-center pt-20 pb-10">
                    <h1 className="text-4xl font-bold text-white drop-shadow-lg">
                        CSR Leaderboards
                    </h1>

                    <div className="mt-6 flex gap-4">
                        {(['Daily', 'Weekly', 'Monthly'] as const).map(
                            (tab: PeriodTab) => (
                                <button
                                    key={tab}
                                    onClick={() => setPeriod(tab)}
                                    className={`relative overflow-hidden rounded-full border border-white/10 px-6 py-2 text-gray-200 transition-all hover:shadow-lg hover:shadow-white/10 ${
                                        period === tab
                                            ? 'bg-white/5 backdrop-blur-sm hover:bg-white/20'
                                            : 'hover:bg-white/5'
                                    }`}
                                >
                                    <div className="absolute top-0 left-0 h-1/2 w-full bg-gradient-to-b from-white/20 to-transparent"></div>
                                    <span className="relative z-10">{tab}</span>
                                </button>
                            ),
                        )}
                    </div>

                    {error && (
                        <div className="mt-6 rounded-lg border border-red-400/30 bg-red-500/10 px-4 py-2 text-sm text-red-100">
                            {error}
                        </div>
                    )}

                    <div className="flex items-end gap-4">
                        {topThree.map((user: LeaderboardRow, index: number) => {
                            const rank = index === 0 ? 2 : index === 1 ? 1 : 3;
                            return (
                                <LeaderboardCard
                                    key={user.csr_id}
                                    rank={rank}
                                    initials={getInitials(user.name)}
                                    name={user.name}
                                    sales={formatSales(user.total_sales)}
                                    orders={formatOrders(user.total_orders)}
                                />
                            );
                        })}
                    </div>

                    <div className="mt-8 w-full max-w-4xl space-y-2 px-4">
                        {restOfUsers.map((user: LeaderboardRow, index: number) => (
                            <LeaderboardEntry
                                key={user.csr_id}
                                rank={index + 4}
                                initials={getInitials(user.name)}
                                name={user.name}
                                amount={formatSales(user.total_sales)}
                                orders={formatOrders(user.total_orders)}
                            />
                        ))}
                    </div>
                </div>
            </div>

            {loaderMounted && <OldDesignLoader visible={loading} />}
        </div>
    );
}
