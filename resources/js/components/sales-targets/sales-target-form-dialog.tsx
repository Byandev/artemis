import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { currencyFormatter } from '@/lib/utils';
import { Workspace } from '@/types/models/Workspace';
import { useForm } from '@inertiajs/react';
import { useEffect, useId } from 'react';
import { toast } from 'sonner';

export interface TeamOption {
    id: number;
    name: string;
}

export interface SalesTargetShape {
    id: number;
    date: string;
    name: string;
    team_targets: {
        team_id: number;
        sales_target: string;
    }[];
}

interface Props {
    workspace: Workspace;
    teams: TeamOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    target?: SalesTargetShape | null;
}

export function SalesTargetFormDialog({
    workspace,
    teams,
    open,
    onOpenChange,
    target,
}: Props) {
    const isEditing = !!target;
    const base = `/workspaces/${workspace.slug}/sales-marketing/dashboard/sales-targets`;
    const fieldId = useId();

    /**
     * Every team gets a row, keyed by team id, and a blank amount means "not in
     * this target". Listing them all rather than making the user add rows from a
     * dropdown turns setting targets into one pass down a known list — nothing
     * to recall, no way to add the same team twice, and the teams left without a
     * number are visible instead of merely absent.
     */
    const { data, setData, post, put, processing, errors, reset, transform } =
        useForm({
            date: '',
            name: '',
            amounts: {} as Record<string, string>,
        });

    // The server validates a `teams` array, which isn't a key of the form data
    // above, so its error arrives outside the typed error map.
    const teamsError = (errors as Record<string, string | undefined>).teams;

    useEffect(() => {
        if (!open) return;

        if (target) {
            setData({
                date: target.date,
                name: target.name,
                amounts: Object.fromEntries(
                    target.team_targets.map((row) => [
                        String(row.team_id),
                        row.sales_target,
                    ]),
                ),
            });
        } else {
            reset();
        }
    }, [target, open]);

    const filled = teams.filter((team) => {
        const value = data.amounts[String(team.id)];
        return value !== undefined && value !== '';
    });

    const total = filled.reduce(
        (sum, team) => sum + (Number(data.amounts[String(team.id)]) || 0),
        0,
    );

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    isEditing ? 'Sales target updated' : 'Sales target created',
                );
                reset();
                onOpenChange(false);
            },
        };

        // Reshape the per-team map into the array the server validates. Only
        // teams with an amount are sent — the payload is exactly the set of rows
        // the target should end up with.
        transform((payload) => ({
            date: payload.date,
            name: payload.name,
            teams: filled.map((team) => ({
                team_id: team.id,
                sales_target: payload.amounts[String(team.id)],
            })),
        }));

        if (isEditing) {
            put(`${base}/${target.id}`, options);
        } else {
            post(base, options);
        }
    };

    const handleOpenChange = (next: boolean) => {
        onOpenChange(next);
        if (!next) reset();
    };

    const inputClass =
        'h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 transition-all outline-none placeholder:text-gray-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400';
    const labelClass =
        'block font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-lg">
                <div className="border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            {isEditing
                                ? 'Edit Sales Target'
                                : 'New Sales Target'}
                        </DialogTitle>
                        <DialogDescription className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                            Pick a date, then set what each team should hit on
                            it. Leave a team blank to leave it out.
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="max-h-[55vh] space-y-5 overflow-y-auto px-5 py-4">
                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <label
                                    htmlFor={`${fieldId}-date`}
                                    className={labelClass}
                                >
                                    Date <span className="text-red-400">*</span>
                                </label>
                                <input
                                    id={`${fieldId}-date`}
                                    type="date"
                                    value={data.date}
                                    onChange={(e) =>
                                        setData('date', e.target.value)
                                    }
                                    className={inputClass}
                                />
                                {errors.date && (
                                    <p className="font-mono text-[11px] text-red-500">
                                        {errors.date}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-1.5">
                                <label
                                    htmlFor={`${fieldId}-name`}
                                    className={labelClass}
                                >
                                    Name <span className="text-red-400">*</span>
                                </label>
                                <input
                                    id={`${fieldId}-name`}
                                    type="text"
                                    placeholder="7:7"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    className={inputClass}
                                />
                                {errors.name && (
                                    <p className="font-mono text-[11px] text-red-500">
                                        {errors.name}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <div className="flex items-baseline justify-between">
                                <label className={labelClass}>
                                    Targets Per Team{' '}
                                    <span className="text-red-400">*</span>
                                </label>
                                <span className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                    {filled.length} of {teams.length} set
                                </span>
                            </div>

                            {teams.length === 0 ? (
                                <p className="rounded-[10px] border border-dashed border-black/8 px-3 py-4 text-center font-mono text-[11px] text-gray-400 dark:border-white/8 dark:text-gray-500">
                                    No teams available.
                                </p>
                            ) : (
                                <div className="overflow-hidden rounded-[10px] border border-black/8 dark:border-white/8">
                                    {teams.map((team, index) => {
                                        const value =
                                            data.amounts[String(team.id)] ?? '';

                                        return (
                                            <div
                                                key={team.id}
                                                className={`flex items-center gap-3 px-3 py-2 ${
                                                    index > 0
                                                        ? 'border-t border-black/5 dark:border-white/5'
                                                        : ''
                                                } ${value === '' ? '' : 'bg-emerald-50/40 dark:bg-emerald-500/5'}`}
                                            >
                                                <label
                                                    htmlFor={`${fieldId}-team-${team.id}`}
                                                    className="flex-1 truncate text-[12px] text-gray-700 dark:text-gray-300"
                                                >
                                                    {team.name}
                                                </label>
                                                <input
                                                    id={`${fieldId}-team-${team.id}`}
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    inputMode="decimal"
                                                    placeholder="—"
                                                    value={value}
                                                    onChange={(e) =>
                                                        setData('amounts', {
                                                            ...data.amounts,
                                                            [String(team.id)]:
                                                                e.target.value,
                                                        })
                                                    }
                                                    className="h-8 w-40 rounded-lg border border-black/8 bg-white px-2.5 text-right font-mono! text-[12px]! text-gray-800 tabular-nums transition-all outline-none placeholder:text-gray-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                                                />
                                            </div>
                                        );
                                    })}
                                </div>
                            )}

                            {teamsError && (
                                <p className="font-mono text-[11px] text-red-500">
                                    {teamsError}
                                </p>
                            )}

                            <div className="flex items-baseline justify-between pt-1">
                                <span className="font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                    Total
                                </span>
                                <span className="font-mono text-[13px] font-semibold text-gray-800 tabular-nums dark:text-gray-100">
                                    {currencyFormatter(total)}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center justify-between gap-2 border-t border-black/6 px-5 py-4 dark:border-white/6">
                        {/* Say why the button is off rather than just disabling it. */}
                        <span className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                            {filled.length === 0 && 'Set at least one team'}
                        </span>
                        <div className="flex gap-2">
                            <button
                                type="button"
                                onClick={() => handleOpenChange(false)}
                                className="h-9 rounded-lg px-4 font-mono! text-[12px]! font-medium text-gray-500 transition-colors hover:bg-stone-100 dark:text-gray-400 dark:hover:bg-zinc-800"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={processing || filled.length === 0}
                                className="h-9 rounded-lg bg-brand-600 px-4 font-mono! text-[12px]! font-medium text-white shadow-sm transition-all hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {processing
                                    ? 'Saving...'
                                    : isEditing
                                      ? 'Save Changes'
                                      : 'Create Target'}
                            </button>
                        </div>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
