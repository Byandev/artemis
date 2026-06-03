<?php

use App\Models\User;
use App\Models\WorkspaceChecklist;

test('owner can view checklist index', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/checklist")
        ->assertOk();
});

test('non-member cannot view checklist', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get("/workspaces/{$workspace->slug}/checklist")
        ->assertForbidden();
});

test('owner can create a checklist item for Page or Shop targets', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/checklist")
        ->post("/workspaces/{$workspace->slug}/checklist", [
            'title' => 'Connect Pancake',
            'target' => 'Page',
            'required' => true,
        ])
        ->assertRedirect();

    expect(WorkspaceChecklist::where('workspace_id', $workspace->id)
        ->where('title', 'Connect Pancake')->exists())->toBeTrue();
});

test('store rejects invalid target value', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/checklist")
        ->post("/workspaces/{$workspace->slug}/checklist", [
            'title' => 'X',
            'target' => 'NotARealTarget',
            'required' => true,
        ])
        ->assertSessionHasErrors('target');
});

test('owner can update a checklist item', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = WorkspaceChecklist::create([
        'workspace_id' => $workspace->id,
        'created_by' => $owner->id,
        'title' => 'Old',
        'target' => 'Page',
        'required' => false,
    ]);

    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/checklist/{$item->id}", [
            'title' => 'New',
            'target' => 'Shop',
            'required' => true,
        ])
        ->assertRedirect();

    $item->refresh();
    expect($item->title)->toBe('New');
    expect($item->target)->toBe('Shop');
    expect((bool) $item->required)->toBeTrue();
});

test('cannot update a checklist from another workspace', function () {
    ['user' => $owner, 'workspace' => $workspaceA] = makeWorkspaceWithOwner();
    ['workspace' => $workspaceB] = makeWorkspaceWithOwner();
    $foreign = WorkspaceChecklist::create([
        'workspace_id' => $workspaceB->id,
        'created_by' => $owner->id,
        'title' => 'X',
        'target' => 'Page',
        'required' => false,
    ]);

    $this->actingAs($owner)
        ->put("/workspaces/{$workspaceA->slug}/checklist/{$foreign->id}", [
            'title' => 'Hijacked',
            'target' => 'Page',
            'required' => false,
        ])
        ->assertForbidden();
});

test('owner can delete a checklist item', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = WorkspaceChecklist::create([
        'workspace_id' => $workspace->id,
        'created_by' => $owner->id,
        'title' => 'X', 'target' => 'Page', 'required' => false,
    ]);

    $this->actingAs($owner)
        ->delete("/workspaces/{$workspace->slug}/checklist/{$item->id}")
        ->assertRedirect();

    expect(WorkspaceChecklist::find($item->id))->toBeNull();
});
