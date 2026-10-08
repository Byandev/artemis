<?php

namespace Modules\TaskManagement\Models\Concerns;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\TaskManagement\Models\Folder;
use Modules\TaskManagement\Models\Space;
use Modules\TaskManagement\Models\Task;
use Modules\TaskManagement\Models\TaskList;
use Modules\TaskManagement\Support\TicketCodes;

/**
 * Gives a task the short identifier people refer to it by -- ART-1, MAT-14.
 *
 * The code is the folder's when the task's list sits in a folder, and the
 * space's when the list sits directly under the space. Whichever owns the code
 * also keeps the counter its numbers come from.
 *
 * The pair is written once, as the task is created, and never recomputed.
 * Deriving it through the folder at read time would break three criteria at
 * once: the identifier has to survive the task moving to another list, moving
 * to another project, and the project's code being edited afterwards.
 *
 * Identifiers are unique per Artemis workspace, so every lookup below is
 * scoped to the task's `workspace_id`.
 */
trait HasTicketIdentifier
{
    /**
     * How many times to re-derive the number when a simultaneous create takes
     * it first. Each retry reads the number the winner has just committed, so
     * one is normally enough; three covers a burst.
     */
    private const TICKET_NUMBER_ATTEMPTS = 3;

    /**
     * Assign the workspace and identifier as the task is created, so every path
     * gets one -- the API, a factory, a seeder, a console command.
     */
    public static function bootHasTicketIdentifier(): void
    {
        static::creating(function (Task $task): void {
            $task->workspace_id ??= Space::query()
                ->whereKey(TaskList::query()->whereKey($task->task_list_id)->select('space_id'))
                ->value('workspace_id');

            if ($task->ticket_number !== null) {
                return;
            }

            [$task->ticket_code, $task->ticket_number] = self::nextIdentifierForList($task->workspace_id, $task->task_list_id);
        });
    }

    /**
     * Save the task, re-deriving the number if a simultaneous create committed
     * the same one first.
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        for ($attempt = 1; ; $attempt++) {
            $isInsert = ! $this->exists;

            try {
                return parent::save($options);
            } catch (UniqueConstraintViolationException $exception) {
                if (! $isInsert || $attempt >= self::TICKET_NUMBER_ATTEMPTS || ! $this->hasTicketClash()) {
                    throw $exception;
                }

                $this->ticket_number = self::nextNumberFor($this->workspace_id, (string) $this->ticket_code, self::codeOwnerForList($this->task_list_id));
            }
        }
    }

    /**
     * The identifier as it is read and pasted: CODE-N.
     */
    public function ticketIdentifier(): ?string
    {
        if ($this->ticket_code === null || $this->ticket_number === null) {
            return null;
        }

        return $this->ticket_code.'-'.$this->ticket_number;
    }

    /**
     * Whether this task's identifier is the one already taken. Checked so a
     * unique violation from any other column is rethrown rather than retried.
     */
    private function hasTicketClash(): bool
    {
        return $this->ticket_code !== null
            && $this->ticket_number !== null
            && Task::query()
                ->where('workspace_id', $this->workspace_id)
                ->where('ticket_code', $this->ticket_code)
                ->where('ticket_number', $this->ticket_number)
                ->exists();
    }

    /**
     * The code and number for a task about to be created in the given list.
     *
     * @return array{string, int}
     */
    private static function nextIdentifierForList(?int $workspaceId, ?int $listId): array
    {
        $owner = self::codeOwnerForList($listId);
        $code = $owner?->code ?? TicketCodes::FALLBACK;

        return [$code, self::nextNumberFor($workspaceId, $code, $owner?->code === null ? null : $owner)];
    }

    /**
     * Whatever the given list takes its code from: its folder, or its space
     * when it sits directly under one.
     */
    private static function codeOwnerForList(?int $listId): Folder|Space|null
    {
        $list = TaskList::query()->whereKey($listId)->first(['folder_id', 'space_id']);

        if ($list === null) {
            return null;
        }

        return $list->folder_id !== null
            ? Folder::query()->whereKey($list->folder_id)->first()
            : Space::query()->whereKey($list->space_id)->first();
    }

    /**
     * The next number for a code, counting from 1.
     *
     * Two highs are compared, and the reason is the pair of criteria that pull
     * against each other. A project's numbering must keep increasing by one per
     * task, so the project's own counter is followed even after its code has
     * been edited -- ART-1 is followed by ARM-2, not by a second number 1. But
     * CODE-N also has to be unique in the workspace, and a code freed by that
     * same edit can be taken by another project later, so the highest number
     * already issued under the code is honoured too. Whichever is higher wins.
     *
     * The row lock serialises two creates racing under one owner; the unique
     * index on (workspace_id, ticket_code, ticket_number) is the real
     * guarantee, and the retry in save() turns a lost race into a correct
     * number rather than a 500.
     */
    private static function nextNumberFor(?int $workspaceId, string $code, Folder|Space|null $owner): int
    {
        return DB::transaction(function () use ($workspaceId, $code, $owner): int {
            $usedUnderCode = (int) Task::query()
                ->where('workspace_id', $workspaceId)
                ->where('ticket_code', $code)
                ->lockForUpdate()
                ->max('ticket_number');

            if ($owner === null) {
                return $usedUnderCode + 1;
            }

            $issuedByOwner = (int) $owner->newQuery()
                ->whereKey($owner->getKey())
                ->lockForUpdate()
                ->value('last_ticket_number');

            $next = max($usedUnderCode, $issuedByOwner) + 1;

            $owner->newQuery()->whereKey($owner->getKey())->update(['last_ticket_number' => $next]);

            return $next;
        });
    }
}
