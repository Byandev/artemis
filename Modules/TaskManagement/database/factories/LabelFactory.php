<?php

namespace Modules\TaskManagement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\TaskManagement\Models\Label;
use Modules\TaskManagement\Models\Space;

/**
 * @extends Factory<Label>
 */
class LabelFactory extends Factory
{
    protected $model = Label::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'name' => fake()->unique()->word(),
            'color' => fake()->hexColor(),
        ];
    }
}
