import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, Users } from 'lucide-react';

interface TeamScope {
    activeTeamId: number | null;
    teams: { id: number; name: string }[];
    canViewAll: boolean;
}

/**
 * "Viewing data for" switcher. Picking a team appends ?team_id= to the URL;
 * the server validates it and persists the choice to the session so it sticks
 * across navigation. Acts as a view filter over everything that is team-scoped.
 */
const TeamSwitcher = () => {
    const { teamScope } = usePage<{ teamScope: TeamScope | null }>().props;

    if (!teamScope) return null;

    const { activeTeamId, teams, canViewAll } = teamScope;

    // A single-team scoped user has nothing to switch between.
    if (!canViewAll && teams.length <= 1) return null;

    const select = (value: string) => {
        const url = new URL(window.location.href);
        url.searchParams.set('team_id', value);
        router.get(url.pathname + url.search, {}, { preserveScroll: true });
    };

    const active = teams.find((t) => t.id === activeTeamId);
    const allLabel = canViewAll ? 'All teams' : 'All my teams';
    const label = active ? active.name : allLabel;

    const rowClass = (selected: boolean) =>
        [
            'flex w-full cursor-pointer items-center gap-2.5 rounded-[8px] px-2 py-2 text-[13px] transition-colors',
            selected
                ? 'bg-emerald-500/[0.08] font-medium text-emerald-600 dark:bg-emerald-500/[0.10] dark:text-emerald-400'
                : 'text-gray-600 hover:bg-black/[0.03] dark:text-gray-400 dark:hover:bg-white/[0.04]',
        ].join(' ');

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button className="inline-flex h-8 max-w-full min-w-0 items-center gap-2 rounded-[8px] bg-black/[0.04] px-2 transition-colors duration-150 outline-none hover:bg-black/[0.07] dark:bg-white/[0.05] dark:hover:bg-white/[0.08]">
                    <Users className="h-3.5 w-3.5 shrink-0 text-gray-400" />
                    <span className="hidden min-w-0 truncate text-[13px] font-medium text-gray-700 sm:inline dark:text-gray-200">
                        {label}
                    </span>
                    <ChevronsUpDown className="hidden h-3 w-3 shrink-0 text-gray-300 sm:block dark:text-gray-600" />
                </button>
            </DropdownMenuTrigger>

            <DropdownMenuContent
                align="start"
                className="w-56 rounded-[14px] border border-black/6 bg-white p-2 shadow-[0_8px_30px_rgba(0,0,0,0.08)] dark:border-white/6 dark:bg-zinc-900 dark:shadow-[0_8px_30px_rgba(0,0,0,0.4)]"
            >
                <p className="px-1 pb-1 font-mono text-[10px] font-medium tracking-wider text-gray-300 uppercase dark:text-gray-600">
                    Viewing data for
                </p>

                <DropdownMenuItem
                    onSelect={() => select('all')}
                    className={rowClass(activeTeamId === null)}
                >
                    <span className="flex-1 truncate">{allLabel}</span>
                    {activeTeamId === null && (
                        <Check className="h-3.5 w-3.5 shrink-0" />
                    )}
                </DropdownMenuItem>

                {teams.map((team) => (
                    <DropdownMenuItem
                        key={team.id}
                        onSelect={() => select(String(team.id))}
                        className={rowClass(activeTeamId === team.id)}
                    >
                        <span className="flex-1 truncate">{team.name}</span>
                        {activeTeamId === team.id && (
                            <Check className="h-3.5 w-3.5 shrink-0" />
                        )}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
};

export default TeamSwitcher;
