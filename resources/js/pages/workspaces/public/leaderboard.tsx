import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { Lock } from 'lucide-react';
import { useEffect, useState } from 'react';

interface PublicWorkspace {
    id: number;
    name: string;
    slug: string;
}

interface LeaderboardPageProps {
    locked?: boolean;
    workspace?: PublicWorkspace | null;
}

interface User {
    id: string;
    name: string;
    fb_id?: string;
    email?: string;
    phone_number?: string | null;
    created_at?: string;
    updated_at?: string;
    orders_count?: number;
    sales?: number;
    assigned_order_for_delivery_count?: number;
    called_count?: number;
}

interface Schedule {
    id: number;
    pancake_user_id: string;
    name: string;
    shift_start: string;
    shift_end: string;
    notes: string | null;
}

interface MedalIconProps {
    rank: number;
}

interface LeaderboardCardProps {
    rank: number;
    initials: string;
    name: string;
    primaryValue: string;
    secondaryValue?: string;
    activeTab: string;
    schedule?: Schedule;
}

interface LeaderboardEntryProps {
    rank: number;
    initials: string;
    name: string;
    primaryValue: string;
    secondaryValue?: string;
    activeTab: string;
    schedule?: Schedule;
}

// Rank accent colours (podium): gold / silver / bronze, then brand.
const rankRing: Record<number, string> = {
    1: 'ring-amber-400/60',
    2: 'ring-slate-300',
    3: 'ring-orange-400/60',
};

const MedalIcon = ({ rank }: MedalIconProps) => {
    const medals: Record<number, string> = {
        1: '🥇',
        2: '🥈',
        3: '🥉',
    };

    return (
        <div className="absolute -top-[4.75rem] left-1/2 -translate-x-1/2 transform text-3xl drop-shadow-sm">
            {medals[rank] ?? `#${rank}`}
        </div>
    );
};

