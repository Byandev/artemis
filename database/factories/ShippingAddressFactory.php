<?php

namespace Database\Factories;

use App\Models\ShippingAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShippingAddress>
 */
class ShippingAddressFactory extends Factory
{
    protected $model = ShippingAddress::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => null, // Will be set by the seeder or through relationships
            'province_name' => fake()->state(),
            'district_name' => fake()->city(),
            'commune_name' => fake()->citySuffix(),
            'address' => fake()->streetAddress(),
            'full_address' => fake()->address(),
            'full_name' => fake()->name(),
            'phone_number' => fake()->phoneNumber(),
        ];
    }
}
