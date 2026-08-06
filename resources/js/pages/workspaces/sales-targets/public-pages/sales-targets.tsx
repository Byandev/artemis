import {
    BoardTeam,
    GameboardHeader,
} from '@/components/sales-targets/gameboard-header';
import {
    GameboardKpiRow,
    GameboardKpis,
} from '@/components/sales-targets/gameboard-kpis';
import { GameboardLeader } from '@/components/sales-targets/gameboard-leader';
import { Head, useForm } from '@inertiajs/react';
import { Lock, Target } from 'lucide-react';

interface PublicWorkspace {
    id: number;
    name: string;
    slug: string;
}

interface Props {
    workspace: PublicWorkspace;
    /** When true the password gate is shown and no data is sent. */
    locked?: boolean;
    /** The day the board is scored on — today's target, else the most recent. */
    featured?: { id: number; name: string; date: string } | null;
    kpis?: GameboardKpis | null;
    /** The teams on the featured day, and the one the board is narrowed to. */
    teams?: BoardTeam[];
    teamId?: number | null;
}

/** Password gate shown before the board when the workspace requires it. */
function SalesTargetsLock({ workspace }: { workspace: PublicWorkspace }) {
    const form = useForm({ password: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(
            `/public/workspaces/${workspace.slug}/sales-targets/verify-password`,
            {
                preserveScroll: true,
                onError: () => form.reset('password'),
            },
        );
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-stone-50 p-4 dark:bg-zinc-950">
            <div className="w-full max-w-sm rounded-2xl border border-black/8 bg-white p-6 dark:border-white/8 dark:bg-zinc-900">
                <div className="mb-4 flex flex-col items-center text-center">
                    <div className="mb-3 flex h-11 w-11 items-center justify-center rounded-full bg-stone-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400">
                        <Lock className="h-5 w-5" />
                    </div>
                    <h1 className="text-[16px] font-semibold text-gray-900 dark:text-gray-100">
                        Protected page
                    </h1>
                    <p className="mt-1 text-[12px] text-gray-400 dark:text-gray-500">
                        Enter the password to view {workspace.name}&apos;s sales
                        targets.
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
                        className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 transition-all outline-none placeholder:text-gray-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600"
                    />
                    {form.errors.password && (
                        <p className="text-center font-mono text-[11px] text-red-500">
                            {form.errors.password}
                        </p>
                    )}
                    <button
                        type="submit"
                        disabled={form.processing || !form.data.password}
                        className="h-10 w-full rounded-[10px] bg-brand-600 font-mono! text-[12px]! font-medium text-white transition-colors hover:bg-brand-700 disabled:opacity-50"
                    >
                        {form.processing ? 'Unlocking…' : 'Unlock'}
                    </button>
                </form>
            </div>
        </div>
    );
}

export default function PublicSalesTargets({
    workspace,
    locked,
    featured,
    kpis,
    teams,
    teamId,
}: Props) {
    if (locked) {
        return <SalesTargetsLock workspace={workspace} />;
    }

    return (
        <div className="min-h-screen bg-stone-50 dark:bg-zinc-950">
            <Head title={`${workspace.name} - Sales Targets`} />

            <GameboardHeader
                workspaceName={workspace.name}
                featured={featured}
                teams={teams}
                teamId={teamId}
            />

            <div className="mx-auto w-full max-w-(--breakpoint-2xl) px-4 py-3 md:px-6">
                {kpis ? (
                    <>
                        <GameboardKpiRow kpis={kpis} />
                        {kpis.leader && (
                            <GameboardLeader
                                leader={kpis.leader}
                                qualifyingRoas={kpis.qualifying_roas}
                            />
                        )}
                    </>
                ) : (
                    <div className="flex flex-col items-center justify-center rounded-[16px] border border-dashed border-black/8 bg-white py-20 dark:border-white/8 dark:bg-zinc-900">
                        <div className="rounded-2xl bg-stone-100 p-3.5 dark:bg-zinc-800">
                            <Target className="h-7 w-7 text-gray-400 dark:text-gray-500" />
                        </div>
                        <p className="mt-4 text-[14px] font-semibold text-gray-700 dark:text-gray-200">
                            No sales targets yet
                        </p>
                        <p className="mt-1 text-[12px] text-gray-400 dark:text-gray-500">
                            The board lights up once a target is set for the
                            day.
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
}
