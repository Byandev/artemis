import { useEffect, useState } from 'react';
import axios from 'axios';
import error from 'eslint-plugin-react/lib/util/error';

interface User {
    id: string;
    name: string;
    fb_id: string;
    email: string;
    phone_number: string | null;
    created_at: string;
    updated_at: string;
    orders_count: number;
    sales: number;
}

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

const users: User[] = [
    {
        id: 'a1',
        name: 'Maria Santos',
        fb_id: '100000000000001',
        email: 'maria.santos@example.com',
        phone_number: '09171234567',
        created_at: '2026-03-01T10:00:00.000Z',
        updated_at: '2026-03-10T12:00:00.000Z',
        orders_count: 2100,
        sales: 1250000.0,
    },
    {
        id: 'a2',
        name: 'Juan Dela Cruz',
        fb_id: '100000000000002',
        email: 'juan.delacruz@example.com',
        phone_number: '09181234567',
        created_at: '2026-02-15T09:30:00.000Z',
        updated_at: '2026-03-12T11:20:00.000Z',
        orders_count: 1980,
        sales: 1104500.0,
    },
    {
        id: 'a3',
        name: 'Ana Reyes',
        fb_id: '100000000000003',
        email: 'ana.reyes@example.com',
        phone_number: null,
        created_at: '2026-01-20T08:15:00.000Z',
        updated_at: '2026-03-11T14:10:00.000Z',
        orders_count: 1750,
        sales: 985300.0,
    },
    {
        id: 'a4',
        name: 'Carlos Mendoza',
        fb_id: '100000000000004',
        email: 'carlos.mendoza@example.com',
        phone_number: '09221234567',
        created_at: '2026-01-05T13:45:00.000Z',
        updated_at: '2026-03-09T16:00:00.000Z',
        orders_count: 1600,
        sales: 912000.0,
    },
    {
        id: 'a5',
        name: 'Liza Fernandez',
        fb_id: '100000000000005',
        email: 'liza.fernandez@example.com',
        phone_number: '09331234567',
        created_at: '2026-02-01T07:20:00.000Z',
        updated_at: '2026-03-08T10:30:00.000Z',
        orders_count: 1500,
        sales: 870500.0,
    },
    {
        id: 'a6',
        name: 'Mark Villanueva',
        fb_id: '100000000000006',
        email: 'mark.v@example.com',
        phone_number: null,
        created_at: '2026-02-10T11:00:00.000Z',
        updated_at: '2026-03-07T09:45:00.000Z',
        orders_count: 1400,
        sales: 820000.0,
    },
    {
        id: 'a7',
        name: 'Grace Bautista',
        fb_id: '100000000000007',
        email: 'grace.bautista@example.com',
        phone_number: '09441234567',
        created_at: '2026-01-25T15:10:00.000Z',
        updated_at: '2026-03-06T13:25:00.000Z',
        orders_count: 1300,
        sales: 765200.0,
    },
    {
        id: 'a8',
        name: 'Paolo Garcia',
        fb_id: '100000000000008',
        email: 'paolo.garcia@example.com',
        phone_number: '09551234567',
        created_at: '2026-03-02T12:00:00.000Z',
        updated_at: '2026-03-05T08:40:00.000Z',
        orders_count: 1200,
        sales: 702100.0,
    },
    {
        id: 'a9',
        name: 'Jessa Cruz',
        fb_id: '100000000000009',
        email: 'jessa.cruz@example.com',
        phone_number: null,
        created_at: '2026-02-18T14:30:00.000Z',
        updated_at: '2026-03-04T17:15:00.000Z',
        orders_count: 1100,
        sales: 650000.0,
    },
    {
        id: 'a10',
        name: 'Ronnie Lopez',
        fb_id: '100000000000010',
        email: 'ronnie.lopez@example.com',
        phone_number: '09661234567',
        created_at: '2026-01-12T06:50:00.000Z',
        updated_at: '2026-03-03T19:00:00.000Z',
        orders_count: 1000,
        sales: 590300.0,
    },
];

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

    const [ users, setUsers] = useState([]);
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

    const formatOrders = (orders: number): string => {
        return orders.toString();
    };

    const handleTabClick = (tab: string): void => {
        console.log(`${tab} tab clicked`);
        // if (tab === 'Daily') { sort by daily sales }
        // if (tab === 'Weekly') { sort by weekly sales }
        // if (tab === 'Monthly') { sort by monthly sales }
    };

    const sortedUsers: User[] = [...users].sort(
        (a: User, b: User) => b.sales - a.sales,
    );
    const topThree: User[] = sortedUsers.slice(0, 3);
    const restOfUsers: User[] = sortedUsers.slice(3);

    useEffect(() => {
        const fetchData = async () => {
            try {
                const response = await axios.get(
                    '/api/public/leaderboards',
                );
                setUsers(response.data);
            } catch (error) {
                console.log(error);
            }
        };

        fetchData();
    }, []);

    console.log(users)

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
                            (tab: string, index: number) => (
                                <button
                                    key={tab}
                                    onClick={() => handleTabClick(tab)}
                                    className={`relative overflow-hidden rounded-full border border-white/10 px-6 py-2 text-gray-200 transition-all hover:shadow-lg hover:shadow-white/10 ${
                                        index === 0
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

                    <div className="flex items-end gap-4">
                        {topThree.map((user: User, index: number) => {
                            const rank = index === 0 ? 1 : index === 1 ? 2 : 3;

                            return (
                                <LeaderboardCard
                                    key={user.id}
                                    rank={rank}
                                    initials={getInitials(user.name)}
                                    name={user.name}
                                    sales={formatSales(user.sales)}
                                    orders={formatOrders(user.orders_count)}
                                />
                            );
                        })}
                    </div>

                    <div className="mt-8 w-full max-w-4xl space-y-2 px-4">
                        {restOfUsers.map((user: User, index: number) => (
                            <LeaderboardEntry
                                key={user.id}
                                rank={index + 4}
                                initials={getInitials(user.name)}
                                name={user.name}
                                amount={formatSales(user.sales)}
                                orders={formatOrders(user.orders_count)}
                            />
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
