<?php

namespace Modules\TaskManagement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\TaskManagement\Models\Folder;
use Modules\TaskManagement\Models\Space;
use Modules\TaskManagement\Models\TaskList;

/**
 * @extends Factory<TaskList>
 */
class TaskListFactory extends Factory
{
    protected $model = TaskList::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'folder_id' => null,
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'position' => 0,
            'metadata' => null,
            'archived_at' => null,
        ];
    }

    /**
     * Place the list inside the given folder, and inside that folder's space.
     */
    public function inFolder(Folder $folder): static
    {
        return $this->state(fn (array $attributes) => [
            'space_id' => $folder->space_id,
            'folder_id' => $folder->id,
        ]);
    }

    /**
     * Indicate that the list is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}
