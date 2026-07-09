<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->unique()->words(2, true),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
