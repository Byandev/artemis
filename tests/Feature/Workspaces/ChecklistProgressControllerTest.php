<?php

use App\Models\Page;
use App\Models\Shop;
use App\Models\User;
use App\Models\WorkspaceChecklist;
use App\Models\WorkspaceChecklistCompletion;

test('index returns checklist progress for a Page target', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = Page::factory()->forWorkspace($workspace)->create();

    $item = WorkspaceChecklist::create([
        'workspace_id' => $workspace->id, 'created_by' => $owner->id,
        'title' => 'Has POS Token', 'target' => 'Page', 'required' => true,
    ]);

    $response = $this->actingAs($owner)
        ->getJson("/workspaces/{$workspace->slug}/checklist/progress/page/{$page->id}")
        ->assertOk();

    $items = $response->json('items');
    expect($items)->toHaveCount(1);
    expect($items[0]['id'])->toBe($item->id);
    expect($items[0]['is_completed'])->toBeFalse();
});

test('store creates a completion and is idempotent', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $shop = Shop::factory()->forWorkspace($workspace)->create();

    $item = WorkspaceChecklist::create([
        'workspace_id' => $workspace->id, 'created_by' => $owner->id,
        'title' => 'X', 'target' => 'Shop', 'required' => false,
    ]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/checklist/progress/shop/{$shop->id}", [
            'checklist_id' => $item->id,
        ])->assertOk();

    // Second call should not create a duplicate
    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/checklist/progress/shop/{$shop->id}", [
            'checklist_id' => $item->id,
        ])->assertOk();

    expect(WorkspaceChecklistCompletion::count())->toBe(1);
});

test('store rejects checklist that targets a different model type', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = Page::factory()->forWorkspace($workspace)->create();

    $shopItem = WorkspaceChecklist::create([
        'workspace_id' => $workspace->id, 'created_by' => $owner->id,
        'title' => 'X', 'target' => 'Shop', 'required' => false,
    ]);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/checklist/progress/page/{$page->id}", [
            'checklist_id' => $shopItem->id,
        ])
        ->assertForbidden();
});

test('destroy removes the completion', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = Page::factory()->forWorkspace($workspace)->create();
    $item = WorkspaceChecklist::create([
        'workspace_id' => $workspace->id, 'created_by' => $owner->id,
        'title' => 'X', 'target' => 'Page', 'required' => false,
    ]);
    WorkspaceChecklistCompletion::create([
        'workspace_id' => $workspace->id,
        'workspace_checklist_id' => $item->id,
        'target_type' => Page::class,
        'target_id' => $page->id,
        'checked_by' => $owner->id,
        'checked_at' => now(),
    ]);

    $this->actingAs($owner)
        ->deleteJson("/workspaces/{$workspace->slug}/checklist/progress/page/{$page->id}", [
            'checklist_id' => $item->id,
        ])
        ->assertNoContent();

    expect(WorkspaceChecklistCompletion::count())->toBe(0);
});

test('non-member cannot access progress endpoints', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = Page::factory()->forWorkspace($workspace)->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->getJson("/workspaces/{$workspace->slug}/checklist/progress/page/{$page->id}")
        ->assertForbidden();
});

test('progress endpoint 404s on unknown target type', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = Page::factory()->forWorkspace($workspace)->create();

    $this->actingAs($owner)
        ->getJson("/workspaces/{$workspace->slug}/checklist/progress/order/{$page->id}")
        ->assertNotFound();
});

test('progress endpoint 403s when target belongs to another workspace', function () {
    ['user' => $owner, 'workspace' => $workspaceA] = makeWorkspaceWithOwner();
    ['workspace' => $workspaceB] = makeWorkspaceWithOwner();
    $foreignPage = Page::factory()->forWorkspace($workspaceB)->create();

    // Owner is a member of workspaceA, not B. They look up a page that is in B.
    $this->actingAs($owner)
        ->getJson("/workspaces/{$workspaceA->slug}/checklist/progress/page/{$foreignPage->id}")
        ->assertForbidden();
});

test('member without View Checklist permission cannot view checklist progress', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = makeWorkspaceMember($workspace);
    $page = Page::factory()->forWorkspace($workspace)->create();

    $this->actingAs($member)
        ->getJson("/workspaces/{$workspace->slug}/checklist/progress/page/{$page->id}")
        ->assertForbidden();
});

test('member without Edit Checklist permission cannot store checklist progress', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = makeWorkspaceMember($workspace);
    $shop = Shop::factory()->forWorkspace($workspace)->create();

    $item = WorkspaceChecklist::create([
        'workspace_id' => $workspace->id,
        'created_by' => $owner->id,
        'title' => 'X',
        'target' => 'Shop',
        'required' => false,
    ]);

    $this->actingAs($member)
        ->postJson("/workspaces/{$workspace->slug}/checklist/progress/shop/{$shop->id}", [
            'checklist_id' => $item->id,
        ])
        ->assertForbidden();
});

test('member without Edit Checklist permission cannot delete checklist progress', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = makeWorkspaceMember($workspace);
    $page = Page::factory()->forWorkspace($workspace)->create();
    $item = WorkspaceChecklist::create([
        'workspace_id' => $workspace->id,
        'created_by' => $owner->id,
        'title' => 'X',
        'target' => 'Page',
        'required' => false,
    ]);

    WorkspaceChecklistCompletion::create([
        'workspace_id' => $workspace->id,
        'workspace_checklist_id' => $item->id,
        'target_type' => Page::class,
        'target_id' => $page->id,
        'checked_by' => $owner->id,
        'checked_at' => now(),
    ]);

    $this->actingAs($member)
        ->deleteJson("/workspaces/{$workspace->slug}/checklist/progress/page/{$page->id}", [
            'checklist_id' => $item->id,
        ])
        ->assertForbidden();
});
