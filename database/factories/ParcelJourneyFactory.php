<?php

namespace Database\Factories;

use App\Models\ParcelJourney;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ParcelJourney>
 */
class ParcelJourneyFactory extends Factory
{
    protected $model = ParcelJourney::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => null, // Will be set by the seeder or through relationships
            'status' => 'On Delivery',
            'rider_name' => fake()->name(),
            'rider_mobile' => fake()->phoneNumber(),
            'note' => fake()->sentence(),
            'created_at' => today(),
            'updated_at' => now(),
        ];
    }

    /**
     * Mark the parcel as on delivery for today.
     */
    public function onDeliveryToday(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'On Delivery',
            'created_at' => today(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Mark the parcel as delivered.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Delivered',
            'created_at' => today(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Mark the parcel with a specific rider.
     */
    public function withRider(string $riderName, ?string $riderMobile = null): static
    {
        return $this->state(fn (array $attributes) => [
            'rider_name' => $riderName,
            'rider_mobile' => $riderMobile ?? fake()->phoneNumber(),
        ]);
    }
}
