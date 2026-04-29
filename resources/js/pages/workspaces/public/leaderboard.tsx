import { useEffect, useState } from 'react';
import axios from 'axios';

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
    delay?: number;
}

const leaderboardStyles = `
@keyframes lb-blob-a {
    0%   { transform: translate(0, 0) scale(1); }
    33%  { transform: translate(80px, -60px) scale(1.15); }
    66%  { transform: translate(-50px, 40px) scale(0.9); }
    100% { transform: translate(0, 0) scale(1); }
}
@keyframes lb-blob-b {
    0%   { transform: translate(0, 0) scale(1); }
    50%  { transform: translate(-90px, 70px) scale(1.2); }
    100% { transform: translate(0, 0) scale(1); }
}
@keyframes lb-blob-c {
    0%   { transform: translate(0, 0) scale(1); }
    50%  { transform: translate(60px, 90px) scale(1.1); }
    100% { transform: translate(0, 0) scale(1); }
}
@keyframes lb-drift-up {
    0%   { transform: translate3d(0,100vh,0) rotate(0deg); opacity: 0; }
    10%  { opacity: 1; }
    90%  { opacity: 1; }
    100% { transform: translate3d(0,-10vh,0) rotate(360deg); opacity: 0; }
}
@keyframes lb-drift-side {
    0%   { transform: translate3d(-10vw,0,0) rotate(0deg); opacity: 0; }
    10%  { opacity: 0.9; }
    90%  { opacity: 0.9; }
    100% { transform: translate3d(110vw,0,0) rotate(720deg); opacity: 0; }
}
@keyframes lb-drift-diag {
    0%   { transform: translate3d(-10vw, 110vh, 0) rotate(0deg); opacity: 0; }
    15%  { opacity: 0.85; }
    85%  { opacity: 0.85; }
    100% { transform: translate3d(110vw, -10vh, 0) rotate(540deg); opacity: 0; }
}
@keyframes lb-twinkle {
    0%, 100% { opacity: 0.2; transform: scale(0.8); }
    50%      { opacity: 1;   transform: scale(1.3); }
}
@keyframes lb-shimmer {
    0%   { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}
@keyframes lb-ring-spin {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}
@keyframes lb-crown-bob {
    0%, 100% { transform: translate(-50%, 0) rotate(-3deg); }
    50%      { transform: translate(-50%, -8px) rotate(3deg); }
}
@keyframes lb-pop-in {
    0%   { transform: translateY(40px) scale(0.85); opacity: 0; }
    60%  { transform: translateY(-6px) scale(1.03); opacity: 1; }
    100% { transform: translateY(0) scale(1); opacity: 1; }
}
@keyframes lb-pop-in-center {
    0%   { transform: translateY(60px) scale(0.8); opacity: 0; }
    60%  { transform: translateY(-10px) scale(1.05); opacity: 1; }
    100% { transform: translateY(0) scale(1); opacity: 1; }
}
@keyframes lb-slide-in {
    0%   { transform: translateX(-30px); opacity: 0; }
    100% { transform: translateX(0); opacity: 1; }
}
@keyframes lb-glow-pulse {
    0%, 100% { box-shadow: 0 0 30px 4px rgba(250,204,21,0.4), 0 0 60px 12px rgba(250,204,21,0.15); }
    50%      { box-shadow: 0 0 50px 8px rgba(250,204,21,0.7), 0 0 100px 24px rgba(250,204,21,0.25); }
}
@keyframes lb-float-y {
    0%, 100% { transform: translateY(0); }
    50%      { transform: translateY(-12px); }
}
@keyframes lb-rays {
    0%   { transform: translate(-50%, -50%) rotate(0deg); }
    100% { transform: translate(-50%, -50%) rotate(360deg); }
}

.lb-blob-a { animation: lb-blob-a 18s ease-in-out infinite; }
.lb-blob-b { animation: lb-blob-b 22s ease-in-out infinite; }
.lb-blob-c { animation: lb-blob-c 26s ease-in-out infinite; }
.lb-twinkle { animation: lb-twinkle 3s ease-in-out infinite; }
.lb-shimmer-text {
    background-image: linear-gradient(90deg, #fff 0%, #fcd34d 25%, #fff 50%, #fcd34d 75%, #fff 100%);
    background-size: 200% auto;
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    animation: lb-shimmer 3.5s linear infinite;
}
.lb-ring-spin { animation: lb-ring-spin 8s linear infinite; }
.lb-crown-bob { animation: lb-crown-bob 2.4s ease-in-out infinite; }
.lb-pop-in { animation: lb-pop-in 0.7s cubic-bezier(0.22, 1, 0.36, 1) both; }
.lb-pop-in-center { animation: lb-pop-in-center 0.8s cubic-bezier(0.22, 1, 0.36, 1) 0.15s both; }
.lb-slide-in { animation: lb-slide-in 0.5s ease-out both; }
.lb-glow-pulse { animation: lb-glow-pulse 2.5s ease-in-out infinite; }
.lb-float-y { animation: lb-float-y 4s ease-in-out infinite; }
.lb-rays { animation: lb-rays 18s linear infinite; }
`;

