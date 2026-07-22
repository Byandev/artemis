import PageHeader from '@/components/common/PageHeader';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';
import { Flame, Search, Trophy } from 'lucide-react';
import { useMemo, useState } from 'react';

interface EscMember {
    id: number;
    name: string;
    current_streak: number;
    longest_streak: number;
}

interface Props {
    workspace: Workspace;
    members: EscMember[];
}

export default function EscTracker({ workspace, members }: Props) {
    const [search, setSearch] = useState('');

    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();
        if (!term) return members;
        return members.filter((member) =>
            member.name.toLowerCase().includes(term),
        );
    }, [members, search]);

    return (
        <AppLayout>
            <Head title={`${workspace.name} - ESC Tracker`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="ESC Tracker"
                    description="Extreme Self-Care streaks for everyone in this workspace"
                />

                <div className="mb-3 flex items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                            placeholder="Search members…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>
                </div>

                <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-black/6 dark:border-white/6">
                                <th className="px-4 py-3 text-left font-mono text-[10px] font-medium tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500">
                                    Name
                                </th>
                                <th className="px-4 py-3 text-right font-mono text-[10px] font-medium tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500">
                                    Current Streak
                                </th>
                                <th className="px-4 py-3 text-right font-mono text-[10px] font-medium tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500">
                                    Longest Streak
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {filtered.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={3}
                                        className="px-4 py-12 text-center font-mono text-[12px] text-gray-400 dark:text-gray-500"
                                    >
                                        {members.length === 0
                                            ? 'No members in this workspace yet.'
                                            : 'No members match your search.'}
                                    </td>
                                </tr>
                            ) : (
                                filtered.map((member) => (
                                    <tr
                                        key={member.id}
                                        className="border-b border-black/5 last:border-b-0 dark:border-white/5"
                                    >
                                        <td className="px-4 py-3 text-[13px] font-medium text-gray-800 dark:text-gray-100">
                                            {member.name}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <span className="inline-flex items-center gap-1 rounded-full bg-orange-50 px-2.5 py-1 font-mono text-[11px] font-medium text-orange-600 dark:bg-orange-500/10 dark:text-orange-400">
                                                <Flame className="h-3.5 w-3.5" />
                                                {member.current_streak}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 font-mono text-[11px] font-medium text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                                                <Trophy className="h-3.5 w-3.5" />
                                                {member.longest_streak}
                                            </span>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
