<?php

namespace App\Support;

use App\Models\CallLog;
use App\Models\User as SystemUser;
use Illuminate\Support\Collection;
use Modules\Pancake\Models\User;

/**
 * Works out who placed a call.
 *
 * A log carries whichever id the app that synced it knew about: the older mobile
 * build sends a Pancake user id in `user_id`, the newer one sends the system user
 * id in `assignee_user_id`. Both have to be resolved, because the register isn't
 * filtered by CSR — everyone who called that day is in it, and a row has no other
 * way to say who placed it.
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
}