const AnimatedBackground = () => (
    <>
        <div className="lb-blob-a absolute -top-32 -right-32 h-[34rem] w-[34rem] rounded-full bg-gradient-to-bl from-fuchsia-400/40 via-violet-500/30 to-transparent blur-3xl" />
        <div className="lb-blob-b absolute top-1/3 -left-40 h-[36rem] w-[36rem] rounded-full bg-gradient-to-tr from-indigo-400/30 via-violet-400/30 to-transparent blur-3xl" />
        <div className="lb-blob-c absolute -bottom-40 right-1/4 h-[30rem] w-[30rem] rounded-full bg-gradient-to-tl from-pink-400/30 via-violet-500/20 to-transparent blur-3xl" />

        <div className="pointer-events-none absolute inset-0 overflow-hidden">
            {Array.from({ length: 28 }).map((_, i) => {
                const left = (i * 3.6) % 100;
                const size = 10 + (i % 6) * 6;
                const duration = 12 + (i % 6) * 3;
                const delay = (i * 0.9) % 12;
                const tint = i % 3 === 0 ? 'bg-yellow-200/60' : i % 3 === 1 ? 'bg-violet-200/60' : 'bg-white/70';
                return (
                    <div
                        key={`up-${i}`}
                        className={`absolute rounded-full ${tint} shadow-[0_0_12px_rgba(255,255,255,0.5)]`}
                        style={{
                            left: `${left}%`,
                            bottom: `-${size}px`,
                            width: `${size}px`,
                            height: `${size}px`,
                            animation: `lb-drift-up ${duration}s linear ${delay}s infinite`,
                        }}
                    />
                );
            })}

            {Array.from({ length: 12 }).map((_, i) => {
                const top = (5 + i * 8) % 95;
                const size = 18 + (i % 4) * 12;
                const duration = 22 + (i % 5) * 8;
                const delay = (i * 2.5) % 14;
                const isFilled = i % 2 === 0;
                return (
                    <div
                        key={`side-${i}`}
                        className={`absolute ${isFilled ? 'bg-fuchsia-300/40' : 'border-2 border-yellow-200/60'} shadow-[0_0_15px_rgba(255,255,255,0.3)]`}
                        style={{
                            top: `${top}%`,
                            left: 0,
                            width: `${size}px`,
                            height: `${size}px`,
                            borderRadius: i % 3 === 0 ? '50%' : i % 3 === 1 ? '20%' : '8px',
                            animation: `lb-drift-side ${duration}s linear ${delay}s infinite`,
                        }}
                    />
                );
            })}

            {Array.from({ length: 8 }).map((_, i) => {
                const left = (i * 13) % 100;
                const size = 14 + (i % 3) * 8;
                const duration = 26 + (i % 4) * 6;
                const delay = (i * 3.5) % 16;
                return (
                    <div
                        key={`diag-${i}`}
                        className="absolute rounded-full border-2 border-pink-200/70 shadow-[0_0_18px_rgba(244,114,182,0.5)]"
                        style={{
                            left: `${left}%`,
                            bottom: 0,
                            width: `${size}px`,
                            height: `${size}px`,
                            animation: `lb-drift-diag ${duration}s linear ${delay}s infinite`,
                        }}
                    />
                );
            })}

            {Array.from({ length: 50 }).map((_, i) => {
                const top = (i * 11) % 100;
                const left = (i * 17) % 100;
                const size = 2 + (i % 3) * 1.5;
                const delay = (i * 0.2) % 4;
                return (
                    <div
                        key={`star-${i}`}
                        className="lb-twinkle absolute rounded-full bg-white shadow-[0_0_8px_rgba(255,255,255,0.8)]"
                        style={{
                            top: `${top}%`,
                            left: `${left}%`,
                            width: `${size}px`,
                            height: `${size}px`,
                            animationDelay: `${delay}s`,
                        }}
                    />
                );
            })}
        </div>

        <div className="absolute inset-0 bg-gradient-to-t from-violet-950/60 via-transparent to-violet-950/30" />
    </>
);

