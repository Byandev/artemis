<?php

namespace Modules\Products\Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Products\Models\Product;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'workspace_id' => Workspace::factory(),
            'owner_id' => User::factory(),
            'title' => $name,
            'name' => $name,
            'code' => strtoupper(fake()->unique()->bothify('???-####')),
            'category' => fake()->randomElement(['Health', 'Beauty', 'Apparel']),
            'status' => 'Scaling',
            'description' => fake()->sentence(),
        ];
    }
}
