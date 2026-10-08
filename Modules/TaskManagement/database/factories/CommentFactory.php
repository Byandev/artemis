<?php

namespace Modules\TaskManagement\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\TaskManagement\Models\Comment;
use Modules\TaskManagement\Models\Task;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
            'edited_at' => null,
        ];
    }

    /**
     * Indicate that the comment has been edited since it was posted.
     */
    public function edited(): static
    {
        return $this->state(fn (array $attributes) => [
            'edited_at' => now(),
        ]);
    }
}