const MedalIcon = ({ rank }: { rank: number }) => {
    const medals: Record<number, string> = { 1: '👑', 2: '🥈', 3: '🥉' };
    const icon = medals[rank] || `#${rank}`;

    if (rank === 1) {
        return (
            <div className="lb-crown-bob absolute -top-24 left-1/2 z-20 text-5xl drop-shadow-[0_0_20px_rgba(250,204,21,0.7)]">
                {icon}
            </div>
        );
    }
    return (
        <div className="lb-float-y absolute -top-20 left-1/2 -translate-x-1/2 transform text-3xl drop-shadow-lg">
            {icon}
        </div>
    );
};

const LeaderboardCard = ({ rank, initials, name, primaryValue, secondaryValue, activeTab }: LeaderboardCardProps) => {
    const isFirst = rank === 1;
    const cardHeight = isFirst ? 'h-72' : 'h-56';
    const cardWidth = isFirst ? 'w-72' : 'w-60';
    const animClass = isFirst ? 'lb-pop-in-center' : 'lb-pop-in';
    const delay = rank === 2 ? '0s' : rank === 3 ? '0.15s' : '0.3s';

    const getLabels = () => {
        if (activeTab === 'Sales Ranking') return { primary: 'Sales', secondary: 'Orders' };
        if (activeTab === 'Called Activity') return { primary: 'Calls Made', secondary: null };
        return { primary: 'Delivered', secondary: null };
    };
    const labels = getLabels();

    const ringColor = isFirst
        ? 'ring-yellow-400/70 shadow-yellow-400/40'
        : rank === 2
          ? 'ring-gray-200/60 shadow-gray-300/30'
          : 'ring-amber-500/60 shadow-amber-600/30';

    const borderColor = isFirst
        ? 'border-yellow-400'
        : rank === 2
          ? 'border-gray-300'
          : 'border-amber-500';

    return (
        <div
            className={`relative ${animClass} mt-24 ${cardWidth} ${cardHeight} rounded-2xl border-t-4 ${borderColor} bg-white/10 p-6 backdrop-blur-md transition-all duration-300 hover:scale-105 hover:bg-white/15 hover:shadow-2xl hover:shadow-white/10`}
            style={{ animationDelay: delay }}
        >
            {isFirst && (
                <>
                    <div className="lb-rays pointer-events-none absolute top-1/2 left-1/2 h-[200%] w-[200%]">
                        <div className="absolute inset-0 bg-[conic-gradient(from_0deg,rgba(250,204,21,0.25),transparent_30%,rgba(250,204,21,0.25)_60%,transparent_90%)] opacity-50 blur-2xl" />
                    </div>
                    <div className="pointer-events-none absolute inset-0 overflow-hidden rounded-2xl">
                        <div
                            className="absolute inset-0 opacity-40"
                            style={{
                                backgroundImage: 'linear-gradient(120deg, transparent 30%, rgba(255,255,255,0.4) 50%, transparent 70%)',
                                backgroundSize: '200% 100%',
                                animation: 'lb-shimmer 3s linear infinite',
                            }}
                        />
                    </div>
                </>
            )}

            <div className="absolute top-1/2 left-0 h-20 w-10 -translate-y-1/2 bg-gradient-to-r from-violet-400/10 to-transparent blur-md" />
            <div className="absolute top-1/2 right-0 h-20 w-10 -translate-y-1/2 bg-gradient-to-l from-violet-400/10 to-transparent blur-md" />
            <div
                className={`absolute -top-10 left-1/2 h-24 w-24 -translate-x-1/2 transform rounded-full blur-xl ${
                    isFirst ? 'bg-yellow-400/30' : 'bg-violet-400/20'
                }`}
            />

            <MedalIcon rank={rank} />

            <div className="absolute -top-10 left-1/2 z-10 -translate-x-1/2 transform">
                <div className="relative">
                    {isFirst && (
                        <div className="lb-ring-spin absolute -inset-2 rounded-full border-2 border-dashed border-yellow-300/70" />
                    )}
                    <div
                        className={`flex items-center justify-center rounded-full bg-gradient-to-br ${
                            isFirst ? 'from-yellow-300 via-amber-400 to-orange-500 lb-glow-pulse' : 'from-violet-400 to-violet-600'
                        } font-bold text-white shadow-xl ring-4 drop-shadow-lg ${ringColor} ${isFirst ? 'h-24 w-24 text-4xl' : 'h-20 w-20 text-3xl'}`}
                    >
                        {initials}
                    </div>
                </div>
            </div>

            <div className={`relative z-10 flex flex-col items-center ${isFirst ? 'mt-16' : 'mt-12'}`}>
                <p
                    className={`text-center font-semibold drop-shadow ${
                        isFirst ? 'text-2xl lb-shimmer-text' : 'text-lg text-white'
                    }`}
                >
                    {name}
                </p>

                <div className={`mt-4 flex ${secondaryValue ? 'justify-center gap-8' : 'justify-center'}`}>
                    <div className="text-center">
                        <p className={`text-xs uppercase tracking-wider ${isFirst ? 'text-yellow-200/80' : 'text-gray-400'}`}>
                            {labels.primary}
                        </p>
                        <p className={`font-medium drop-shadow ${isFirst ? 'text-xl text-yellow-100' : 'text-sm text-white'}`}>
                            {primaryValue}
                        </p>
                    </div>
                    {secondaryValue && (
                        <div className="text-center">
                            <p className={`text-xs uppercase tracking-wider ${isFirst ? 'text-yellow-200/80' : 'text-gray-400'}`}>
                                {labels.secondary}
                            </p>
                            <p className={`font-medium drop-shadow ${isFirst ? 'text-xl text-yellow-100' : 'text-sm text-white'}`}>
                                {secondaryValue}
                            </p>
                        </div>
                    )}
                </div>
            </div>

            {isFirst && (
                <div className="pointer-events-none absolute inset-0 overflow-hidden rounded-2xl">
                    {Array.from({ length: 8 }).map((_, i) => {
                        const left = (i * 13) % 100;
                        const delay = (i * 0.4) % 3;
                        return (
                            <div
                                key={i}
                                className="lb-twinkle absolute h-1 w-1 rounded-full bg-yellow-200"
                                style={{ left: `${left}%`, top: `${20 + (i * 9) % 60}%`, animationDelay: `${delay}s` }}
                            />
                        );
                    })}
                </div>
            )}
        </div>
    );
};

