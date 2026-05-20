<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->unique()->word().'-'.fake()->unique()->randomNumber(5),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
