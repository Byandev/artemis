<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        return [
            'category' => fake()->randomElement(['workspace', 'orders', 'pages', 'shops', 'csr']),
            'name' => fake()->unique()->slug(2),
        ];
    }
}