const LeaderboardEntry = ({ rank, initials, name, primaryValue, secondaryValue, activeTab, delay = 0 }: LeaderboardEntryProps) => {
    const getLabels = () => {
        if (activeTab === 'Sales Ranking') return { primary: 'Sales', secondary: 'orders' };
        if (activeTab === 'Called Activity') return { primary: 'Calls', secondary: null };
        return { primary: 'Delivered', secondary: null };
    };
    const labels = getLabels();

    return (
        <div
            className="lb-slide-in group relative overflow-hidden rounded-xl border border-white/5 bg-white/5 p-4 backdrop-blur-sm transition-all hover:translate-x-1 hover:bg-white/10 hover:shadow-xl hover:shadow-white/5"
            style={{ animationDelay: `${delay}s` }}
        >
            <div className="absolute top-0 left-0 h-10 w-full bg-gradient-to-b from-white/10 to-transparent" />
            <div className="absolute bottom-0 left-0 h-10 w-full bg-gradient-to-t from-violet-400/10 to-transparent" />
            <div className="absolute top-0 left-0 h-full w-1 bg-gradient-to-b from-transparent via-violet-400/20 to-transparent" />

            <div className="relative z-10 flex items-center gap-3">
                <span className="flex h-6 w-6 items-center justify-center rounded-full bg-white/5 text-center text-sm font-medium text-gray-300 ring-1 ring-white/10">
                    {rank}
                </span>
                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-violet-500 to-violet-700 text-xs font-bold text-white shadow-lg transition-transform group-hover:scale-110">
                    {initials}
                </div>
                <span className="flex-1 text-sm text-white drop-shadow">{name}</span>
                <span className="text-sm font-medium text-white drop-shadow">{primaryValue}</span>
                {secondaryValue && (
                    <span className="text-sm font-medium text-white/70 drop-shadow">
                        {secondaryValue} {labels.secondary}
                    </span>
                )}
            </div>

            <div className="absolute bottom-0 left-0 h-0.5 w-0 bg-gradient-to-r from-violet-400 to-violet-300 transition-all duration-300 group-hover:w-full" />
        </div>
    );
};

