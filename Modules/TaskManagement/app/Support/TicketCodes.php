<?php

namespace Modules\TaskManagement\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The ticket-code namespace of one workspace: the prefix in ART-12.
 *
 * Spaces and folders both carry a code -- a task takes its folder's, or its
 * space's when the list sits directly under the space -- so the two share one
 * namespace per workspace. A code is also taken once any task was numbered
 * under it, even if whatever owned it has since been renamed, so ART-1 can
 * never be issued twice.
 */
class TicketCodes
{
    /**
     * The code reserved for tasks whose space has no code at all, which no
     * space or folder may take.
     */
    public const FALLBACK = 'TSK';

    public const PATTERN = '/^[A-Z][A-Z0-9]*$/';

    public const MAX_LENGTH = 10;

    /**
     * Derive the default code for a name: upper case, letters only, three
     * characters, right-padded when the name is too short or carries too few
     * letters. "Artemis" gives ART, "R&D" gives RDX, "42" gives XXX.
     */
    public static function deriveFrom(string $name): string
    {
        $letters = preg_replace('/[^A-Z]/', '', mb_strtoupper($name)) ?? '';

        return str_pad(mb_substr($letters, 0, 3), 3, 'X');
    }

    /**
     * Whether a code is already used in the workspace.
     *
     * $owner is the space or folder the code is being checked for, so its own
     * current code -- and the tickets numbered under it -- do not count.
     */
    public static function isTaken(int $workspaceId, string $code, ?Model $owner = null): bool
    {
        if ($code === self::FALLBACK) {
            return true;
        }

        $ownCode = $owner?->getOriginal('code');

        $byOwner = fn (string $table) => DB::table($table)
            ->where('workspace_id', $workspaceId)
            ->where('code', $code)
            ->when($owner !== null && $owner->exists && $owner->getTable() === $table,
                fn ($query) => $query->where('id', '!=', $owner->getKey()))
            ->exists();

        $byTickets = $code !== $ownCode && DB::table('tasks')
            ->where('workspace_id', $workspaceId)
            ->where('ticket_code', $code)
            ->exists();

        return $byOwner('task_spaces') || $byOwner('task_folders') || $byTickets;
    }

    /**
     * The derived code for a name, or the first free variant of it -- ART,
     * then ART2, ART3 -- so creating something never fails just because
     * another space or folder already took the obvious code.
     */
    public static function firstFreeFor(int $workspaceId, string $name, ?Model $owner = null): string
    {
        $base = self::deriveFrom($name);

        for ($suffix = 1; ; $suffix++) {
            $candidate = $suffix === 1
                ? $base
                : mb_substr($base, 0, self::MAX_LENGTH - strlen((string) $suffix)).$suffix;

            if (! self::isTaken($workspaceId, $candidate, $owner)) {
                return $candidate;
            }
        }
    }

    /**
     * The validation rule for a code someone typed.
     */
    public static function availableRule(int $workspaceId, ?Model $owner = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($workspaceId, $owner): void {
            if ($value === self::FALLBACK) {
                $fail(__('That code is reserved. Choose another one.'));

                return;
            }

            if (self::isTaken($workspaceId, (string) $value, $owner)) {
                $fail(__('That code is already in use, or has already numbered tickets. Choose another one.'));
            }
        };
    }
}
