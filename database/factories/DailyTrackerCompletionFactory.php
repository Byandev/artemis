<?php

namespace Database\Factories;

use App\Models\DailyTrackerCompletion;
use App\Models\DailyTrackerItem;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyTrackerCompletion>
 */
class DailyTrackerCompletionFactory extends Factory
{
    protected $model = DailyTrackerCompletion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'daily_tracker_item_id' => DailyTrackerItem::factory(),
            'user_id' => User::factory(),
            'tracked_on' => CarbonImmutable::today()->toDateString(),
            'checked_by' => null,
            'completed_at' => now(),
        ];
    }
}
