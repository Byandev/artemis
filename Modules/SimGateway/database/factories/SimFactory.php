<?php

namespace Modules\SimGateway\Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\SimGateway\Enums\SimCarrier;
use Modules\SimGateway\Enums\SimStatus;
use Modules\SimGateway\Models\Sim;

/**
 * @extends Factory<Sim>
 */
class SimFactory extends Factory
{
    protected $model = Sim::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'phone_number' => '09'.$this->faker->numerify('#########'),
            'carrier' => $this->faker->randomElement(SimCarrier::cases())->value,
            'port_number' => $this->faker->unique()->numberBetween(1, 512),
            'label' => $this->faker->optional()->word(),
            'status' => SimStatus::Active->value,
            'activated_at' => now(),
            'notes' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => SimStatus::Active->value]);
    }
}
