<?php

namespace Modules\TaskManagement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\TaskManagement\Enums\TaskStatusType;
use Modules\TaskManagement\Models\Space;
use Modules\TaskManagement\Models\TaskStatus;

/**
 * @extends Factory<TaskStatus>
 */
class TaskStatusFactory extends Factory
{
    protected $model = TaskStatus::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'space_id' => Space::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'color' => fake()->hexColor(),
            'type' => TaskStatusType::NotStarted,
            'position' => 0,
            'is_default' => false,
        ];
    }

    /**
     * Indicate the status type.
     */
    public function ofType(TaskStatusType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
        ]);
    }

    /**
     * Indicate that new tasks fall into this status by default.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
