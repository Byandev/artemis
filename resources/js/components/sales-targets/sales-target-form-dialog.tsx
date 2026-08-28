import DatePicker from '@/components/ui/date-picker';
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
import { format } from 'date-fns';
import { useEffect, useId, useState } from 'react';
import { toast } from 'sonner';

export interface TeamOption {
    id: number;
    name: string;
}

/**
 * The bounds the server enforces, kept in step with SalesTargetController.
 *
 * The picker below is bounded by these too, so this is the second line rather
 * than the first — but its header year is still a typable number field, and a
 * year the `date` column can't hold used to surface as a server error rather
 * than a message on the field.
 */
const MIN_DATE = '2000-01-01';
const MAX_DATE = '2100-12-31';

/** decimal(15,2) and decimal(10,2) respectively, matching the columns. */
const MAX_AMOUNT = 9_999_999_999_999.99;
const MAX_ROAS = 99_999_999.99;

const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;

/**
 * Whether the calendar actually has that day. Date rolls Feb 30 forward into
 * March rather than rejecting it, so the round trip is the check.
 */
function isRealDate(iso: string): boolean {
    const [year, month, day] = iso.split('-').map(Number);
    const parsed = new Date(year, month - 1, day);

    return (
        parsed.getFullYear() === year &&
        parsed.getMonth() === month - 1 &&
        parsed.getDate() === day
    );
}

function dateIssue(value: string): string | null {
    if (value === '') return 'Pick a date.';
    if (!ISO_DATE.test(value)) return 'Pick a full date from the calendar.';
    if (!isRealDate(value)) return 'That day is not on the calendar.';
    // Both sides are fixed-width ISO by now, so string order is date order.
    if (value < MIN_DATE || value > MAX_DATE) {
        return 'Pick a date between 2000 and 2100.';
    }

    return null;
}

/** Blank is not an error — an empty box means "not set", not zero. */
function amountIssue(value: string, label: string, max: number): string | null {
    if (value === '') return null;

    const parsed = Number(value);

    if (!Number.isFinite(parsed)) return `${label} must be a number.`;
    if (parsed < 0) return `${label} cannot be negative.`;
    if (parsed > max) return `${label} is too large.`;

    return null;
}

