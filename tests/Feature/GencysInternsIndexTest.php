<?php

use Illuminate\Support\Facades\Http;
use Modules\GencysERP\Models\Intern;

function internsUrl($workspace, array $query = []): string
{
    $url = "/workspaces/{$workspace->slug}/gencys/interns";

    return $query ? $url.'?'.http_build_query($query) : $url;
}

test('the interns index lists workspace-scoped interns for the owner', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();

    Intern::factory()->for($workspace)->count(3)->create();
    Intern::factory()->for($other)->create(['full_name' => 'Outsider']);

    $this->actingAs($user)
        ->get(internsUrl($workspace))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/gencys/interns/index')
            ->has('interns.data', 3)
            ->where('interns.data.0.full_name', fn ($name) => $name !== 'Outsider')
        );
});

test('search matches across name, company, username and contact', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    Intern::factory()->for($workspace)->create(['full_name' => 'Alpha Reyes']);
    Intern::factory()->for($workspace)->create(['company_name' => 'Beta Corp']);
    Intern::factory()->for($workspace)->create(['username' => 'gamma_user']);

    $this->actingAs($user)
        ->get(internsUrl($workspace, ['filter' => ['search' => 'Beta']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('interns.data', 1)
            ->where('interns.data.0.company_name', 'Beta Corp')
        );
});

test('the company filter narrows results and exposes distinct companies', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    Intern::factory()->for($workspace)->count(2)->create(['company_name' => 'Acme']);
    Intern::factory()->for($workspace)->create(['company_name' => 'Globex']);

    $this->actingAs($user)
        ->get(internsUrl($workspace, ['filter' => ['company_name' => 'Acme']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('interns.data', 2)
            ->has('companies', 2)
        );
});

test('results can be sorted and paginated', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    Intern::factory()->for($workspace)->create(['full_name' => 'Zed']);
    Intern::factory()->for($workspace)->create(['full_name' => 'Ann']);
    Intern::factory()->for($workspace)->create(['full_name' => 'Mia']);

    $this->actingAs($user)
        ->get(internsUrl($workspace, ['sort' => 'full_name', 'per_page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('interns.per_page', 2)
            ->where('interns.total', 3)
            ->where('interns.data.0.full_name', 'Ann')
        );
});

test('a member without the permission is forbidden', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = makeWorkspaceMember($workspace, 'member');

    $this->actingAs($member)
        ->get(internsUrl($workspace))
        ->assertForbidden();
});

test('the sync button fires the n8n interns webhook with the workspace api key', function () {
    config(['services.n8n.gencys_interns_webhook_url' => 'https://n8n.test/webhook/interns']);
    Http::fake(['n8n.test/*' => Http::response(['ok' => true], 200)]);

    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['erp_username' => 'erp-user', 'erp_password' => 'erp-pass']);
    ['raw' => $raw] = makeApiKey($workspace);

    $this->actingAs($user)
        ->post("/workspaces/{$workspace->slug}/gencys/interns/sync")
        ->assertRedirect()
        ->assertSessionHas('success');

    Http::assertSent(function ($request) use ($raw) {
        return $request->url() === 'https://n8n.test/webhook/interns'
            && $request['api_key'] === $raw
            && $request['erp_username'] === 'erp-user'
            && str_ends_with($request['webhook_url'], '/api/v1/public/gencys/interns');
    });
});

test('the sync fails cleanly when the workspace has no ERP credentials', function () {
    config(['services.n8n.gencys_interns_webhook_url' => 'https://n8n.test/webhook/interns']);
    Http::fake();

    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($user)
        ->post("/workspaces/{$workspace->slug}/gencys/interns/sync")
        ->assertRedirect()
        ->assertSessionHas('error');

    Http::assertNothingSent();
});

test('the public callback upserts interns keyed on the gencys row id', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $payload = [
        'data' => [
            'workspace_id' => $workspace->id,
            'api_key' => $raw,
            'interns' => [
                [
                    'id' => 12,
                    'fullName' => 'Juan Dela Cruz',
                    'companyName' => 'Acme',
                    'username' => 'juan.dc',
                    'contactNumber' => '09171234567',
                    'email' => 'juan@acme.test',
                ],
                ['fullName' => 'No Row Id'], // skipped: no stable key
            ],
        ],
    ];

    $this->postJson('/api/v1/public/gencys/interns', $payload)
        ->assertOk()
        ->assertJson(['created' => 1, 'updated' => 0, 'skipped' => 1]);

    $intern = Intern::where('workspace_id', $workspace->id)->where('intern_id', 12)->first();
    expect($intern)->not->toBeNull()
        ->and($intern->full_name)->toBe('Juan Dela Cruz')
        ->and($intern->contact_number)->toBe('09171234567')
        ->and($intern->email)->toBe('juan@acme.test');

    // Re-syncing the same row id updates rather than duplicates.
    $payload['data']['interns'] = [[
        'id' => 12,
        'fullName' => 'Juan Dela Cruz',
        'companyName' => 'Globex',
        'username' => 'juan.dc',
        'contactNumber' => '09171234567',
        'email' => 'juan@acme.test',
    ]];

    $this->postJson('/api/v1/public/gencys/interns', $payload)
        ->assertOk()
        ->assertJson(['created' => 0, 'updated' => 1]);

    expect(Intern::where('workspace_id', $workspace->id)->where('intern_id', 12)->count())->toBe(1)
        ->and($intern->fresh()->company_name)->toBe('Globex');
});

test('the public callback rejects an invalid api key', function () {
    $this->postJson('/api/v1/public/gencys/interns', [
        'data' => [
            'workspace_id' => 1,
            'api_key' => 'art_totally-invalid',
            'interns' => [['id' => 1, 'fullName' => 'X']],
        ],
    ])->assertStatus(401);

    expect(Intern::count())->toBe(0);
});

test('the public callback rejects an api key that does not own the workspace', function () {
    ['workspace' => $a] = makeWorkspaceWithOwner();
    ['workspace' => $b] = makeWorkspaceWithOwner();
    ['raw' => $rawB] = makeApiKey($b);

    // A valid key for workspace B cannot push into workspace A.
    $this->postJson('/api/v1/public/gencys/interns', [
        'data' => [
            'workspace_id' => $a->id,
            'api_key' => $rawB,
            'interns' => [['id' => 1, 'fullName' => 'X']],
        ],
    ])->assertStatus(401);

    expect(Intern::count())->toBe(0);
});
