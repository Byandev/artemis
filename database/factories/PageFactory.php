<?php

namespace Database\Factories;

use App\Models\Page;
use App\Models\Shop;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'shop_id' => Shop::factory(),
            'owner_id' => User::factory(),
            'name' => fake()->company() . ' Page',
            'facebook_url' => fake()->optional()->url(),
            'pos_token' => fake()->optional()->uuid(),
            'botcake_token' => fake()->optional()->uuid(),
            'infotxt_token' => fake()->optional()->uuid(),
            'infotxt_user_id' => fake()->optional()->numerify('######'),
            'orders_last_synced_at' => fake()->optional()->dateTimeBetween('-1 month', 'now'),
            'archived_at' => null,
        ];
    }

    /**
     * Set a specific workspace for the page.
     */
    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (array $attributes) => [
            'workspace_id' => $workspace->id,
        ]);
    }

    /**
     * Set a specific shop for the page.
     */
    public function forShop(Shop $shop): static
    {
        return $this->state(fn (array $attributes) => [
            'shop_id' => $shop->id,
            'workspace_id' => $shop->workspace_id,
        ]);
    }

    /**
     * Set a specific owner for the page.
     */
    public function forOwner(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'owner_id' => $user->id,
        ]);
    }

    /**
     * Mark the page as archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }

    /**
     * Mark the page as recently synced.
     */
    public function recentlySynced(): static
    {
        return $this->state(fn (array $attributes) => [
            'orders_last_synced_at' => now(),
        ]);
    }

    /**
     * Mark the page as never synced.
     */
    public function neverSynced(): static
    {
        return $this->state(fn (array $attributes) => [
            'orders_last_synced_at' => null,
        ]);
    }
}
