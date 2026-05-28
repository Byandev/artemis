<?php

namespace App\Console\Commands;

use Modules\TaskManagement\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateRecurringTasks extends Command
{
    protected $signature = 'tasks:generate-recurring {--date= : Generate occurrences due on or before this date}';

    protected $description = 'Generate scheduled recurring task occurrences.';

    public function handle(): int
    {
        $targetDate = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'))->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        $createdCount = 0;

        do {
            $createdInPass = 0;

            Task::query()
                ->where('recurrence', '!=', 'none')
                ->whereNotNull('due_date')
                ->with(['assignees:id'])
                ->orderBy('due_date')
                ->chunkById(100, function ($tasks) use ($targetDate, &$createdInPass) {
                    foreach ($tasks as $task) {
                        if ($this->createNextOccurrenceIfDue($task, $targetDate)) {
                            $createdInPass++;
                        }
                    }
                });

            $createdCount += $createdInPass;
        } while ($createdInPass > 0);

        $this->info("Generated {$createdCount} recurring task occurrence(s).");

        return self::SUCCESS;
    }

    private function createNextOccurrenceIfDue(Task $task, CarbonImmutable $targetDate): bool
    {
        return DB::transaction(function () use ($task, $targetDate) {
            $lockedTask = Task::query()
                ->whereKey($task->id)
                ->with(['assignees:id'])
                ->lockForUpdate()
                ->first();

            if (
                $lockedTask === null ||
                $lockedTask->recurrence === 'none' ||
                $lockedTask->due_date === null ||
                $lockedTask->recurringChildren()->exists()
            ) {
                return false;
            }

            $nextDueDate = match ($lockedTask->recurrence) {
                'daily' => $lockedTask->due_date->copy()->addDay(),
                'weekly' => $lockedTask->due_date->copy()->addWeek(),
                'monthly' => $lockedTask->due_date->copy()->addMonthNoOverflow(),
                default => null,
            };

            if ($nextDueDate === null || $nextDueDate->greaterThan($targetDate)) {
                return false;
            }

            if (
                $lockedTask->recurring_until !== null &&
                $nextDueDate->toDateString() > $lockedTask->recurring_until->toDateString()
            ) {
                return false;
            }

            $nextTask = $lockedTask->workspace->tasks()->create([
                'created_by' => $lockedTask->created_by,
                'name' => $lockedTask->name,
                'description' => $lockedTask->description,
                'due_date' => $nextDueDate,
                'recurrence' => $lockedTask->recurrence,
                'recurring_until' => $lockedTask->recurring_until,
                'recurring_from_task_id' => $lockedTask->id,
            ]);

            if ($lockedTask->assignees->isNotEmpty()) {
                $nextTask->assignees()->attach(
                    $lockedTask->assignees
                        ->mapWithKeys(fn ($assignee) => [
                            $assignee->id => ['status' => 'todo'],
                        ])
                        ->all()
                );
            }

            return true;
        });
    }
}
