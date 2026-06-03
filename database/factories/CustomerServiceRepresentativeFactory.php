<?php

namespace Database\Factories;

use App\Models\CustomerServiceRepresentative;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomerServiceRepresentative>
 */
class CustomerServiceRepresentativeFactory extends Factory
{
    protected $model = CustomerServiceRepresentative::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
        ];
    }
}