const LeaderboardCard = ({
    rank,
    initials,
    name,
    primaryValue,
    secondaryValue,
    activeTab,
    schedule,
}: LeaderboardCardProps) => {
    const cardHeight = rank === 1 ? 'h-64' : rank === 2 ? 'h-56' : 'h-52';

    const getLabels = () => {
        if (activeTab === 'Sales Ranking') {
            return { primary: 'Sales', secondary: 'Orders' };
        } else if (activeTab === 'Called Activity') {
            return { primary: 'Calls Made', secondary: null };
        } else {
            return { primary: 'Delivered', secondary: null };
        }
    };

    const labels = getLabels();

    return (
        <div
            className={`relative mt-24 w-60 ${cardHeight} rounded-[16px] border border-black/6 bg-white shadow-[0_8px_30px_rgba(0,0,0,0.06)] transition-all hover:-translate-y-1 hover:shadow-[0_12px_40px_rgba(0,0,0,0.10)] dark:border-white/8 dark:bg-zinc-900 dark:shadow-black/30 ${
                rank === 1
                    ? 'border-t-[3px] border-t-brand-500'
                    : 'border-t-[3px] border-t-brand-500/40'
            }`}
        >
            <MedalIcon rank={rank} />

            <div className="absolute -top-8 left-1/2 -translate-x-1/2 transform">
                <div
                    className={`flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-brand-600 text-3xl font-bold text-white shadow-lg ring-4 ${
                        rankRing[rank] ?? 'ring-brand-200'
                    } dark:ring-offset-zinc-900`}
                >
                    {initials}
                </div>
            </div>

            <div className="relative z-10 mt-14 flex flex-col items-center px-6">
                <p className="text-center text-base font-semibold text-gray-800 dark:text-gray-100">
                    {name}
                </p>

                {schedule && (
                    <p className="mt-1 text-xs text-brand-600 dark:text-brand-400">
                        {schedule.shift_start} - {schedule.shift_end}
                    </p>
                )}

                <div
                    className={`mt-4 flex ${secondaryValue ? 'justify-center gap-8' : 'justify-center'}`}
                >
                    <div className="text-center">
                        <p className="font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                            {labels.primary}
                        </p>
                        <p className="mt-0.5 text-lg font-bold text-brand-600 dark:text-brand-400">
                            {primaryValue}
                        </p>
                    </div>
                    {secondaryValue && (
                        <div className="text-center">
                            <p className="font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                {labels.secondary}
                            </p>
                            <p className="mt-0.5 text-lg font-bold text-gray-700 dark:text-gray-200">
                                {secondaryValue}
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

const LeaderboardEntry = ({
    rank,
    initials,
    name,
    primaryValue,
    secondaryValue,
    activeTab,
    schedule,
}: LeaderboardEntryProps) => {
    const getLabels = () => {
        if (activeTab === 'Sales Ranking') {
            return { primary: 'Sales', secondary: 'orders' };
        } else if (activeTab === 'Called Activity') {
            return { primary: 'Calls', secondary: null };
        } else {
            return { primary: 'Delivered', secondary: null };
        }
    };

    const labels = getLabels();

    return (
        <div className="group flex items-center gap-3 rounded-[12px] border border-black/6 bg-white p-3.5 shadow-[0_1px_3px_rgba(0,0,0,0.04)] transition-all hover:border-brand-500/30 hover:shadow-[0_4px_16px_rgba(0,0,0,0.06)] dark:border-white/6 dark:bg-zinc-900 dark:hover:border-brand-500/30">
            <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-stone-100 text-center text-[12px] font-semibold text-gray-500 dark:bg-zinc-800 dark:text-gray-400">
                {rank}
            </span>
            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-brand-600 text-[12px] font-bold text-white shadow-sm">
                {initials}
            </div>
            <div className="flex-1 truncate">
                <span className="text-[14px] font-medium text-gray-800 dark:text-gray-100">
                    {name}
                </span>
                {schedule && (
                    <span className="ml-2 text-xs text-brand-600 dark:text-brand-400">
                        {schedule.shift_start} - {schedule.shift_end}
                    </span>
                )}
            </div>
            <span className="text-[14px] font-bold text-brand-600 dark:text-brand-400">
                {primaryValue}
            </span>
            {secondaryValue && (
                <span className="text-[12px] font-medium text-gray-400 dark:text-gray-500">
                    {secondaryValue} {labels.secondary}
                </span>
            )}
        </div>
    );
};

const getTodayString = (): string => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
};

function LeaderboardInner() {
    const [users, setUsers] = useState<User[]>([]);
    const [schedules, setSchedules] = useState<Schedule[]>([]);
    const [activeTab, setActiveTab] = useState('Sales Ranking');
    const [selectedDate, setSelectedDate] = useState(getTodayString());
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [retryCount, setRetryCount] = useState(0);

    const getInitials = (name: string): string => {
        return name
            .split(' ')
            .map((n: string) => n[0])
            .join('')
            .toUpperCase();
    };

    const formatSales = (sales: number | undefined): string => {
        if (sales === undefined || sales === null) return '₱0';
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(sales);
    };

    const getPrimaryValue = (user: User): string => {
        if (activeTab === 'Sales Ranking') {
            return formatSales(user.sales);
        } else if (activeTab === 'Called Activity') {
            return (user.assigned_order_for_delivery_count || 0).toString();
        } else {
            return (user.assigned_order_for_delivery_count || 0).toString();
        }
    };

    const getSecondaryValue = (user: User): string | undefined => {
        if (activeTab === 'Sales Ranking') {
            return (user.orders_count || 0).toString();
        } else {
            return undefined;
        }
    };

    const getScheduleForUser = (userId: string): Schedule | undefined =>
        schedules.find((s) => s.pancake_user_id === userId);

    const handleTabClick = (tab: string): void => {
        setActiveTab(tab);
        setError(null);
    };

    const sortedUsers: User[] = [...users].sort((a: User, b: User) => {
        if (activeTab === 'Sales Ranking') {
            return (b.sales || 0) - (a.sales || 0);
        } else if (activeTab === 'Called Activity') {
            return (
                (b.assigned_order_for_delivery_count || 0) -
                (a.assigned_order_for_delivery_count || 0)
            );
        } else {
            return (
                (b.assigned_order_for_delivery_count || 0) -
                (a.assigned_order_for_delivery_count || 0)
            );
        }
    });

    const topThree: User[] = sortedUsers.slice(0, 3);
    const restOfUsers: User[] = sortedUsers.slice(3);

    useEffect(() => {
        const fetchData = async () => {
            setIsLoading(true);
            setError(null);

            try {
                let url = '/api/public/leaderboards';

                if (activeTab === 'Called Activity') {
                    url = '/api/public/leaderboards/group-by-called';
                } else if (activeTab === 'Delivery Success') {
                    url = '/api/public/leaderboards/group-by-delivered';
                }

                const [leaderboardRes, schedulesRes] = await Promise.all([
                    axios.get(url, { params: { date: selectedDate } }),
                    axios.get('/api/public/leaderboards/schedules', {
                        params: { date: selectedDate },
                    }),
                ]);

                let data = Array.isArray(leaderboardRes.data)
                    ? leaderboardRes.data
                    : [];

                data = data.map((user: User) => ({
                    id: user.id,
                    name: user.name,
                    sales: user.sales || 0,
                    orders_count: user.orders_count || 0,
                    assigned_order_for_delivery_count:
                        user.assigned_order_for_delivery_count || 0,
                }));

                setUsers(data);
                setSchedules(
                    Array.isArray(schedulesRes.data) ? schedulesRes.data : [],
                );
            } catch (error) {
                console.error('Error fetching data:', error);
                setError('Failed to load leaderboard data. Please try again.');
                setUsers([]);
            } finally {
                setIsLoading(false);
            }
        };

        fetchData();
    }, [activeTab, selectedDate, retryCount]);

    const renderContent = () => {
        if (isLoading) {
            return (
                <div className="mt-24 flex flex-col items-center justify-center gap-4">
                    <div className="h-12 w-12 animate-spin rounded-full border-2 border-brand-500/20 border-t-brand-500" />
                    <p className="text-sm text-gray-400 dark:text-gray-500">
                        Loading leaderboard…
                    </p>
                </div>
            );
        }

        if (error) {
            return (
                <div className="mt-24 flex items-center justify-center">
                    <div className="text-center">
                        <div className="mb-3 text-5xl">⚠️</div>
                        <p className="mb-4 text-sm text-red-500">{error}</p>
                        <button
                            onClick={() => {
                                setError(null);
                                setRetryCount((c) => c + 1);
                            }}
                            className="rounded-[10px] bg-brand-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-700"
                        >
                            Try Again
                        </button>
                    </div>
                </div>
            );
        }

        if (users.length === 0) {
            return (
                <div className="mt-24 flex flex-col items-center justify-center">
                    <div className="text-center">
                        <div className="mb-5 text-6xl">
                            {activeTab === 'Sales Ranking' && '💰'}
                            {activeTab === 'Called Activity' && '📞'}
                            {activeTab === 'Delivery Success' && '🚚'}
                        </div>
                        <h3 className="mb-2 text-xl font-semibold text-gray-800 dark:text-gray-100">
                            No Data Available
                        </h3>
                        <p className="mb-1 text-sm text-gray-500 dark:text-gray-400">
                            {activeTab === 'Sales Ranking' &&
                                'No sales have been recorded for this date.'}
                            {activeTab === 'Called Activity' &&
                                'No call activity has been recorded for this date.'}
                            {activeTab === 'Delivery Success' &&
                                'No deliveries have been completed for this date.'}
                        </p>
                        <p className="text-xs text-gray-400 dark:text-gray-500">
                            Check back later for updates
                        </p>
                    </div>
                </div>
            );
        }

        return (
            <>
                {topThree.length > 0 && (
                    <div className="flex items-end justify-center gap-4">
                        {/* Podium order: 2nd, 1st, 3rd */}
                        {[topThree[1], topThree[0], topThree[2]]
                            .filter(Boolean)
                            .map((user: User) => {
                                const rank =
                                    user === topThree[0]
                                        ? 1
                                        : user === topThree[1]
                                          ? 2
                                          : 3;

                                return (
                                    <LeaderboardCard
                                        key={user.id}
                                        rank={rank}
                                        initials={getInitials(user.name)}
                                        name={user.name}
                                        primaryValue={getPrimaryValue(user)}
                                        secondaryValue={getSecondaryValue(user)}
                                        activeTab={activeTab}
                                        schedule={getScheduleForUser(user.id)}
                                    />
                                );
                            })}
                    </div>
                )}

                {restOfUsers.length > 0 && (
                    <div className="mx-auto mt-8 w-full max-w-3xl space-y-2 px-4">
                        {restOfUsers.map((user: User, index: number) => (
                            <LeaderboardEntry
                                key={user.id}
                                rank={index + 4}
                                initials={getInitials(user.name)}
                                name={user.name}
                                primaryValue={getPrimaryValue(user)}
                                secondaryValue={getSecondaryValue(user)}
                                activeTab={activeTab}
                                schedule={getScheduleForUser(user.id)}
                            />
                        ))}
                    </div>
                )}
            </>
        );
    };

    return (
        <div className="relative min-h-screen overflow-y-auto bg-stone-100 font-sans dark:bg-zinc-950">
            {/* Landing-style grid + glow backdrop */}
            <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(rgba(0,0,0,0.03)_1px,transparent_1px),linear-gradient(90deg,rgba(0,0,0,0.03)_1px,transparent_1px)] bg-[size:60px_60px] dark:bg-[linear-gradient(rgba(255,255,255,0.015)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.015)_1px,transparent_1px)]" />
            <div className="pointer-events-none absolute -top-40 left-1/2 h-[500px] w-[900px] -translate-x-1/2 bg-[radial-gradient(ellipse,rgba(16,211,161,0.12),transparent_70%)]" />

            <div className="relative z-10 flex flex-col items-center px-4 pt-14 pb-12">
                {/* Brand mark */}
                <div className="mb-4 flex items-center gap-2">
                    <span className="flex h-7 w-7 items-center justify-center rounded-[7px] bg-brand-500! text-sm font-bold text-white shadow-sm shadow-brand-500/20">
                        A
                    </span>
                    <span className="font-mono text-[11px] font-semibold tracking-[0.2em] text-brand-700 uppercase dark:text-brand-400">
                        Artemis
                    </span>
                </div>

                <h1 className="text-center text-4xl font-bold tracking-tight text-gray-900 md:text-5xl dark:text-gray-100">
                    CSR{' '}
                    <span className="bg-gradient-to-br from-brand-500 to-brand-700 bg-clip-text text-transparent italic">
                        Leaderboards
                    </span>
                </h1>
                <p className="mt-3 font-mono text-[11px] tracking-[0.15em] text-gray-400 uppercase dark:text-gray-600">
                    Daily Performance Rankings
                </p>

                {/* Date controls */}
                <div className="mt-5 flex items-center gap-2">
                    <button
                        onClick={() => {
                            const d = new Date(selectedDate + 'T00:00:00');
                            d.setDate(d.getDate() - 1);
                            setSelectedDate(
                                `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`,
                            );
                        }}
                        className="flex h-9 w-9 items-center justify-center rounded-[10px] border border-black/8 bg-white text-gray-500 transition-colors hover:border-black/14 hover:text-gray-700 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        ←
                    </button>
                    <input
                        type="date"
                        value={selectedDate}
                        onChange={(e) => setSelectedDate(e.target.value)}
                        className="h-9 rounded-[10px] border border-black/8 bg-white px-3 text-sm text-gray-700 transition-all outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-200 dark:[color-scheme:dark]"
                    />
                    <button
                        onClick={() => {
                            const d = new Date(selectedDate + 'T00:00:00');
                            d.setDate(d.getDate() + 1);
                            setSelectedDate(
                                `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`,
                            );
                        }}
                        className="flex h-9 w-9 items-center justify-center rounded-[10px] border border-black/8 bg-white text-gray-500 transition-colors hover:border-black/14 hover:text-gray-700 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        →
                    </button>
                    {selectedDate !== getTodayString() && (
                        <button
                            onClick={() => setSelectedDate(getTodayString())}
                            className="h-9 rounded-[10px] border border-brand-500/30 bg-brand-500/10 px-3 text-xs font-medium text-brand-700 transition-colors hover:bg-brand-500/20 dark:text-brand-400"
                        >
                            Today
                        </button>
                    )}
                </div>

                {/* Tabs */}
                <div className="mt-5 flex flex-wrap justify-center gap-2">
                    {(
                        [
                            'Sales Ranking',
                            'Called Activity',
                            'Delivery Success',
                        ] as const
                    ).map((tab: string) => (
                        <button
                            key={tab}
                            onClick={() => handleTabClick(tab)}
                            className={`rounded-[10px] border px-5 py-2 text-[13px] font-medium transition-all ${
                                activeTab === tab
                                    ? 'border-brand-600 bg-brand-600 text-white shadow-sm dark:border-brand-500 dark:bg-brand-500'
                                    : 'border-black/8 bg-white text-gray-600 hover:border-black/14 hover:text-gray-800 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-400 dark:hover:text-gray-200'
                            }`}
                        >
                            {tab}
                        </button>
                    ))}
                </div>

                <div className="mt-10 w-full">{renderContent()}</div>
            </div>
        </div>
    );
}

/** Password gate shown before the leaderboard when the workspace requires it. */
function LeaderboardLock({ workspace }: { workspace: PublicWorkspace }) {
    const form = useForm({ password: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(
            `/public/workspaces/${workspace.slug}/leaderboards/verify-password`,
            {
                preserveScroll: true,
                onError: () => form.reset('password'),
            },
        );
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-violet-900 p-4">
            <div className="w-full max-w-sm rounded-2xl border border-white/10 bg-white/10 p-6 backdrop-blur-sm">
                <div className="mb-4 flex flex-col items-center text-center">
                    <div className="mb-3 flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-white">
                        <Lock className="h-5 w-5" />
                    </div>
                    <h1 className="text-lg font-semibold text-white">
                        Protected leaderboard
                    </h1>
                    <p className="mt-1 text-xs text-violet-200">
                        Enter the password to view {workspace.name}&apos;s
                        leaderboards.
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-3">
                    <input
                        type="password"
                        autoFocus
                        autoComplete="current-password"
                        value={form.data.password}
                        onChange={(e) =>
                            form.setData('password', e.target.value)
                        }
                        placeholder="Password"
                        className="w-full rounded-full border border-white/10 bg-white/10 px-4 py-2 text-sm text-white placeholder-violet-200/60 transition-all outline-none focus:ring-2 focus:ring-violet-400/50"
                    />
                    {form.errors.password && (
                        <p className="text-center text-xs text-red-300">
                            {form.errors.password}
                        </p>
                    )}
                    <button
                        type="submit"
                        disabled={form.processing || !form.data.password}
                        className="w-full rounded-full bg-violet-500 px-6 py-2 text-sm font-medium text-white transition-colors hover:bg-violet-600 disabled:opacity-50"
                    >
                        {form.processing ? 'Unlocking…' : 'Unlock'}
                    </button>
                </form>
            </div>
        </div>
    );
}

export default function Leaderboard({
    locked,
    workspace,
}: LeaderboardPageProps) {
    if (locked && workspace) {
        return <LeaderboardLock workspace={workspace} />;
    }

    return <LeaderboardInner />;
}
