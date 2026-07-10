<?php

use Illuminate\Support\Facades\Http;
use Modules\GencysERP\Models\GencysPage;
use Modules\GencysERP\Models\Intern;

function gencysPagesUrl($workspace, array $query = []): string
{
    $url = "/workspaces/{$workspace->slug}/gencys/pages";

    return $query ? $url.'?'.http_build_query($query) : $url;
}

test('the pages index lists workspace-scoped pages for the owner', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();

    GencysPage::factory()->for($workspace)->count(3)->create();
    GencysPage::factory()->for($other)->create(['name' => 'Outsider']);

    $this->actingAs($user)
        ->get(gencysPagesUrl($workspace))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/gencys/pages/index')
            ->has('pages.data', 3)
            ->where('pages.data.0.name', fn ($name) => $name !== 'Outsider')
        );
});

test('search matches across name, owner, intern and brand', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    GencysPage::factory()->for($workspace)->create(['name' => 'Alpha Store']);
    GencysPage::factory()->for($workspace)->create(['owner' => 'Beta Reyes']);
    GencysPage::factory()->for($workspace)->create(['intern_and_brand' => 'Gamma - Acme']);

    $this->actingAs($user)
        ->get(gencysPagesUrl($workspace, ['filter' => ['search' => 'Beta']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pages.data', 1)
            ->where('pages.data.0.owner', 'Beta Reyes')
        );
});

test('the status and platform filters narrow results and expose distinct values', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    GencysPage::factory()->for($workspace)->count(2)->create([
        'status' => 'Active', 'platform' => 'Facebook',
    ]);
    GencysPage::factory()->for($workspace)->create([
        'status' => 'Inactive', 'platform' => 'TikTok',
    ]);

    $this->actingAs($user)
        ->get(gencysPagesUrl($workspace, ['filter' => ['status' => 'Active']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pages.data', 2)
            ->has('statuses', 2)
            ->has('platforms', 2)
        );

    $this->actingAs($user)
        ->get(gencysPagesUrl($workspace, ['filter' => ['platform' => 'TikTok']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('pages.data', 1));
});

test('results default to newest first and can be sorted and paginated', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    GencysPage::factory()->for($workspace)->create(['name' => 'Zed', 'date_created' => '2026-01-01']);
    GencysPage::factory()->for($workspace)->create(['name' => 'Ann', 'date_created' => '2026-03-01']);
    GencysPage::factory()->for($workspace)->create(['name' => 'Mia', 'date_created' => '2026-02-01']);

    // Default sort is -date_created.
    $this->actingAs($user)
        ->get(gencysPagesUrl($workspace))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('pages.data.0.name', 'Ann'));

    $this->actingAs($user)
        ->get(gencysPagesUrl($workspace, ['sort' => 'name', 'per_page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pages.per_page', 2)
            ->where('pages.total', 3)
            ->where('pages.data.0.name', 'Ann')
        );
});

test('a member without the permission is forbidden', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = makeWorkspaceMember($workspace, 'member');

    $this->actingAs($member)
        ->get(gencysPagesUrl($workspace))
        ->assertForbidden();
});

test('the sync button fires the n8n pages webhook with the workspace api key', function () {
    config(['services.n8n.gencys_pages_webhook_url' => 'https://n8n.test/webhook/pages']);
    Http::fake(['n8n.test/*' => Http::response(['ok' => true], 200)]);

    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['erp_username' => 'erp-user', 'erp_password' => 'erp-pass']);
    ['raw' => $raw] = makeApiKey($workspace);

    $this->actingAs($user)
        ->post("/workspaces/{$workspace->slug}/gencys/pages/sync")
        ->assertRedirect()
        ->assertSessionHas('success');

    Http::assertSent(function ($request) use ($raw) {
        return $request->url() === 'https://n8n.test/webhook/pages'
            && $request['api_key'] === $raw
            && $request['erp_username'] === 'erp-user'
            && str_ends_with($request['webhook_url'], '/api/v1/public/gencys/pages');
    });
});

test('the sync fails cleanly when the workspace has no ERP credentials', function () {
    config(['services.n8n.gencys_pages_webhook_url' => 'https://n8n.test/webhook/pages']);
    Http::fake();

    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($user)
        ->post("/workspaces/{$workspace->slug}/gencys/pages/sync")
        ->assertRedirect()
        ->assertSessionHas('error');

    Http::assertNothingSent();
});

test('the public callback upserts pages keyed on the gencys page id', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $payload = [
        'data' => [
            'workspace_id' => $workspace->id,
            'api_key' => $raw,
            'pages' => [
                [
                    'id' => 12,
                    'dateCreated' => '2026-05-04',
                    'name' => 'Acme Skincare PH',
                    'owner' => 'Maria Santos',
                    'internAndBrand' => 'Juan Dela Cruz - Acme',
                    'status' => 'Active',
                    'platform' => 'Facebook',
                ],
                ['name' => 'No Page Id'], // skipped: no stable key
            ],
        ],
    ];

    $this->postJson('/api/v1/public/gencys/pages', $payload)
        ->assertOk()
        ->assertJson(['created' => 1, 'updated' => 0, 'skipped' => 1]);

    $page = GencysPage::where('workspace_id', $workspace->id)->where('page_id', 12)->first();
    expect($page)->not->toBeNull()
        ->and($page->name)->toBe('Acme Skincare PH')
        ->and($page->owner)->toBe('Maria Santos')
        ->and($page->platform)->toBe('Facebook')
        ->and($page->date_created->toDateString())->toBe('2026-05-04');

    // Re-syncing the same page id updates rather than duplicates.
    $payload['data']['pages'] = [[
        'id' => 12,
        'name' => 'Acme Skincare PH',
        'status' => 'Inactive',
        'platform' => 'TikTok',
    ]];

    $this->postJson('/api/v1/public/gencys/pages', $payload)
        ->assertOk()
        ->assertJson(['created' => 0, 'updated' => 1]);

    expect(GencysPage::where('workspace_id', $workspace->id)->where('page_id', 12)->count())->toBe(1)
        ->and($page->fresh()->status)->toBe('Inactive');
});

