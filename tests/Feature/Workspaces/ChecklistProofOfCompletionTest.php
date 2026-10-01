<?php

use App\Models\Page;
use App\Models\Shop;
use App\Models\WorkspaceChecklist;
use App\Models\WorkspaceChecklistCompletion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * @param  'Shop'|'Page'  $target
 */
function checklistItemFor($workspace, $owner, string $target = 'Page'): WorkspaceChecklist
{
    return WorkspaceChecklist::create([
        'workspace_id' => $workspace->id,
        'created_by' => $owner->id,
        'title' => 'Screenshot the page settings',
        'target' => $target,
        'required' => true,
    ]);
}

beforeEach(function () {
    // The media disk is whatever the environment configured; faking it keeps
    // the suite off real S3 and off the developer's filesystem.
    Storage::fake(config('filesystems.checklist_proof_disk'));
});

it('refuses to complete a Page checklist item without proof', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = Page::factory()->forWorkspace($workspace)->create();
    $item = checklistItemFor($workspace, $owner);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/checklist/progress/page/{$page->id}", [
            'checklist_id' => $item->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('proof');

    expect(WorkspaceChecklistCompletion::count())->toBe(0);
});

it('completes a Page checklist item when proof is attached', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = Page::factory()->forWorkspace($workspace)->create();
    $item = checklistItemFor($workspace, $owner);

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/checklist/progress/page/{$page->id}", [
            'checklist_id' => $item->id,
            'proof' => UploadedFile::fake()->image('settings.jpg'),
            'note' => 'Token rotated on 2026-08-31.',
        ])
        ->assertOk();

    $completion = WorkspaceChecklistCompletion::firstOrFail();
    $proof = $completion->proof();

    expect($completion->note)->toBe('Token rotated on 2026-08-31.')
        ->and($completion->checked_by)->toBe($owner->id)
        ->and($proof)->not->toBeNull()
        ->and($proof->file_name)->toBe('settings.jpg')
        ->and($proof->collection_name)->toBe(WorkspaceChecklistCompletion::PROOF_COLLECTION)
        // Stored on the configured media disk, not wherever the default points.
        ->and($proof->disk)->toBe(config('filesystems.checklist_proof_disk'));

    Storage::disk($proof->disk)->assertExists($proof->getPathRelativeToRoot());
});

it('accepts a pdf as proof and rejects anything that is not a document or image', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = Page::factory()->forWorkspace($workspace)->create();
    $item = checklistItemFor($workspace, $owner);

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/checklist/progress/page/{$page->id}", [
            'checklist_id' => $item->id,
            'proof' => UploadedFile::fake()->create('evidence.pdf', 200, 'application/pdf'),
        ])
        ->assertOk();

    expect(WorkspaceChecklistCompletion::firstOrFail()->proof()->file_name)->toBe('evidence.pdf');

    $other = checklistItemFor($workspace, $owner);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/checklist/progress/page/{$page->id}", [
            'checklist_id' => $other->id,
            'proof' => UploadedFile::fake()->create('payload.exe', 10),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('proof');
});

it('rejects proof larger than 10 MB', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = Page::factory()->forWorkspace($workspace)->create();
    $item = checklistItemFor($workspace, $owner);

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/checklist/progress/page/{$page->id}", [
            'checklist_id' => $item->id,
            'proof' => UploadedFile::fake()->image('huge.jpg')->size(10241),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('proof');
});

it('replaces the proof when a second file is uploaded for the same item', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = Page::factory()->forWorkspace($workspace)->create();
    $item = checklistItemFor($workspace, $owner);
    $url = "/workspaces/{$workspace->slug}/checklist/progress/page/{$page->id}";

    $this->actingAs($owner)->post($url, [
        'checklist_id' => $item->id,
        'proof' => UploadedFile::fake()->image('first.jpg'),
    ])->assertOk();

    $this->actingAs($owner)->post($url, [
        'checklist_id' => $item->id,
        'proof' => UploadedFile::fake()->image('second.jpg'),
    ])->assertOk();

    $completion = WorkspaceChecklistCompletion::firstOrFail();

    expect(WorkspaceChecklistCompletion::count())->toBe(1)
        ->and($completion->getMedia(WorkspaceChecklistCompletion::PROOF_COLLECTION))->toHaveCount(1)
        ->and($completion->proof()->file_name)->toBe('second.jpg');
});

it('stays idempotent once proof is on file', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = Page::factory()->forWorkspace($workspace)->create();
    $item = checklistItemFor($workspace, $owner);
    $url = "/workspaces/{$workspace->slug}/checklist/progress/page/{$page->id}";

    $this->actingAs($owner)->post($url, [
        'checklist_id' => $item->id,
        'proof' => UploadedFile::fake()->image('first.jpg'),
    ])->assertOk();

    // A retry with no file must not 422 an item that is already proven.
    $this->actingAs($owner)
        ->postJson($url, ['checklist_id' => $item->id])
        ->assertOk();

    expect(WorkspaceChecklistCompletion::firstOrFail()->proof()->file_name)->toBe('first.jpg');
});

it('does not ask a Shop checklist item for proof', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $shop = Shop::factory()->forWorkspace($workspace)->create();
    $item = checklistItemFor($workspace, $owner, 'Shop');

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/checklist/progress/shop/{$shop->id}", [
            'checklist_id' => $item->id,
        ])
        ->assertOk();

    expect(WorkspaceChecklistCompletion::count())->toBe(1);
});

