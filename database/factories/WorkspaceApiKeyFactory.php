<?php

namespace Database\Factories;

use App\Models\Workspace;
use App\Models\WorkspaceApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkspaceApiKey>
 */
class WorkspaceApiKeyFactory extends Factory
{
    protected $model = WorkspaceApiKey::class;

    public function definition(): array
    {
        $generated = WorkspaceApiKey::generate();

        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->words(2, true),
            'key' => $generated['key'],
            'key_encrypted' => $generated['key_encrypted'],
            'key_prefix' => $generated['prefix'],
            'last_used_at' => null,
        ];
    }

    /**
     * Convenience: build the model and stash the raw token on it.
     * Use ->raw_key in tests to retrieve the token to send in headers.
     */
    public function withRawKey(): static
    {
        return $this->afterMaking(function (WorkspaceApiKey $key) {
            // no-op; raw key is exposed via state() for tests that need it
        });
    }
}