export interface SalesTargetShape {
    id: number;
    date: string;
    name: string;
    target_roas: string | null;
    team_targets: {
        team_id: number;
        sales_target: string | null;
        ad_budget: string | null;
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
    const base = `/workspaces/${workspace.slug}/sales-marketing/sales-targets`;
    const fieldId = useId();

    /**
     * Every team gets a row, keyed by team id, and the tick is what puts it on
     * the target. Listing them all rather than making the user add rows from a
     * dropdown turns setting targets into one pass down a known list — nothing
     * to recall, no way to add the same team twice, and the teams left off are
     * visible instead of merely absent.
     *
     * The tick is separate from the numbers on purpose: a team can be on the
     * target for its ad budget alone, with no sales amount to hit.
     */
    const { data, setData, post, put, processing, errors, reset, transform } =
        useForm({
            date: '',
            name: '',
            target_roas: '',
            included: [] as number[],
            amounts: {} as Record<string, string>,
            budgets: {} as Record<string, string>,
        });

    // The server validates a `teams` array, which isn't a key of the form data
    // above, so its errors arrive outside the typed error map.
    const serverErrors = errors as Record<string, string | undefined>;
    const teamsError = serverErrors.teams;

    /**
     * Whether a save has been tried. The checks below normally keep the button
     * off, which means the messages have to stand on their own — nothing here
     * waits for a submit that a blocked button will never deliver. This only
     * covers the ways a form submits anyway, like Enter in a text field.
     */
    const [attempted, setAttempted] = useState(false);

    useEffect(() => {
        if (!open) return;

        setAttempted(false);

        if (target) {
            setData({
                date: target.date,
                name: target.name,
                target_roas: target.target_roas ?? '',
                included: target.team_targets.map((row) => row.team_id),
                amounts: Object.fromEntries(
                    target.team_targets.map((row) => [
                        String(row.team_id),
                        row.sales_target ?? '',
                    ]),
                ),
                budgets: Object.fromEntries(
                    target.team_targets.map((row) => [
                        String(row.team_id),
                        row.ad_budget ?? '',
                    ]),
                ),
            });
        } else {
            reset();
        }
    }, [target, open]);

    const isIncluded = (teamId: number) => data.included.includes(teamId);

    const toggleTeam = (teamId: number, next: boolean) =>
        setData(
            'included',
            next
                ? [...data.included, teamId]
                : data.included.filter((id) => id !== teamId),
        );

    /** Typing a number is itself a decision to include the team. */
    const setAmount = (
        key: 'amounts' | 'budgets',
        teamId: number,
        value: string,
    ) => {
        setData((current) => ({
            ...current,
            [key]: { ...current[key], [String(teamId)]: value },
            included:
                value !== '' && !current.included.includes(teamId)
                    ? [...current.included, teamId]
                    : current.included,
        }));
    };

    const selected = teams.filter((team) => isIncluded(team.id));

    const sum = (map: Record<string, string>) =>
        selected.reduce(
            (total, team) => total + (Number(map[String(team.id)]) || 0),
            0,
        );

    const totalSales = sum(data.amounts);
    const totalBudget = sum(data.budgets);

    /**
     * Everything the server would reject, checked here so the form can say so
     * on the spot instead of round-tripping to a 422 — the date especially,
     * which is the one field a user can put a value in that no column will take.
     */
    const rowIssues: Record<string, string> = {};

    for (const team of selected) {
        const key = String(team.id);
        const issue =
            amountIssue(data.amounts[key] ?? '', 'Sales target', MAX_AMOUNT) ??
            amountIssue(data.budgets[key] ?? '', 'Ad budget', MAX_AMOUNT);

        if (issue) rowIssues[key] = issue;
    }

    const issues = {
        date: dateIssue(data.date),
        name:
            data.name.trim() === ''
                ? 'Give the target a name.'
                : data.name.length > 255
                  ? 'Keep the name to 255 characters.'
                  : null,
        target_roas: amountIssue(data.target_roas, 'Target ROAS', MAX_ROAS),
        teams: selected.length === 0 ? 'Select at least one team.' : null,
    };

    // Read down the form, so the hint points at the first thing to fix.
    const blocking =
        issues.date ??
        issues.name ??
        issues.target_roas ??
        issues.teams ??
        Object.values(rowIssues)[0] ??
        null;

    /**
     * A field only nags once it holds something wrong, or once a save has been
     * tried — an untouched form shouldn't open in red. The footer carries the
     * blocking reason in the meantime, so the disabled button is never silent.
     */
    const show = (field: 'date' | 'name' | 'target_roas') =>
        (attempted || data[field] !== '' ? issues[field] : null) ??
        serverErrors[field];

    const teamsMessage = (attempted ? issues.teams : null) ?? teamsError;

    /** Server errors on the teams array come back keyed by payload index. */
    const rowError = (teamId: number, index: number) =>
        rowIssues[String(teamId)] ??
        serverErrors[`teams.${index}.sales_target`] ??
        serverErrors[`teams.${index}.ad_budget`] ??
        serverErrors[`teams.${index}.team_id`];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        setAttempted(true);

        if (blocking) return;

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

        // Reshape the per-team maps into the array the server validates. Only
        // ticked teams are sent — the payload is exactly the set of rows the
        // target should end up with.
        transform((payload) => ({
            date: payload.date,
            name: payload.name,
            target_roas: payload.target_roas,
            teams: selected.map((team) => ({
                team_id: team.id,
                sales_target: payload.amounts[String(team.id)] || null,
                ad_budget: payload.budgets[String(team.id)] || null,
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

    // h-9 to sit level with the date picker beside it, which is fixed at that.
    const inputClass =
        'h-9 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 transition-all outline-none placeholder:text-gray-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400';
    const labelClass =
        'block font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';
    const cellClass =
        'h-8 w-32 rounded-lg border border-black/8 bg-white px-2.5 text-right font-mono! text-[12px]! text-gray-800 tabular-nums transition-all outline-none placeholder:text-gray-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400';

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-2xl">
                <div className="border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            {isEditing
                                ? 'Edit Sales Target'
                                : 'New Sales Target'}
                        </DialogTitle>
                        <DialogDescription className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                            Pick a date, then tick the teams on the target and
                            set what each should hit and spend.
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="max-h-[55vh] space-y-5 overflow-y-auto px-5 py-4">
                        <div className="grid grid-cols-3 gap-3">
                            <div className="space-y-1.5">
                                <label
                                    htmlFor={`${fieldId}-date`}
                                    className={labelClass}
                                >
                                    Date <span className="text-red-400">*</span>
                                </label>
                                {/* Picked, never typed — a calendar can't
                                    produce a day that isn't one. `key` gives
                                    each target a fresh flatpickr instance. */}
                                <DatePicker
                                    key={`${fieldId}-${target?.id ?? 'new'}-${open}`}
                                    id={`${fieldId}-date`}
                                    mode="single"
                                    fullWidth
                                    placeholder="Select date"
                                    defaultDate={data.date || undefined}
                                    minDate={MIN_DATE}
                                    maxDate={MAX_DATE}
                                    onChange={(dates) =>
                                        setData(
                                            'date',
                                            dates.length
                                                ? format(dates[0], 'yyyy-MM-dd')
                                                : '',
                                        )
                                    }
                                />
                                {show('date') && (
                                    <p className="font-mono text-[11px] text-red-500">
                                        {show('date')}
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
                                {show('name') && (
                                    <p className="font-mono text-[11px] text-red-500">
                                        {show('name')}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-1.5">
                                <label
                                    htmlFor={`${fieldId}-roas`}
                                    className={labelClass}
                                >
                                    Target ROAS
                                </label>
                                <input
                                    id={`${fieldId}-roas`}
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    inputMode="decimal"
                                    placeholder="5.00"
                                    value={data.target_roas}
                                    onChange={(e) =>
                                        setData('target_roas', e.target.value)
                                    }
                                    className={inputClass}
                                />
                                {show('target_roas') && (
                                    <p className="font-mono text-[11px] text-red-500">
                                        {show('target_roas')}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <div className="flex items-baseline justify-between">
                                <label className={labelClass}>
                                    Teams On This Target{' '}
                                    <span className="text-red-400">*</span>
                                </label>
                                <span className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                    {selected.length} of {teams.length} selected
                                </span>
                            </div>

                            {teams.length === 0 ? (
                                <p className="rounded-[10px] border border-dashed border-black/8 px-3 py-4 text-center font-mono text-[11px] text-gray-400 dark:border-white/8 dark:text-gray-500">
                                    No teams available.
                                </p>
                            ) : (
                                <div className="overflow-hidden rounded-[10px] border border-black/8 dark:border-white/8">
                                    <div className="flex items-center gap-3 bg-stone-50 px-3 py-2 dark:bg-zinc-800/50">
                                        <span className="flex-1 font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                            Team
                                        </span>
                                        <span className="w-32 text-right font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                            Sales Target
                                        </span>
                                        <span className="w-32 text-right font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                            Ad Budget
                                        </span>
                                    </div>

                                    {teams.map((team) => {
                                        const included = isIncluded(team.id);
                                        const message = included
                                            ? rowError(
                                                  team.id,
                                                  selected.indexOf(team),
                                              )
                                            : undefined;

                                        return (
                                            <div
                                                key={team.id}
                                                className={`border-t border-black/5 px-3 py-2 dark:border-white/5 ${
                                                    included
                                                        ? 'bg-emerald-50/40 dark:bg-emerald-500/5'
                                                        : ''
                                                }`}
                                            >
                                                <div className="flex items-center gap-3">
                                                    <div className="flex flex-1 items-center gap-2.5 truncate">
                                                        <input
                                                            id={`${fieldId}-team-${team.id}`}
                                                            type="checkbox"
                                                            checked={included}
                                                            onChange={(e) =>
                                                                toggleTeam(
                                                                    team.id,
                                                                    e.target
                                                                        .checked,
                                                                )
                                                            }
                                                            className="h-3.5 w-3.5 shrink-0 accent-emerald-500"
                                                        />
                                                        <label
                                                            htmlFor={`${fieldId}-team-${team.id}`}
                                                            className="truncate text-[12px] text-gray-700 dark:text-gray-300"
                                                        >
                                                            {team.name}
                                                        </label>
                                                    </div>

                                                    <input
                                                        aria-label={`${team.name} sales target`}
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        inputMode="decimal"
                                                        placeholder="—"
                                                        value={
                                                            data.amounts[
                                                                String(team.id)
                                                            ] ?? ''
                                                        }
                                                        onChange={(e) =>
                                                            setAmount(
                                                                'amounts',
                                                                team.id,
                                                                e.target.value,
                                                            )
                                                        }
                                                        className={cellClass}
                                                    />

                                                    <input
                                                        aria-label={`${team.name} ad budget`}
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        inputMode="decimal"
                                                        placeholder="—"
                                                        value={
                                                            data.budgets[
                                                                String(team.id)
                                                            ] ?? ''
                                                        }
                                                        onChange={(e) =>
                                                            setAmount(
                                                                'budgets',
                                                                team.id,
                                                                e.target.value,
                                                            )
                                                        }
                                                        className={cellClass}
                                                    />
                                                </div>

                                                {message && (
                                                    <p className="mt-1 text-right font-mono text-[11px] text-red-500">
                                                        {message}
                                                    </p>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            )}

                            {teamsMessage && (
                                <p className="font-mono text-[11px] text-red-500">
                                    {teamsMessage}
                                </p>
                            )}

                            <div className="flex items-baseline justify-between pt-1">
                                <span className="font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                    Total
                                </span>
                                <span className="font-mono text-[12px] text-gray-500 dark:text-gray-400">
                                    <span className="font-semibold text-gray-800 tabular-nums dark:text-gray-100">
                                        {currencyFormatter(totalSales)}
                                    </span>{' '}
                                    sales on{' '}
                                    <span className="font-semibold text-gray-800 tabular-nums dark:text-gray-100">
                                        {currencyFormatter(totalBudget)}
                                    </span>{' '}
                                    budget
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center justify-between gap-2 border-t border-black/6 px-5 py-4 dark:border-white/6">
                        {/* Say why the button is off rather than just disabling it. */}
                        <span className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                            {blocking}
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
                                disabled={processing || blocking !== null}
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
