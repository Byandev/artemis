<?php

use App\Models\Page;
use App\Models\User;
use App\Models\Workspace;

it('creates an activity when a page is created', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $this->actingAs($user);

    Page::factory()->forWorkspace($workspace)->create([
        'owner_id' => $user->id,
    ]);

    $this->assertDatabaseHas('activity_log', [
        'description' => 'Page created',
        'causer_id' => $user->id,
        'workspace_id' => $workspace->id,
    ]);
});