export default function Leaderboard() {
    const [users, setUsers] = useState<User[]>([]);
    const [activeTab, setActiveTab] = useState('Sales Ranking');
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const getInitials = (name: string): string =>
        name
            .split(' ')
            .map((n: string) => n[0])
            .join('')
            .toUpperCase();

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
        if (activeTab === 'Sales Ranking') return formatSales(user.sales);
        return (user.assigned_order_for_delivery_count || 0).toString();
    };

    const getSecondaryValue = (user: User): string | undefined => {
        if (activeTab === 'Sales Ranking') return (user.orders_count || 0).toString();
        return undefined;
    };

    const handleTabClick = (tab: string): void => {
        setActiveTab(tab);
        setError(null);
    };

    const sortedUsers: User[] = [...users].sort((a: User, b: User) => {
        if (activeTab === 'Sales Ranking') return (b.sales || 0) - (a.sales || 0);
        return (b.assigned_order_for_delivery_count || 0) - (a.assigned_order_for_delivery_count || 0);
    });

    const topThree: User[] = sortedUsers.slice(0, 3);
    const restOfUsers: User[] = sortedUsers.slice(3);

    // Reorder podium so #1 is in the middle: [2nd, 1st, 3rd]
    const podiumOrder: { user: User; rank: number }[] = (() => {
        const result: { user: User; rank: number }[] = [];
        if (topThree[1]) result.push({ user: topThree[1], rank: 2 });
        if (topThree[0]) result.push({ user: topThree[0], rank: 1 });
        if (topThree[2]) result.push({ user: topThree[2], rank: 3 });
        return result;
    })();

    useEffect(() => {
        const fetchData = async () => {
            setIsLoading(true);
            setError(null);

            try {
                let url = '/api/public/leaderboards';
                if (activeTab === 'Called Activity') url = '/api/public/leaderboards/group-by-called';
                else if (activeTab === 'Delivery Success') url = '/api/public/leaderboards/group-by-delivered';

                const response = await axios.get(url);
                let data = Array.isArray(response.data) ? response.data : [];

                data = data.map((user: any) => ({
                    id: user.id,
                    name: user.name,
                    sales: user.sales || 0,
                    orders_count: user.orders_count || 0,
                    assigned_order_for_delivery_count: user.assigned_order_for_delivery_count || 0,
                }));

                setUsers(data);
            } catch (err) {
                console.error('Error fetching data:', err);
                setError('Failed to load leaderboard data. Please try again.');
                setUsers([]);
            } finally {
                setIsLoading(false);
            }
        };

        fetchData();
    }, [activeTab]);

    if (isLoading) {
        return (
            <div className="relative h-screen overflow-hidden bg-violet-900">
                <style>{leaderboardStyles}</style>
                <AnimatedBackground />
                <div className="relative z-10 flex h-full items-center justify-center">
                    <div className="relative">
                        <div className="h-32 w-32 animate-spin rounded-full border-t-2 border-b-2 border-violet-300" />
                        <div className="absolute inset-0 flex items-center justify-center">
                            <div className="h-16 w-16 animate-pulse rounded-full bg-violet-400/30" />
                        </div>
                        <p className="absolute -bottom-12 left-1/2 -translate-x-1/2 whitespace-nowrap text-white/80">
                            Loading leaderboard...
                        </p>
                    </div>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="relative h-screen overflow-hidden bg-violet-900">
                <style>{leaderboardStyles}</style>
                <AnimatedBackground />
                <div className="relative z-10 flex h-full items-center justify-center">
                    <div className="text-center">
                        <div className="mb-4 animate-bounce text-6xl">⚠️</div>
                        <p className="mb-2 text-red-300">{error}</p>
                        <button
                            onClick={() => {
                                setError(null);
                                setActiveTab((t) => t);
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

    const renderHeader = () => (
        <>
            <h1 className="lb-shimmer-text text-5xl font-extrabold tracking-tight drop-shadow-lg md:text-6xl">
                CSR Leaderboards
            </h1>
            <p className="mt-2 text-sm text-violet-200/80 md:text-base">Live performance · Updated in real time</p>

            <div className="mt-6 flex flex-wrap justify-center gap-3">
                {(['Sales Ranking', 'Called Activity', 'Delivery Success'] as const).map((tab) => (
                    <button
                        key={tab}
                        onClick={() => handleTabClick(tab)}
                        className={`relative overflow-hidden rounded-full border px-6 py-2 text-gray-200 transition-all hover:scale-105 hover:shadow-lg hover:shadow-white/10 ${
                            activeTab === tab
                                ? 'border-yellow-300/40 bg-white/20 backdrop-blur-sm shadow-lg shadow-yellow-400/10'
                                : 'border-white/10 hover:bg-white/10'
                        }`}
                    >
                        <div className="absolute top-0 left-0 h-1/2 w-full bg-gradient-to-b from-white/20 to-transparent" />
                        <span className="relative z-10">{tab}</span>
                    </button>
                ))}
            </div>
        </>
    );

    if (users.length === 0) {
        return (
            <div className="relative h-screen overflow-hidden bg-violet-900">
                <style>{leaderboardStyles}</style>
                <AnimatedBackground />

                <div className="relative z-10 h-full overflow-y-auto">
                    <div className="flex flex-col items-center pt-20 pb-10">
                        {renderHeader()}

                        <div className="mt-20 flex flex-col items-center justify-center">
                            <div className="text-center">
                                <div className="mb-6 animate-bounce text-7xl">
                                    {activeTab === 'Sales Ranking' && '💰'}
                                    {activeTab === 'Called Activity' && '📞'}
                                    {activeTab === 'Delivery Success' && '🚚'}
                                </div>
                                <h3 className="mb-3 text-2xl font-semibold text-white">No Data Available</h3>
                                <p className="mb-2 text-lg text-gray-300">
                                    {activeTab === 'Sales Ranking' && 'No sales have been recorded for today.'}
                                    {activeTab === 'Called Activity' && 'No call activity has been recorded for today.'}
                                    {activeTab === 'Delivery Success' && 'No deliveries have been completed today.'}
                                </p>
                                <p className="text-sm text-gray-400">Check back later for updates</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="relative h-screen overflow-hidden bg-violet-900">
            <style>{leaderboardStyles}</style>
            <AnimatedBackground />

            <div className="relative z-10 h-full overflow-y-auto">
                <div className="flex flex-col items-center pt-16 pb-10">
                    {renderHeader()}

                    {podiumOrder.length > 0 && (
                        <div className="mt-12 flex items-end justify-center gap-4 px-4">
                            {podiumOrder.map(({ user, rank }) => (
                                <LeaderboardCard
                                    key={user.id}
                                    rank={rank}
                                    initials={getInitials(user.name)}
                                    name={user.name}
                                    primaryValue={getPrimaryValue(user)}
                                    secondaryValue={getSecondaryValue(user)}
                                    activeTab={activeTab}
                                />
                            ))}
                        </div>
                    )}

                    {restOfUsers.length > 0 && (
                        <div className="mt-10 w-full max-w-4xl space-y-2 px-4">
                            {restOfUsers.map((user, index) => (
                                <LeaderboardEntry
                                    key={user.id}
                                    rank={index + 4}
                                    initials={getInitials(user.name)}
                                    name={user.name}
                                    primaryValue={getPrimaryValue(user)}
                                    secondaryValue={getSecondaryValue(user)}
                                    activeTab={activeTab}
                                    delay={0.05 * index}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
