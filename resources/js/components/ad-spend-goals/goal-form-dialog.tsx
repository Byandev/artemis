import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn, currencyFormatter } from '@/lib/utils';
import { Workspace } from '@/types/models/Workspace';
import { useForm } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

interface TeamMember {
    id: number;
    name: string;
}

interface TeamOption {
    id: number;
    name: string;
    members?: TeamMember[];
}

interface MilestoneInput {
    amount: number | '';
    label: string;
}

interface MemberInput {
    user_id: number;
    daily_target: number | '';
}

export interface Goal {
    id: number;
    team_id: number;
    daily_target: number;
    start_date: string;
    end_date: string;
    milestones?: { amount: number; label: string | null }[];
    members?: { user_id: number; daily_target: number }[];
}

interface GoalFormDialogProps {
    workspace: Workspace;
    teams: TeamOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    goal?: Goal | null;
}

const inputClass =
    'h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 transition-all outline-none placeholder:text-gray-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-brand-400';

const labelClass =
    'block font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';

export function GoalFormDialog({
    workspace,
    teams,
    open,
    onOpenChange,
    goal,
}: GoalFormDialogProps) {
    const isEditing = !!goal;
    const [splitMembers, setSplitMembers] = useState(false);

    const { data, setData, post, put, processing, errors, reset, transform } =
        useForm({
            team_id: '' as number | '',
            daily_target: '' as number | '',
            start_date: '',
            end_date: '',
            milestones: [] as MilestoneInput[],
            members: [] as MemberInput[],
        });

    useEffect(() => {
        if (goal) {
            setData({
                team_id: goal.team_id,
                daily_target: goal.daily_target,
                start_date: goal.start_date,
                end_date: goal.end_date,
                milestones: (goal.milestones ?? []).map((m) => ({
                    amount: m.amount,
                    label: m.label ?? '',
                })),
                members: (goal.members ?? []).map((m) => ({
                    user_id: m.user_id,
                    daily_target: m.daily_target,
                })),
            });
            setSplitMembers((goal.members?.length ?? 0) > 0);
        } else {
            reset();
            // New goals default to per-member targets so it's front-and-centre.
            setSplitMembers(true);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [goal, open]);

    const baseUrl = `/workspaces/${workspace.slug}/ad-spend-goals`;

    const selectedTeam = teams.find((t) => t.id === data.team_id);
    const teamMembers = selectedTeam?.members ?? [];
    const targetNum = data.daily_target === '' ? 0 : Number(data.daily_target);
    const membersTotal = data.members.reduce(
        (sum, m) => sum + (m.daily_target === '' ? 0 : Number(m.daily_target)),
        0,
    );
    const meetsTarget = membersTotal + 0.01 >= targetNum;

    // Even split of the target across the given members (last one absorbs the
    // rounding remainder so the slices sum to exactly the target).
    const evenSplitFor = (members: TeamMember[]): MemberInput[] => {
        const n = members.length;
        if (!n || targetNum <= 0) {
            return members.map((m) => ({ user_id: m.id, daily_target: '' }));
        }
        const each = Math.round((targetNum / n) * 100) / 100;
        return members.map((m, i) => ({
            user_id: m.id,
            daily_target:
                i === n - 1
                    ? Math.round((targetNum - each * (n - 1)) * 100) / 100
                    : each,
        }));
    };

    const applyEvenSplit = () => setData('members', evenSplitFor(teamMembers));

    const memberTargetOf = (userId: number): number | '' =>
        data.members.find((m) => m.user_id === userId)?.daily_target ?? '';

    const setMemberTarget = (userId: number, value: number | '') =>
        setData(
            'members',
            data.members.some((m) => m.user_id === userId)
                ? data.members.map((m) =>
                      m.user_id === userId ? { ...m, daily_target: value } : m,
                  )
                : [...data.members, { user_id: userId, daily_target: value }],
        );

    const onTeamChange = (val: number | '') => {
        setData('team_id', val);
        const nextMembers = teams.find((t) => t.id === val)?.members ?? [];
        setData('members', splitMembers ? evenSplitFor(nextMembers) : []);
    };

    const toggleSplit = (on: boolean) => {
        setSplitMembers(on);
        if (!on) {
            setData('members', []);
        } else if (data.members.length === 0) {
            applyEvenSplit();
        }
    };

    const addMilestone = () =>
        setData('milestones', [...data.milestones, { amount: '', label: '' }]);

    const updateMilestone = (i: number, patch: Partial<MilestoneInput>) =>
        setData(
            'milestones',
            data.milestones.map((m, idx) =>
                idx === i ? { ...m, ...patch } : m,
            ),
        );

    const removeMilestone = (i: number) =>
        setData(
            'milestones',
            data.milestones.filter((_, idx) => idx !== i),
        );

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // Drop empty milestone rows; send member slices only when splitting.
        transform((payload) => ({
            ...payload,
            milestones: payload.milestones.filter(
                (m) => m.amount !== '' && m.amount !== null,
            ),
            members: splitMembers
                ? payload.members.map((m) => ({
                      user_id: m.user_id,
                      daily_target: m.daily_target === '' ? 0 : m.daily_target,
                  }))
                : [],
        }));

        const onSuccess = () => {
            toast.success(
                isEditing
                    ? 'Ad spend goal updated successfully!'
                    : 'Ad spend goal created successfully!',
            );
            reset();
            onOpenChange(false);
        };

        if (isEditing) {
            put(`${baseUrl}/${goal.id}`, { preserveScroll: true, onSuccess });
        } else {
            post(baseUrl, { preserveScroll: true, onSuccess });
        }
    };

    const handleOpenChange = (next: boolean) => {
        onOpenChange(next);
        if (!next) reset();
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-md">
                <div className="border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            {isEditing ? 'Edit Ad Spend Goal' : 'New Ad Spend Goal'}
                        </DialogTitle>
                        <DialogDescription className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                            Set a daily ad-spend target for a team over a date
                            range.
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="max-h-[65vh] space-y-5 overflow-y-auto px-5 py-4">
                        {/* Team */}
                        <div className="space-y-1.5">
                            <label className={labelClass}>
                                Team <span className="text-red-400">*</span>
                            </label>
                            <select
                                value={data.team_id}
                                onChange={(e) =>
                                    onTeamChange(
                                        e.target.value === ''
                                            ? ''
                                            : Number(e.target.value),
                                    )
                                }
                                className={inputClass}
                            >
                                <option value="">Select a team…</option>
                                {teams.map((team) => (
                                    <option key={team.id} value={team.id}>
                                        {team.name}
                                    </option>
                                ))}
                            </select>
                            {errors.team_id && (
                                <p className="font-mono text-[11px] text-red-500">
                                    {errors.team_id}
                                </p>
                            )}
                        </div>

                        {/* Daily target */}
                        <div className="space-y-1.5">
                            <label className={labelClass}>
                                Daily Target (₱){' '}
                                <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="e.g. 500000"
                                value={data.daily_target}
                                onChange={(e) =>
                                    setData(
                                        'daily_target',
                                        e.target.value === ''
                                            ? ''
                                            : Number(e.target.value),
                                    )
                                }
                                className={inputClass}
                            />
                            <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                The ad-spend amount the team should hit each day.
                            </p>
                            {errors.daily_target && (
                                <p className="font-mono text-[11px] text-red-500">
                                    {errors.daily_target}
                                </p>
                            )}
                        </div>

                        {/* Dates */}
                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <label className={labelClass}>
                                    Start Date{' '}
                                    <span className="text-red-400">*</span>
                                </label>
                                <input
                                    type="date"
                                    value={data.start_date}
                                    onChange={(e) =>
                                        setData('start_date', e.target.value)
                                    }
                                    className={inputClass}
                                />
                                {errors.start_date && (
                                    <p className="font-mono text-[11px] text-red-500">
                                        {errors.start_date}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-1.5">
                                <label className={labelClass}>
                                    End Date{' '}
                                    <span className="text-red-400">*</span>
                                </label>
                                <input
                                    type="date"
                                    value={data.end_date}
                                    min={data.start_date || undefined}
                                    onChange={(e) =>
                                        setData('end_date', e.target.value)
                                    }
                                    className={inputClass}
                                />
                                {errors.end_date && (
                                    <p className="font-mono text-[11px] text-red-500">
                                        {errors.end_date}
                                    </p>
                                )}
                            </div>
                        </div>

                        {/* Milestones (optional) */}
                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <label className={labelClass}>
                                    Milestones (optional)
                                </label>
                                <button
                                    type="button"
                                    onClick={addMilestone}
                                    className="flex items-center gap-1 font-mono text-[11px] font-medium text-brand-600 transition-colors hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300"
                                >
                                    <Plus className="h-3.5 w-3.5" /> Add
                                </button>
                            </div>

                            {data.milestones.length === 0 ? (
                                <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                    Stepping-stone daily amounts below the target
                                    (e.g. 300000). Optional.
                                </p>
                            ) : (
                                <div className="space-y-2">
                                    {data.milestones.map((m, i) => (
                                        <div
                                            key={i}
                                            className="flex items-center gap-2"
                                        >
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                placeholder="Amount"
                                                value={m.amount}
                                                onChange={(e) =>
                                                    updateMilestone(i, {
                                                        amount:
                                                            e.target.value === ''
                                                                ? ''
                                                                : Number(
                                                                      e.target
                                                                          .value,
                                                                  ),
                                                    })
                                                }
                                                className={`${inputClass} flex-1`}
                                            />
                                            <input
                                                type="text"
                                                placeholder="Label (optional)"
                                                value={m.label}
                                                onChange={(e) =>
                                                    updateMilestone(i, {
                                                        label: e.target.value,
                                                    })
                                                }
                                                className={`${inputClass} flex-1`}
                                            />
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    removeMilestone(i)
                                                }
                                                className="flex h-10 w-9 shrink-0 items-center justify-center rounded-[10px] border border-black/8 text-gray-400 transition-all hover:bg-error-100 hover:text-error-600 dark:border-white/8 dark:hover:bg-error-500/10 dark:hover:text-error-400"
                                            >
                                                <X className="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            )}

                            {Object.keys(errors)
                                .filter((k) => k.startsWith('milestones'))
                                .map((k) => (
                                    <p
                                        key={k}
                                        className="font-mono text-[11px] text-red-500"
                                    >
                                        {
                                            (errors as Record<string, string>)[
                                                k
                                            ]
                                        }
                                    </p>
                                ))}
                        </div>

                        {/* Per-member goals (optional) */}
                        <div className="space-y-2">
                            <label className="flex cursor-pointer items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={splitMembers}
                                    onChange={(e) =>
                                        toggleSplit(e.target.checked)
                                    }
                                    className="h-3.5 w-3.5 accent-brand-600"
                                />
                                <span className="font-mono text-[10px] font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">
                                    Split target across team members
                                </span>
                            </label>

                            {splitMembers && (
                                <div className="space-y-2 rounded-[12px] border border-black/6 bg-stone-50/50 p-3 dark:border-white/6 dark:bg-zinc-800/30">
                                    {data.team_id === '' ? (
                                        <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                            Select a team first.
                                        </p>
                                    ) : teamMembers.length === 0 ? (
                                        <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                            This team has no members.
                                        </p>
                                    ) : (
                                        <>
                                            <div className="flex items-center justify-between">
                                                <span className="font-mono text-[9px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                                    {teamMembers.length} members
                                                </span>
                                                <button
                                                    type="button"
                                                    onClick={applyEvenSplit}
                                                    className="font-mono text-[11px] font-medium text-brand-600 transition-colors hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300"
                                                >
                                                    Even split
                                                </button>
                                            </div>
                                            {teamMembers.map((m) => (
                                                <div
                                                    key={m.id}
                                                    className="flex items-center gap-2"
                                                >
                                                    <span className="min-w-0 flex-1 truncate font-mono text-[12px] text-gray-700 dark:text-gray-300">
                                                        {m.name}
                                                    </span>
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        placeholder="0"
                                                        value={memberTargetOf(
                                                            m.id,
                                                        )}
                                                        onChange={(e) =>
                                                            setMemberTarget(
                                                                m.id,
                                                                e.target
                                                                    .value === ''
                                                                    ? ''
                                                                    : Number(
                                                                          e.target
                                                                              .value,
                                                                      ),
                                                            )
                                                        }
                                                        className={cn(
                                                            inputClass,
                                                            'w-28 shrink-0',
                                                        )}
                                                    />
                                                </div>
                                            ))}
                                            <div className="flex items-center justify-between border-t border-black/6 pt-2 dark:border-white/6">
                                                <span className="font-mono text-[9px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                                    Total
                                                </span>
                                                <span
                                                    className={`font-mono text-[12px] font-semibold tabular-nums ${meetsTarget ? 'text-brand-600 dark:text-brand-400' : 'text-warning-600 dark:text-warning-400'}`}
                                                >
                                                    {currencyFormatter(
                                                        membersTotal,
                                                    )}{' '}
                                                    /{' '}
                                                    {currencyFormatter(
                                                        targetNum,
                                                    )}
                                                    {meetsTarget ? ' ✓' : ''}
                                                </span>
                                            </div>
                                        </>
                                    )}

                                    {Object.keys(errors)
                                        .filter((k) => k.startsWith('members'))
                                        .map((k) => (
                                            <p
                                                key={k}
                                                className="font-mono text-[11px] text-red-500"
                                            >
                                                {
                                                    (
                                                        errors as Record<
                                                            string,
                                                            string
                                                        >
                                                    )[k]
                                                }
                                            </p>
                                        ))}
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-2 border-t border-black/6 px-5 py-3 dark:border-white/6">
                        <button
                            type="button"
                            onClick={() => onOpenChange(false)}
                            className="flex h-9 items-center rounded-lg border border-black/8 bg-stone-100 px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-200 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="flex h-9 items-center rounded-lg bg-brand-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-brand-700 disabled:opacity-50"
                        >
                            {processing
                                ? isEditing
                                    ? 'Saving…'
                                    : 'Creating…'
                                : isEditing
                                  ? 'Save Changes'
                                  : 'Create Goal'}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
