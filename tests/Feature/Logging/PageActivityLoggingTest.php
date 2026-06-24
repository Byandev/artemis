<?php

use App\Models\ActivityLog;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('editing a page writes a data activity log', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = Page::factory()->forWorkspace($workspace)->forOwner($owner)->create();

    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/pages/{$page->id}", [
            'shop_id' => $page->shop_id,
            'name' => 'Renamed Page',
            'parcel_journey_enabled' => false,
            'owner_id' => $owner->id,
            'status' => 'active',
        ])
        ->assertRedirect();

    $log = ActivityLog::query()->where('action_type', 'page.updated')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($owner->id)
        ->and($log->workspace_id)->toBe($workspace->id)
        ->and($log->category->value)->toBe('data')
        ->and($log->metadata['changed'])->toContain('name')
        ->and($log->metadata['id'])->toBe($page->id);
});
