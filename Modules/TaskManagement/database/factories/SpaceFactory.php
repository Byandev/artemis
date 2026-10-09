<?php

namespace Modules\TaskManagement\Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Models\Space;

/**
 * @extends Factory<Space>
 */
class SpaceFactory extends Factory
{
    protected $model = Space::class;

    /**
     * The workspace new spaces default to when none is given. The test suite
     * pins it so every space a test makes lands in that test's workspace.
     */
    public static ?int $workspaceId = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => self::$workspaceId ?? Workspace::factory(),
            'owner_id' => User::factory(),
            'name' => fake()->unique()->company(),
            'description' => fake()->sentence(),
            'color' => fake()->hexColor(),
            'position' => 0,
            'metadata' => null,
            'archived_at' => null,
        ];
    }

    /**
     * Indicate that the space is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }

    /**
     * Give the space its default set of statuses.
     */
    public function withDefaultStatuses(): static
    {
        return $this->afterCreating(fn (Space $space) => $space->seedDefaultStatuses());
    }

    /**
     * Add the given user to the space with the given role.
     */
    public function withMember(User $user, SpaceRole $role = SpaceRole::Member): static
    {
        return $this->afterCreating(function (Space $space) use ($user, $role): void {
            $space->members()->attach($user, ['role' => $role->value]);
        });
    }
}
