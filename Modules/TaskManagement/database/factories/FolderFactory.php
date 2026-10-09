<?php

namespace Modules\TaskManagement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\TaskManagement\Models\Folder;
use Modules\TaskManagement\Models\Space;

/**
 * @extends Factory<Folder>
 */
class FolderFactory extends Factory
{
    protected $model = Folder::class;

    /**
     * Codes are globally unique, so the factory hands out its own sequence
     * rather than deriving one from the fake name, which would collide as soon
     * as two names shared their first three letters.
     */
    private static int $codeSequence = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'name' => fake()->words(2, true),
            'code' => 'F'.str_pad((string) ++self::$codeSequence, 4, '0', STR_PAD_LEFT),
            'description' => fake()->sentence(),
            'position' => 0,
            'metadata' => null,
            'archived_at' => null,
        ];
    }

    /**
     * Indicate that the folder is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}
