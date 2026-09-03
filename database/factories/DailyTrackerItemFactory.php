<?php

namespace Database\Factories;

use App\Enums\DailyTrackerCadence;
use App\Models\DailyTrackerItem;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyTrackerItem>
 */
class DailyTrackerItemFactory extends Factory
{
    protected $model = DailyTrackerItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'category' => $this->faker->randomElement(['Self care', 'Ad spend', 'Trackers', 'Team', 'Orders']),
            'label' => rtrim($this->faker->sentence(), '.').'?',
            'cadence' => DailyTrackerCadence::Daily,
            'tags' => [],
            'position' => 0,
            'active' => true,
        ];
    }

    public function weekly(): static
    {
        return $this->state(fn () => ['cadence' => DailyTrackerCadence::Weekly]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
