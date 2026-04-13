import { useEffect, useState } from 'react';
import axios from 'axios';

interface LeaderboardRow {
    csr_id: string;
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

type PeriodTab = 'Daily' | 'Weekly' | 'Monthly';

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
}

interface LeaderboardEntryProps {
    rank: number;
    initials: string;
    name: string;
    primaryValue: string;
    secondaryValue?: string;
    activeTab: string;
}

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
    primaryValue,
    secondaryValue,
    activeTab,
}: LeaderboardCardProps) => {
    const cardHeight = rank === 1 ? 'h-60' : rank === 2 ? 'h-54' : 'h-54';

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

                <div
                    className={`mt-4 flex ${secondaryValue ? 'justify-center gap-8' : 'justify-center'}`}
                >
                    <div className="text-center">
                        <p className="text-xs text-gray-400">
                            {labels.primary}
                        </p>
                        <p className="text-sm font-medium text-white drop-shadow">
                            {primaryValue}
                        </p>
                    </div>
                    {secondaryValue && (
                        <div className="text-center">
                            <p className="text-xs text-gray-400">
                                {labels.secondary}
                            </p>
                            <p className="text-sm font-medium text-white drop-shadow">
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
                    {primaryValue}
                </span>
                {secondaryValue && (
                    <span className="text-sm font-medium text-white/70 drop-shadow">
                        {secondaryValue} {labels.secondary}
                    </span>
                )}
            </div>

            <div className="absolute bottom-0 left-0 h-0.5 w-0 bg-gradient-to-r from-violet-400 to-violet-300 transition-all duration-300 group-hover:w-full"></div>
        </div>
    );
};

export default function Leaderboard() {
    const [users, setUsers] = useState<User[]>([]);
    const [activeTab, setActiveTab] = useState('Sales Ranking');
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

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

    const handleTabClick = (tab: string): void => {
        setActiveTab(tab);
        setError(null);
    };

    // Safe sorting with fallback values
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

                const response = await axios.get(url);

                // Ensure we always have an array and normalize the data
                let data = Array.isArray(response.data) ? response.data : [];

                // Normalize data to ensure consistent field names
                data = data.map((user: any) => ({
                    id: user.id,
                    name: user.name,
                    // For sales ranking endpoint
                    sales: user.sales || 0,
                    orders_count: user.orders_count || 0,
                    // For called/delivered endpoints
                    assigned_order_for_delivery_count:
                        user.assigned_order_for_delivery_count || 0,
                }));

                setUsers(data);
            } catch (error) {
                console.error('Error fetching data:', error);
                setError('Failed to load leaderboard data. Please try again.');
                setUsers([]);
            } finally {
                setIsLoading(false);
            }
        };

        fetchData();
    }, [activeTab]);

    // Loading State
    if (isLoading) {
        return (
            <div className="relative h-screen overflow-hidden bg-violet-900">
                <div className="absolute inset-0 flex items-center justify-center">
                    <div className="relative">
                        <div className="h-32 w-32 animate-spin rounded-full border-t-2 border-b-2 border-violet-400"></div>
                        <div className="absolute inset-0 flex items-center justify-center">
                            <div className="h-16 w-16 animate-pulse rounded-full bg-violet-400/20"></div>
                        </div>
                        <p className="absolute -bottom-12 left-1/2 -translate-x-1/2 whitespace-nowrap text-white/80">
                            Loading leaderboard...
                        </p>
                    </div>
                </div>
            </div>
        );
    }

    // Error State
    if (error) {
        return (
            <div className="relative h-screen overflow-hidden bg-violet-900">
                <div className="absolute inset-0 flex items-center justify-center">
                    <div className="text-center">
                        <div className="mb-4 text-6xl">⚠️</div>
                        <p className="mb-2 text-red-400">{error}</p>
                        <button
                            onClick={() => {
                                setError(null);
                                setIsLoading(true);
                                // Trigger refetch
                                const fetchData = async () => {
                                    try {
                                        let url = '/api/public/leaderboards';
                                        if (activeTab === 'Called Activity') {
                                            url =
                                                '/api/public/leaderboards/group-by-called';
                                        } else if (
                                            activeTab === 'Delivery Success'
                                        ) {
                                            url =
                                                '/api/public/leaderboards/group-by-delivered';
                                        }
                                        const response = await axios.get(url);
                                        let data = Array.isArray(response.data)
                                            ? response.data
                                            : [];
                                        data = data.map((user: any) => ({
                                            id: user.id,
                                            name: user.name,
                                            sales: user.sales || 0,
                                            orders_count:
                                                user.orders_count || 0,
                                            assigned_order_for_delivery_count:
                                                user.assigned_order_for_delivery_count ||
                                                0,
                                        }));
                                        setUsers(data);
                                    } catch (err) {
                                        console.error(err);
                                        setError(
                                            'Failed to load leaderboard data. Please try again.',
                                        );
                                    } finally {
                                        setIsLoading(false);
                                    }
                                };
                                fetchData();
                            }}
                            className="mt-4 rounded-full bg-violet-500 px-6 py-2 text-white transition-colors hover:bg-violet-600"
                        >
                            Try Again
                        </button>
                    </div>
                </div>
            </div>
        );
    }

    // Empty State - Now with title and tabs
    if (users.length === 0) {
        return (
            <div className="relative h-screen overflow-hidden bg-violet-900">
                {/* Background decorations - same as main view */}
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
                                    className={`relative overflow-hidden rounded-full border border-white/10 px-6 py-2 text-gray-200 transition-all hover:shadow-lg hover:shadow-white/10 ${
                                        activeTab === tab
                                            ? 'bg-white/20 backdrop-blur-sm'
                                            : 'hover:bg-white/5'
                                    }`}
                                >
                                    <div className="absolute top-0 left-0 h-1/2 w-full bg-gradient-to-b from-white/20 to-transparent"></div>
                                    <span className="relative z-10">{tab}</span>
                                </button>
                            ))}
                        </div>

                        {/* Empty State Content */}
                        <div className="mt-20 flex flex-col items-center justify-center">
                            <div className="text-center">
                                <div className="mb-6 animate-bounce text-7xl">
                                    {activeTab === 'Sales Ranking' && '💰'}
                                    {activeTab === 'Called Activity' && '📞'}
                                    {activeTab === 'Delivery Success' && '🚚'}
                                </div>
                                <h3 className="mb-3 text-2xl font-semibold text-white">
                                    No Data Available
                                </h3>
                                <p className="mb-2 text-lg text-gray-300">
                                    {activeTab === 'Sales Ranking' &&
                                        'No sales have been recorded for today.'}
                                    {activeTab === 'Called Activity' &&
                                        'No call activity has been recorded for today.'}
                                    {activeTab === 'Delivery Success' &&
                                        'No deliveries have been completed today.'}
                                </p>
                                <p className="text-sm text-gray-400">
                                    Check back later for updates
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="relative h-screen overflow-hidden bg-violet-900">
            <Head title="CSR Leaderboards" />

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
                                className={`relative overflow-hidden rounded-full border border-white/10 px-6 py-2 text-gray-200 transition-all hover:shadow-lg hover:shadow-white/10 ${
                                    activeTab === tab
                                        ? 'bg-white/20 backdrop-blur-sm'
                                        : 'hover:bg-white/5'
                                }`}
                            >
                                <div className="absolute top-0 left-0 h-1/2 w-full bg-gradient-to-b from-white/20 to-transparent"></div>
                                <span className="relative z-10">{tab}</span>
                            </button>
                        ))}
                    </div>

                    {topThree.length > 0 && (
                        <div className="flex items-end gap-4">
                            {topThree.map((user: User, index: number) => {
                                const rank =
                                    index === 0 ? 1 : index === 1 ? 2 : 3;

                                return (
                                    <LeaderboardCard
                                        key={user.id}
                                        rank={rank}
                                        initials={getInitials(user.name)}
                                        name={user.name}
                                        primaryValue={getPrimaryValue(user)}
                                        secondaryValue={getSecondaryValue(user)}
                                        activeTab={activeTab}
                                    />
                                );
                            })}
                        </div>
                    )}

                    {restOfUsers.length > 0 && (
                        <div className="mt-8 w-full max-w-4xl space-y-2 px-4">
                            {restOfUsers.map((user: User, index: number) => (
                                <LeaderboardEntry
                                    key={user.id}
                                    rank={index + 4}
                                    initials={getInitials(user.name)}
                                    name={user.name}
                                    primaryValue={getPrimaryValue(user)}
                                    secondaryValue={getSecondaryValue(user)}
                                    activeTab={activeTab}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {loaderMounted && <OldDesignLoader visible={loading} />}
        </div>
    );
}       