it('exposes the proof through the progress endpoint', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = Page::factory()->forWorkspace($workspace)->create();
    $item = checklistItemFor($workspace, $owner);

    $this->actingAs($owner)->post("/workspaces/{$workspace->slug}/checklist/progress/page/{$page->id}", [
        'checklist_id' => $item->id,
        'proof' => UploadedFile::fake()->image('settings.jpg'),
        'note' => 'See the highlighted field.',
    ])->assertOk();

    $response = $this->actingAs($owner)
        ->getJson("/workspaces/{$workspace->slug}/checklist/progress/page/{$page->id}")
        ->assertOk();

    $first = $response->json('items.0');

    expect($response->json('proof_required'))->toBeTrue()
        ->and($first['requires_proof'])->toBeTrue()
        ->and($first['is_completed'])->toBeTrue()
        ->and($first['note'])->toBe('See the highlighted field.')
        ->and($first['proof']['file_name'])->toBe('settings.jpg')
        ->and($first['proof']['url'])->toContain('/checklist/progress/proof/');
});

it('reports Shop items as not needing proof', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $shop = Shop::factory()->forWorkspace($workspace)->create();
    checklistItemFor($workspace, $owner, 'Shop');

    $response = $this->actingAs($owner)
        ->getJson("/workspaces/{$workspace->slug}/checklist/progress/shop/{$shop->id}")
        ->assertOk();

    expect($response->json('proof_required'))->toBeFalse()
        ->and($response->json('items.0.requires_proof'))->toBeFalse();
});

it('serves the proof as a signed url', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = Page::factory()->forWorkspace($workspace)->create();
    $item = checklistItemFor($workspace, $owner);

    $this->actingAs($owner)->post("/workspaces/{$workspace->slug}/checklist/progress/page/{$page->id}", [
        'checklist_id' => $item->id,
        'proof' => UploadedFile::fake()->image('settings.jpg'),
    ])->assertOk();

    $completion = WorkspaceChecklistCompletion::firstOrFail();

    // Storage::fake() yields a disk that can sign, so the handler hands out a
    // short-lived URL rather than proxying the bytes.
    $response = $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/checklist/progress/proof/{$completion->id}");

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('expir');
});

it('streams the proof when the disk cannot sign a url', function () {
    $root = storage_path('framework/testing/unsigned-checklist-proof');

    config(['filesystems.disks.unsigned' => [
        'driver' => 'local',
        'root' => $root,
        'serve' => false,
    ]]);
    config(['filesystems.checklist_proof_disk' => 'unsigned']);

    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = Page::factory()->forWorkspace($workspace)->create();
    $item = checklistItemFor($workspace, $owner);

    $this->actingAs($owner)->post("/workspaces/{$workspace->slug}/checklist/progress/page/{$page->id}", [
        'checklist_id' => $item->id,
        'proof' => UploadedFile::fake()->image('settings.jpg'),
    ])->assertOk();

    $completion = WorkspaceChecklistCompletion::firstOrFail();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/checklist/progress/proof/{$completion->id}")
        ->assertSuccessful()
        ->assertDownload('settings.jpg');

    File::deleteDirectory($root);
});

it('404s when the completion has no proof on file', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = Page::factory()->forWorkspace($workspace)->create();
    $item = checklistItemFor($workspace, $owner);

    $completion = WorkspaceChecklistCompletion::create([
        'workspace_id' => $workspace->id,
        'workspace_checklist_id' => $item->id,
        'target_type' => Page::class,
        'target_id' => $page->id,
        'checked_by' => $owner->id,
        'checked_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/checklist/progress/proof/{$completion->id}")
        ->assertNotFound();
});

it('does not serve a proof belonging to another workspace', function () {
    ['user' => $owner, 'workspace' => $workspaceA] = makeWorkspaceWithOwner();
    ['user' => $otherOwner, 'workspace' => $workspaceB] = makeWorkspaceWithOwner();

    $foreignPage = Page::factory()->forWorkspace($workspaceB)->create();
    $foreignItem = checklistItemFor($workspaceB, $otherOwner);

    $this->actingAs($otherOwner)->post("/workspaces/{$workspaceB->slug}/checklist/progress/page/{$foreignPage->id}", [
        'checklist_id' => $foreignItem->id,
        'proof' => UploadedFile::fake()->image('settings.jpg'),
    ])->assertOk();

    $completion = WorkspaceChecklistCompletion::firstOrFail();

    // Owner of A asking, through their own workspace, for B's file.
    $this->actingAs($owner)
        ->get("/workspaces/{$workspaceA->slug}/checklist/progress/proof/{$completion->id}")
        ->assertForbidden();
});

it('deletes the stored proof when the item is unchecked', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = Page::factory()->forWorkspace($workspace)->create();
    $item = checklistItemFor($workspace, $owner);
    $url = "/workspaces/{$workspace->slug}/checklist/progress/page/{$page->id}";

    $this->actingAs($owner)->post($url, [
        'checklist_id' => $item->id,
        'proof' => UploadedFile::fake()->image('settings.jpg'),
    ])->assertOk();

    $proofPath = WorkspaceChecklistCompletion::firstOrFail()->proof()->getPathRelativeToRoot();
    $disk = Storage::disk(config('filesystems.checklist_proof_disk'));
    $disk->assertExists($proofPath);

    $this->actingAs($owner)
        ->deleteJson($url, ['checklist_id' => $item->id])
        ->assertNoContent();

    expect(WorkspaceChecklistCompletion::count())->toBe(0);
    $disk->assertMissing($proofPath);
});
