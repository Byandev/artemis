<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Workspace>
 */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'description' => fake()->optional()->sentence(),
            'owner_id' => User::factory(),
        ];
    }

    /**
     * Configure the model factory to attach the owner as a member.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Workspace $workspace) {
            // Attach the owner as a workspace member with 'owner' role
            $workspace->users()->attach($workspace->owner_id, ['role' => 'owner']);
        });
    }

    /**
     * Set a specific owner for the workspace.
     */
    public function forOwner(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'owner_id' => $user->id,
        ]);
    }
}
