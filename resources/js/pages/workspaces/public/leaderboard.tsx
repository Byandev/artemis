    // Medal Icon Component
    const MedalIcon = ({ rank }) => {
        const medals = {
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

    // Leaderboard Card Component
    const LeaderboardCard = ({
                                 rank,
                                 initials,
                                 name,
                                 sales,
                                 orders,
                                 rating,
                                 isTopRank = false,
                             }) => {
        // Different heights for top 3 ranks
        const cardHeight = rank === 1 ? 'h-60' : rank === 2 ? 'h-54' : 'h-54';

        return (
            <div
                className={`relative mt-24 w-60 ${cardHeight} rounded-2xl border-t-4 border-violet-400 bg-white/5 p-6 backdrop-blur-sm transition-all hover:scale-105 hover:border-white/20 hover:shadow-2xl hover:shadow-white/10`}
            >
                {/* Left/right subtle glows */}
                <div className='absolute top-1/2 left-0 h-20 w-10 -translate-y-1/2 bg-gradient-to-r from-violet-400/5 to-transparent blur-md'></div>
                <div className='absolute top-1/2 right-0 h-20 w-10 -translate-y-1/2 bg-gradient-to-l from-violet-400/5 to-transparent blur-md'></div>

                {/* Radial glow behind avatar */}
                <div className='absolute -top-10 left-1/2 h-24 w-24 -translate-x-1/2 transform rounded-full bg-violet-400/20 blur-xl'></div>

                {/* Medal Icon on top */}
                <MedalIcon rank={rank}/>

                {/* Rank badge with avatar */}
                <div className="absolute -top-8 left-1/2 -translate-x-1/2 transform">
                    <div className="relative">
                        {/* Avatar with ring and shadow */}
                        <div
                            className={`flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-violet-400 to-violet-600 text-3xl font-bold text-white shadow-xl ring-4 drop-shadow-lg ${
                                rank === 1
                                    ? 'ring-yellow-400/50 shadow-yellow-400/20'
                                    : rank === 2
                                        ? 'ring-gray-300/50 shadow-gray-300/20'
                                        : 'ring-amber-600/50 shadow-amber-600/20'
                            }`}
                        >
                            {initials}
                        </div>
                    </div>
                </div>

                {/* Salesperson info */}
                <div className="relative z-10 mt-12 flex flex-col items-center">
                    <p className="text-center text-lg font-semibold text-white drop-shadow">
                        {name}
                    </p>

                    {/* Stats - horizontal layout */}
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
                        <div className="text-center">
                            <p className="text-xs text-gray-400">Rating</p>
                            <p className="text-sm font-medium text-white drop-shadow">
                                {rating}
                            </p>
                        </div>
                    </div>
                </div>

            </div>
        );
    };

    // Leaderboard Entry Component (for the list below)
    const LeaderboardEntry = ({ rank, initials, name, amount }) => {
        return (
            <div className="group relative overflow-hidden rounded-xl border border-white/5 bg-white/5 p-4 backdrop-blur-sm transition-all hover:bg-white/10 hover:shadow-xl hover:shadow-white/5">
                {/* Top highlight */}
                <div className='absolute top-0 left-0 h-10 w-full bg-gradient-to-b from-white/10 to-transparent'></div>

                {/* Bottom glow */}
                <div className='absolute bottom-0 left-0 h-10 w-full bg-gradient-to-t from-violet-400/10 to-transparent'></div>

                {/* Left accent */}
                <div className='absolute left-0 top-0 h-full w-1 bg-gradient-to-b from-transparent via-violet-400/20 to-transparent'></div>

                <div className="relative z-10 flex items-center gap-3">
                    <span className="flex h-6 w-6 items-center justify-center rounded-full bg-white/5 text-center text-sm font-medium text-gray-400 ring-1 ring-white/10">
                        {rank}
                    </span>
                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-violet-500 to-violet-700 text-xs font-bold text-white shadow-lg">
                        {initials}
                    </div>
                    <span className="flex-1 text-sm text-white drop-shadow">{name}</span>
                    <span className="text-sm font-medium text-white drop-shadow">{amount}</span>
                </div>

                {/* Hover accent */}
                <div className="absolute bottom-0 left-0 h-0.5 w-0 bg-gradient-to-r from-violet-400 to-violet-300 transition-all duration-300 group-hover:w-full"></div>
            </div>
        );
    };

    // Main Leaderboard Component
    export default function Leaderboard() {
        return (
            <div className="relative h-screen overflow-auto bg-violet-900">
                {/* Enhanced Background Circles with Borders and Lighting Effects */}

                {/* Top Right Circle - Enhanced with border and lighting */}
                <div className="absolute -top-20 -right-10 h-100 w-100">
                    {/* Main circle with gradient and border */}
                    <div className="absolute inset-0 rounded-full bg-gradient-to-bl from-violet-900 via-violet-800 to-violet-300 opacity-80 blur-3xl"></div>

                    {/* Animated border ring */}
                    <div className="absolute inset-0 rounded-full border-2 border-white/30 animate-pulse"></div>

                    {/* Inner glow rings */}
                    <div className="absolute inset-4 rounded-full border border-white/20 blur-sm"></div>
                    <div className="absolute inset-8 rounded-full border border-white/10 blur-md"></div>

                    {/* Radial lighting effect */}
                    <div className="absolute inset-0 rounded-full bg-gradient-to-tr from-transparent via-white/20 to-white/40 blur-2xl"></div>

                    {/* Sparkle effects */}
                    <div className="absolute top-10 right-10 h-2 w-2 rounded-full bg-white animate-ping"></div>
                    <div className="absolute top-20 right-20 h-1 w-1 rounded-full bg-white/80 animate-pulse"></div>
                </div>

                {/* Top left ambient circle */}
                <div className="absolute top-20 left-10 h-64 w-64">
                    <div className="absolute inset-0 rounded-full bg-white/20 shadow-[0px_0px_80px_20px_rgba(255,255,255,0.3)] blur-2xl"></div>
                    <div className="absolute inset-0 rounded-full border border-white/30"></div>
                </div>

                {/* Bottom Left Circle - Enhanced with border and lighting */}
                <div className="absolute -bottom-20 -left-10 h-100 w-100">
                    {/* Main circle with gradient and border */}
                    <div className="absolute inset-0 rounded-full bg-gradient-to-tr from-violet-900 via-violet-800 to-violet-300 opacity-80 blur-3xl"></div>

                    {/* Animated border ring */}
                    <div className="absolute inset-0 rounded-full border-2 border-white/30 animate-pulse"></div>

                    {/* Inner glow rings */}
                    <div className="absolute inset-4 rounded-full border border-white/20 blur-sm"></div>
                    <div className="absolute inset-8 rounded-full border border-white/10 blur-md"></div>

                    {/* Radial lighting effect */}
                    <div className="absolute inset-0 rounded-full bg-gradient-to-bl from-transparent via-white/20 to-white/40 blur-2xl"></div>

                    {/* Sparkle effects */}
                    <div className="absolute bottom-10 left-10 h-2 w-2 rounded-full bg-white animate-ping"></div>
                    <div className="absolute bottom-20 left-20 h-1 w-1 rounded-full bg-white/80 animate-pulse"></div>
                </div>

                {/* Bottom right ambient circle */}
                <div className="absolute right-0 bottom-20 h-64 w-64">
                    <div className="absolute inset-1 rounded-full bg-white/20 shadow-white blur-2xl"></div>
                    <div className="absolute inset-0 rounded-full border border-white/30"></div>
                </div>

                {/* Extra ambient glow overlay */}
                <div className="absolute inset-0 bg-gradient-to-t from-violet-950/50 to-transparent"></div>

                {/* Additional floating particles for effect */}
                <div className="absolute top-1/4 right-1/4 h-1 w-1 rounded-full bg-white/50 animate-pulse"></div>
                <div className="absolute bottom-1/3 left-1/3 h-1 w-1 rounded-full bg-white/50 animate-pulse delay-300"></div>
                <div className="absolute top-2/3 right-1/3 h-1 w-1 rounded-full bg-white/50 animate-pulse delay-700"></div>

                {/* Content */}
                <div className="relative z-10 flex h-full flex-col items-center pt-20">
                    <h1 className="text-4xl font-bold text-white drop-shadow-lg">
                        CSR Leaderboards
                    </h1>

                    {/* Tab buttons - Enhanced */}
                    <div className="mt-6 flex gap-4">
                        {['Daily', 'Weekly', 'Monthly'].map((tab, index) => (
                            <button
                                key={tab}
                                className={`relative overflow-hidden rounded-full border border-white/10 px-6 py-2 text-gray-200 transition-all hover:shadow-lg hover:shadow-white/10 ${
                                    index === 0
                                        ? 'bg-white/5 backdrop-blur-sm hover:bg-white/20'
                                        : 'hover:bg-white/5'
                                }`}
                            >
                                {/* Button highlight */}
                                <div className='absolute top-0 left-0 h-1/2 w-full bg-gradient-to-b from-white/20 to-transparent'></div>
                                <span className="relative z-10">{tab}</span>
                            </button>
                        ))}
                    </div>

                    {/* Top 3 Leaderboard Cards */}
                    <div className="flex items-end gap-4">
                        {/* 2nd Place */}
                        <LeaderboardCard
                            rank={2}
                            initials="JS"
                            name="Jane Smith"
                            sales="₱98,000"
                            orders="134"
                            rating="4.7 ★"
                        />

                        {/* 1st Place */}
                        <LeaderboardCard
                            rank={1}
                            initials="NP"
                            name="Neil Patrick Mulingbayan"
                            sales="₱125,000"
                            orders="156"
                            rating="4.8 ★"
                            isTopRank={true}
                        />

                        {/* 3rd Place */}
                        <LeaderboardCard
                            rank={3}
                            initials="JD"
                            name="John Doe"
                            sales="₱76,500"
                            orders="98"
                            rating="4.6 ★"
                        />
                    </div>

                    {/* Other Leaderboard Entries */}
                    <div className="mt-8 w-full max-w-4xl space-y-2">
                        <LeaderboardEntry
                            rank={4}
                            initials="EM"
                            name="Emma Wilson"
                            amount="₱65,000"
                        />
                        <LeaderboardEntry
                            rank={5}
                            initials="MB"
                            name="Michael Brown"
                            amount="₱52,300"
                        />
                        <LeaderboardEntry
                            rank={6}
                            initials="SL"
                            name="Sarah Lee"
                            amount="₱48,900"
                        />
                    </div>
                </div>
            </div>
        );
    }
