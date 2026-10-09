<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceChecklist;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The checklist page's table loads from GET /api/workspaces/{workspace}/checklist.
 * The Inertia page only renders the shell and the URL query.
 */
function checklistApiUrl(Workspace $workspace, array $query = []): string
{
    $url = "/api/workspaces/{$workspace->slug}/checklist";

    return $query ? $url.'?'.http_build_query($query) : $url;
}

function checklistItem(Workspace $workspace, string $title, array $attributes = []): WorkspaceChecklist
{
    return WorkspaceChecklist::query()->create([
        'workspace_id' => $workspace->id,
        'created_by' => $workspace->owner_id,
        'title' => $title,
        'target' => 'Page',
        'required' => false,
        ...$attributes,
    ]);
}

function checklistApiTitles($response): array
{
    return collect($response->json('data'))->pluck('title')->all();
}

// ── Auth ────────────────────────────────────────────────────────────────

test('guests get 401', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->getJson(checklistApiUrl($workspace))->assertUnauthorized();
});

test('a user outside the workspace gets 403', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    checklistItem($workspace, 'Secret');

    $this->actingAs(User::factory()->create())
        ->getJson(checklistApiUrl($workspace))
        ->assertForbidden();
});

test('a member without View Checklist gets 403', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs(makeWorkspaceMember($workspace))
        ->getJson(checklistApiUrl($workspace))
        ->assertForbidden();
});

test('a member with View Checklist can list items', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    checklistItem($workspace, 'Connect Pancake');
    $viewer = makeMemberWithPermissions(
        $workspace,
        [PermissionEnum::ViewChecklist->value],
        PermissionEnum::ViewChecklist->category(),
    );

    $titles = checklistApiTitles($this->actingAs($viewer)
        ->getJson(checklistApiUrl($workspace))
        ->assertOk());

    expect($titles)->toBe(['Connect Pancake']);
});

test('an unknown workspace gets 404', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/api/workspaces/no-such-workspace/checklist')
        ->assertNotFound();
});

// ── Shape / scoping ─────────────────────────────────────────────────────

test('it answers a paginated list', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    checklistItem($workspace, 'Connect Pancake', ['target' => 'Shop', 'required' => true]);

    $response = $this->actingAs($owner)
        ->getJson(checklistApiUrl($workspace))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'title', 'target', 'required', 'created_at']],
            'current_page', 'last_page', 'per_page', 'total', 'from', 'to', 'links',
        ]);

    expect($response->json('data.0.title'))->toBe('Connect Pancake')
        ->and($response->json('data.0.target'))->toBe('Shop')
        ->and((bool) $response->json('data.0.required'))->toBeTrue()
        ->and($response->json('total'))->toBe(1)
        ->and($response->json('per_page'))->toBe(10);
});

test('it only lists items of the requested workspace', function () {
    ['user' => $owner, 'workspace' => $a] = makeWorkspaceWithOwner();
    ['workspace' => $b] = makeWorkspaceWithOwner();
    checklistItem($a, 'Mine');
    checklistItem($b, 'Theirs');

    $titles = checklistApiTitles($this->actingAs($owner)->getJson(checklistApiUrl($a))->assertOk());

    expect($titles)->toBe(['Mine']);
});

// ── Sort / paginate ─────────────────────────────────────────────────────

test('sort=title is natural (Step 2 before Step 10)', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    foreach (['Step 10', 'Step 2', 'Step 1'] as $title) {
        checklistItem($w, $title);
    }

    expect(checklistApiTitles($this->actingAs($owner)
        ->getJson(checklistApiUrl($w, ['sort' => 'title']))->assertOk()))
        ->toBe(['Step 1', 'Step 2', 'Step 10'])
        ->and(checklistApiTitles($this->actingAs($owner)
            ->getJson(checklistApiUrl($w, ['sort' => '-title']))->assertOk()))
        ->toBe(['Step 10', 'Step 2', 'Step 1']);
});

test('sort=target groups items by target', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    checklistItem($w, 'A shop task', ['target' => 'Shop']);
    checklistItem($w, 'A page task', ['target' => 'Page']);

    $targets = collect($this->actingAs($owner)
        ->getJson(checklistApiUrl($w, ['sort' => 'target']))
        ->assertOk()
        ->json('data'))->pluck('target')->all();

    expect($targets)->toBe(['Page', 'Shop']);
});

test('sort=-required puts required items first', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    checklistItem($w, 'Optional', ['required' => false]);
    checklistItem($w, 'Must do', ['required' => true]);

    $titles = checklistApiTitles($this->actingAs($owner)
        ->getJson(checklistApiUrl($w, ['sort' => '-required']))
        ->assertOk());

    expect($titles)->toBe(['Must do', 'Optional']);
});

test('an unknown sort is rejected with 400', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->getJson(checklistApiUrl($w, ['sort' => 'workspace_id']))
        ->assertStatus(400);
});

test('per_page and page paginate the list', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    foreach (['Task 1', 'Task 2', 'Task 3'] as $title) {
        checklistItem($w, $title);
    }

    $response = $this->actingAs($owner)
        ->getJson(checklistApiUrl($w, ['sort' => 'title', 'per_page' => 2, 'page' => 2]))
        ->assertOk();

    expect(checklistApiTitles($response))->toBe(['Task 3'])
        ->and($response->json('last_page'))->toBe(2)
        ->and($response->json('total'))->toBe(3);
});

test('per_page is capped at 100', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->getJson(checklistApiUrl($w, ['per_page' => 5000]))
        ->assertOk()
        ->assertJsonPath('per_page', 100);
});

// ── Inertia shell ───────────────────────────────────────────────────────

test('the checklist page ships the query but not the table', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    checklistItem($w, 'Connect Pancake');

    $this->actingAs($owner)
        ->get("/workspaces/{$w->slug}/checklist?sort=title&page=2&per_page=25")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspaces/checklist/index')
            ->where('query.sort', 'title')
            ->where('query.page', '2')
            ->where('query.per_page', '25')
            ->missing('checklists'));
});
