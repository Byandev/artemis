<?php

namespace Modules\EscTracker\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\EscTracker\Models\DailyEscRecord;

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
            // Mirror how the API derives the flags when the client sends notes
            // rather than explicit booleans, so generated rows stay coherent.
            'learning_completed' => fn (array $attributes) => filled($attributes['learning_text']),
            'movement_completed' => fn (array $attributes) => filled($attributes['movement_text'])
                || filled($attributes['movement_image_url']),
            'meditation_completed' => $this->faker->boolean(),
            'meditation_url' => null,
            'submitted_at' => now(),
        ];
    }
}
