<?php

namespace Database\Factories;

use App\Models\CsrDailyRecord;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CsrDailyRecord>
 */
class CsrDailyRecordFactory extends Factory
{
    protected $model = CsrDailyRecord::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'csr_id' => User::factory(),
            'date' => now()->toDateString(),
            'type' => 'erp',
            'total_orders' => fake()->numberBetween(0, 100),
            'total_sales' => fake()->randomFloat(2, 0, 10000),
            'returning' => fake()->numberBetween(0, 20),
            'delivered' => fake()->numberBetween(0, 100),
            'rts_rate' => fake()->randomFloat(2, 0, 100),
            'rmo_called' => fake()->numberBetween(0, 50),
        ];
    }
}
