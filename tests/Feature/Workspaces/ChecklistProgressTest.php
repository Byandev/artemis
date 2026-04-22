<?php

use App\Models\Shop;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can complete a checklist item for a shop', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($user)->create();

    $shop = Shop::factory()->forWorkspace($workspace)->create();
    $checklist = WorkspaceChecklist::query()->create([
        'workspace_id' => $workspace->id,
        'created_by' => $user->id,
        'title' => 'Connect Pixel',
        'target' => 'Shop',
        'required' => true,
    ]);

    $response = $this->actingAs($user)->postJson(
        "/workspaces/{$workspace->slug}/checklist/progress/shop/{$shop->id}",
        ['checklist_id' => $checklist->id]
    );

    $response->assertOk();

    $this->assertDatabaseHas('workspace_checklist_completions', [
        'workspace_id' => $workspace->id,
        'workspace_checklist_id' => $checklist->id,
        'target_type' => Shop::class,
        'target_id' => $shop->id,
        'checked_by' => $user->id,
    ]);
});

test('user can uncheck a checklist item for a shop', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($user)->create();

    $shop = Shop::factory()->forWorkspace($workspace)->create();
    $checklist = WorkspaceChecklist::query()->create([
        'workspace_id' => $workspace->id,
        'created_by' => $user->id,
        'title' => 'Connect Pixel',
        'target' => 'Shop',
        'required' => true,
    ]);

    $this->actingAs($user)->postJson(
        "/workspaces/{$workspace->slug}/checklist/progress/shop/{$shop->id}",
        ['checklist_id' => $checklist->id]
    )->assertOk();

    $this->actingAs($user)->deleteJson(
        "/workspaces/{$workspace->slug}/checklist/progress/shop/{$shop->id}",
        ['checklist_id' => $checklist->id]
    )->assertNoContent();

    $this->actingAs($user)->deleteJson(
        "/workspaces/{$workspace->slug}/checklist/progress/shop/{$shop->id}",
        ['checklist_id' => $checklist->id]
    )->assertNoContent();

    $this->assertDatabaseMissing('workspace_checklist_completions', [
        'workspace_id' => $workspace->id,
        'workspace_checklist_id' => $checklist->id,
        'target_type' => Shop::class,
        'target_id' => $shop->id,
    ]);
});

test('target from another workspace returns forbidden', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $workspace = Workspace::factory()->forOwner($user)->create();
    $otherWorkspace = Workspace::factory()->create();

    $otherWorkspaceShop = Shop::factory()->forWorkspace($otherWorkspace)->create();

    $checklist = WorkspaceChecklist::query()->create([
        'workspace_id' => $workspace->id,
        'created_by' => $user->id,
        'title' => 'Connect Pixel',
        'target' => 'Shop',
        'required' => true,
    ]);

    $this->actingAs($user)
        ->postJson(
            "/workspaces/{$workspace->slug}/checklist/progress/shop/{$otherWorkspaceShop->id}",
            ['checklist_id' => $checklist->id]
        )
        ->assertForbidden();
});
