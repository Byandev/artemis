<?php

namespace App\Support;

use App\Models\CallLog;
use App\Models\User as SystemUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Pancake\Models\User;

/**
 * Works out who placed a call.
 *
 * A log carries whichever id the app that synced it knew about: the older mobile
 * build sends a Pancake user id in `user_id`, the newer one sends the system user
 * id in `assignee_user_id`. Both have to be resolved, because the register lists
 * every caller's calls unless narrowed to one, and a row has no other way to say
 * who placed it.
 *
 * Two queries for a whole page of calls, however many rows it holds — which is
 * also why the "User" column doesn't sort: the two ids point into two different
 * tables, so there is no single join that names them all.
 */
class CallLogCallers
{
    /**
     * Stamp `called_by` onto each log.
     *
     * @param  Collection<int, CallLog>  $logs
     * @return Collection<int, CallLog>
     */
    public static function stamp(Collection $logs): Collection
    {
        $systemNames = SystemUser::whereIn('id', $logs->pluck('assignee_user_id')->filter()->unique())
            ->pluck('name', 'id');

        $pancakeNames = User::whereIn('id', $logs->pluck('user_id')->filter()->unique())
            ->pluck('name', 'id');

        return $logs->each(function (CallLog $log) use ($systemNames, $pancakeNames) {
            // Left null rather than guessed at when neither id resolves — an
            // empty cell is honest, a wrong name isn't.
            $log->setAttribute('called_by', $systemNames->get($log->assignee_user_id)
                ?? $pancakeNames->get($log->user_id));
        });
    }

    /**
     * Everyone who placed a call in the given set, as filter options.
     *
     * A Pancake user linked to a system user is folded into that system user, so
     * someone who called from both builds of the app is listed once. A Pancake
     * user with no link stands alone. Values are prefixed with where the id
     * lives — `system:` or `pancake:` — because the two tables' ids can't be
     * told apart otherwise.
     *
     * @param  Builder<CallLog>  $calls
     * @return list<array{value: string, label: string}>
     */
    public static function options(Builder $calls): array
    {
        $systemIds = (clone $calls)->whereNotNull('assignee_user_id')->distinct()->pluck('assignee_user_id');

        // Only the rows the User column names by their Pancake id.
        $pancakeUsers = User::whereIn('id', (clone $calls)->whereNull('assignee_user_id')->distinct()->pluck('user_id'))
            ->get(['id', 'name', 'user_id']);

        $system = SystemUser::whereIn('id', $systemIds->merge($pancakeUsers->pluck('user_id')->filter())->unique())
            ->pluck('name', 'id')
            ->map(fn ($name, $id) => ['value' => "system:{$id}", 'label' => $name]);

        $pancake = $pancakeUsers
            ->filter(fn (User $user) => ! $user->user_id || ! $system->has($user->user_id))
            ->map(fn (User $user) => ['value' => "pancake:{$user->id}", 'label' => $user->name]);

        return $system->values()->concat($pancake->values())
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * Narrow calls to the ones the User column would name as this caller.
     *
     * Mirrors stamp(): the system id wins when the row has one, and the Pancake id
     * only speaks for rows without it.
     *
     * @param  Builder<CallLog>  $query
     */
    public static function filter(Builder $query, string $value): void
    {
        [$source, $id] = array_pad(explode(':', $value, 2), 2, '');

        match ($source) {
            'system' => $query->where(fn ($q) => $q
                ->where('assignee_user_id', $id)
                ->orWhere(fn ($q) => $q
                    ->whereNull('assignee_user_id')
                    ->whereIn('user_id', User::where('user_id', $id)->select('id')))),
            'pancake' => $query->whereNull('assignee_user_id')->where('user_id', $id),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
