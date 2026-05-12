<?php

namespace Database\Factories;

use App\Models\CallLog;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CallLog>
 */
class CallLogFactory extends Factory
{
    protected $model = CallLog::class;

    public function definition(): array
    {
        $when = fake()->dateTimeBetween('-30 days', 'now');

        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => (string) Str::uuid(),
            'phone_number' => fake()->phoneNumber(),
            'type' => fake()->randomElement(['outgoing', 'incoming', 'missed']),
            'duration' => fake()->numberBetween(0, 600),
            'call_date' => $when->format('Y-m-d'),
            'call_time' => $when->format('H:i:s'),
        ];
    }
}
