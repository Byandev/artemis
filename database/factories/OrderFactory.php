<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Page;
use App\Models\Shop;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => fake()->unique()->numerify('ORD-#####'),
            'status' => fake()->numberBetween(1, 10),
            'status_name' => fake()->word(),
            'shop_id' => Shop::factory(),
            'page_id' => Page::factory(),
            'workspace_id' => Workspace::factory(),
            'total_amount' => fake()->randomFloat(2, 10, 1000),
            'final_amount' => fake()->randomFloat(2, 10, 1000),
            'discount' => fake()->randomFloat(2, 0, 100),
            'ad_id' => fake()->optional()->numberBetween(1, 100),
            'fb_id' => fake()->unique()->numerify('####################'),
            'delivery_attempts' => fake()->optional()->numberBetween(1, 3),
            'first_delivery_attempt' => fake()->optional()->dateTimeBetween('-1 week', 'now'),
            'inserted_at' => now(),
            'confirmed_at' => fake()->optional()->dateTimeBetween('-1 week', 'now'),
            'returned_at' => null,
            'returning_at' => null,
            'delivered_at' => null,
            'shipped_at' => fake()->optional()->dateTimeBetween('-1 week', 'now'),
            'tracking_code' => fake()->optional()->bothify('TRK-????-########'),
            'parcel_status' => 'On Delivery',
        ];
    }

    /**
     * Set a specific workspace for the order.
     */
    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (array $attributes) => [
            'workspace_id' => $workspace->id,
        ]);
    }

    /**
     * Set a specific page for the order.
     */
    public function forPage(Page $page): static
    {
        return $this->state(fn (array $attributes) => [
            'page_id' => $page->id,
            'workspace_id' => $page->workspace_id,
            'shop_id' => $page->shop_id,
        ]);
    }

    /**
     * Mark order as on delivery.
     */
    public function onDelivery(): static
    {
        return $this->state(fn (array $attributes) => [
            'parcel_status' => 'On Delivery',
            'status_name' => 'On Delivery',
        ]);
    }

    /**
     * Mark order as delivered.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'parcel_status' => 'Delivered',
            'status_name' => 'Delivered',
            'delivered_at' => now(),
        ]);
    }
}
