<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