test('the callback links a page to the intern named in intern_and_brand', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $intern = Intern::factory()->for($workspace)->create([
        'full_name' => 'Juan Dela Cruz',
        'username' => 'juan.dc',
    ]);

    $this->postJson('/api/v1/public/gencys/pages', [
        'data' => [
            'workspace_id' => $workspace->id,
            'api_key' => $raw,
            'pages' => [
                ['id' => 1, 'internAndBrand' => 'Juan Dela Cruz - Acme Skincare'],
                ['id' => 2, 'internAndBrand' => 'juan.dc'],
                ['id' => 3, 'internAndBrand' => 'Nobody At All - Brand X'],
            ],
        ],
    ])->assertOk();

    $byName = GencysPage::where('page_id', 1)->first();
    $byUsername = GencysPage::where('page_id', 2)->first();
    $unmatched = GencysPage::where('page_id', 3)->first();

    expect($byName->gencys_intern_id)->toBe($intern->id)
        ->and($byUsername->gencys_intern_id)->toBe($intern->id)
        // An unmatched name must not fail the row — it just stays unlinked.
        ->and($unmatched->gencys_intern_id)->toBeNull()
        ->and($unmatched->intern_and_brand)->toBe('Nobody At All - Brand X');
});

test('an intern from another workspace is never linked', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    Intern::factory()->for($other)->create(['full_name' => 'Juan Dela Cruz']);

    $this->postJson('/api/v1/public/gencys/pages', [
        'data' => [
            'workspace_id' => $workspace->id,
            'api_key' => $raw,
            'pages' => [['id' => 1, 'internAndBrand' => 'Juan Dela Cruz - Acme']],
        ],
    ])->assertOk();

    expect(GencysPage::where('page_id', 1)->first()->gencys_intern_id)->toBeNull();
});

test('the public callback rejects an invalid api key', function () {
    $this->postJson('/api/v1/public/gencys/pages', [
        'data' => [
            'workspace_id' => 1,
            'api_key' => 'art_totally-invalid',
            'pages' => [['id' => 1, 'name' => 'X']],
        ],
    ])->assertStatus(401);

    expect(GencysPage::count())->toBe(0);
});

test('the public callback rejects an api key that does not own the workspace', function () {
    ['workspace' => $a] = makeWorkspaceWithOwner();
    ['workspace' => $b] = makeWorkspaceWithOwner();
    ['raw' => $rawB] = makeApiKey($b);

    // A valid key for workspace B cannot push into workspace A.
    $this->postJson('/api/v1/public/gencys/pages', [
        'data' => [
            'workspace_id' => $a->id,
            'api_key' => $rawB,
            'pages' => [['id' => 1, 'name' => 'X']],
        ],
    ])->assertStatus(401);

    expect(GencysPage::count())->toBe(0);
});
