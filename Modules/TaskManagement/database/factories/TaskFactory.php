<?php

namespace Modules\TaskManagement\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\TaskManagement\Enums\TaskPriority;
use Modules\TaskManagement\Models\Task;
use Modules\TaskManagement\Models\TaskList;
use Modules\TaskManagement\Models\TaskStatus;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_list_id' => TaskList::factory(),
            'task_status_id' => fn (array $attributes) => TaskStatus::factory()->state([
                'space_id' => TaskList::query()->findOrFail((int) $attributes['task_list_id'])->space_id,
            ]),
            'parent_id' => null,
            'created_by' => User::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'priority' => TaskPriority::Normal,
            'position' => 0,
            'estimate_minutes' => null,
            'start_at' => null,
            'due_at' => null,
            'completed_at' => null,
            'metadata' => null,
            'archived_at' => null,
        ];
    }

    /**
     * Place the task inside the given list, keeping its status within the same space.
     */
    public function inList(TaskList $list): static
    {
        return $this->state(fn (array $attributes) => [
            'task_list_id' => $list->id,
            'task_status_id' => TaskStatus::factory()->state(['space_id' => $list->space_id]),
        ]);
    }

    /**
     * Indicate the task is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}
