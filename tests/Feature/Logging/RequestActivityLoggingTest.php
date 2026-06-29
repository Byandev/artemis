<?php

use App\Models\ActivityLog;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

test('state-changing web requests are captured by the request middleware', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = Page::factory()->forWorkspace($workspace)->forOwner($owner)->create();

    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/pages/{$page->id}", [
            'shop_id' => $page->shop_id,
            'name' => 'Renamed',
            'parcel_journey_enabled' => false,
            'owner_id' => $owner->id,
            'status' => 'active',
        ])
        ->assertRedirect();

    $log = ActivityLog::query()->where('action_type', 'workspaces.pages.update')->first();

    expect($log)->not->toBeNull()
        ->and($log->log_type->value)->toBe('user')
        ->and($log->user_id)->toBe($owner->id)
        ->and($log->workspace_id)->toBe($workspace->id)
        ->and($log->metadata['method'])->toBe('PUT')
        ->and($log->metadata['route'])->toBe('workspaces.pages.update')
        // Human-readable action message with the meaningful changed fields.
        ->and($log->message)->toContain('Updated Pages')
        ->and($log->message)->toContain('status: active');
});

test('read (GET) requests are not logged', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/pages")
        ->assertOk();

    expect(ActivityLog::query()->whereJsonContains('metadata->method', 'GET')->exists())->toBeFalse();
});

test('exporting data (a GET download) is audited', function () {
    Excel::fake();
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/pages/export")
        ->assertOk();

    $log = ActivityLog::query()->where('action_type', 'workspaces.pages.export')->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($owner->id)
        ->and($log->workspace_id)->toBe($workspace->id)
        ->and($log->metadata['method'])->toBe('GET')
        ->and($log->message)->toContain('Exported Pages');
});
