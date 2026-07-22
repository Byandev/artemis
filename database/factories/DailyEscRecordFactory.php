<?php

namespace Database\Factories;

use App\Models\DailyEscRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyEscRecord>
 */
class DailyEscRecordFactory extends Factory
{
    protected $model = DailyEscRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'record_date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'learning_text' => $this->faker->optional()->sentence(),
            'movement_text' => $this->faker->optional()->sentence(),
            'movement_image_url' => null,
            'meditation_completed' => $this->faker->boolean(),
            'meditation_url' => null,
            'submitted_at' => now(),
        ];
    }
}
