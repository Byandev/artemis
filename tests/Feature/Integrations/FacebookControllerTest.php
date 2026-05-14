<?php

use App\Jobs\FetchAdAccounts;
use App\Models\FacebookAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

function fakeFacebookOauth(mixed $tokenOverride = null, mixed $meOverride = null): void
{
    $tokenResponse = $tokenOverride ?? Http::response(['access_token' => 'fb-token-123'], 200);
    $meResponse = $meOverride ?? Http::response([
        'id' => '7777',
        'name' => 'FB User',
        'email' => 'fb@example.test',
        'picture' => ['data' => ['url' => 'https://cdn.example.test/p.png']],
    ], 200);

    Http::fake([
        'graph.facebook.com/v22.0/oauth/access_token' => $tokenResponse,
        'graph.facebook.com/v22.0/me*' => $meResponse,
    ]);
}

test('callback exchanges code, persists account, links workspace, dispatches FetchAdAccounts', function () {
    Bus::fake();
    fakeFacebookOauth();

    $user = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($user)->create();
    $state = json_encode(['auth_id' => $user->id, 'workspace_id' => $workspace->id]);

    $this->get('/auth/facebook/callback?code=auth-code&state='.urlencode($state))
        ->assertRedirect("workspaces/{$workspace->slug}/facebook-accounts");

    $account = FacebookAccount::find('7777');
    expect($account)->not->toBeNull();
    expect($account->user_id)->toBe($user->id);
    expect($account->access_token)->toBe('fb-token-123');
    expect($account->workspaces()->where('workspaces.id', $workspace->id)->exists())->toBeTrue();
    Bus::assertDispatched(FetchAdAccounts::class);
});

test('callback redirects home when token exchange fails', function () {
    Bus::fake();
    fakeFacebookOauth(tokenOverride: Http::response(['error' => 'invalid_grant'], 400));

    $user = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($user)->create();
    $state = json_encode(['auth_id' => $user->id, 'workspace_id' => $workspace->id]);

    $this->get('/auth/facebook/callback?code=bad-code&state='.urlencode($state))
        ->assertRedirect('/');

    expect(FacebookAccount::count())->toBe(0);
    Bus::assertNothingDispatched();
});

test('callback redirects home when /me fetch fails', function () {
    Bus::fake();
    fakeFacebookOauth(meOverride: Http::response([], 500));

    $user = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($user)->create();
    $state = json_encode(['auth_id' => $user->id, 'workspace_id' => $workspace->id]);

    $this->get('/auth/facebook/callback?code=ok&state='.urlencode($state))
        ->assertRedirect('/');

    expect(FacebookAccount::count())->toBe(0);
    Bus::assertNothingDispatched();
});

test('callback updates an existing FacebookAccount instead of duplicating', function () {
    Bus::fake();
    fakeFacebookOauth();

    $user = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($user)->create();

    FacebookAccount::create([
        'id' => '7777',
        'user_id' => $user->id,
        'name' => 'Old Name',
        'email' => 'old@example.test',
        'access_token' => 'old-token',
    ]);

    $state = json_encode(['auth_id' => $user->id, 'workspace_id' => $workspace->id]);

    $this->get('/auth/facebook/callback?code=ok&state='.urlencode($state))
        ->assertRedirect("workspaces/{$workspace->slug}/facebook-accounts");

    expect(FacebookAccount::count())->toBe(1);
    expect(FacebookAccount::find('7777')->access_token)->toBe('fb-token-123');
    expect(FacebookAccount::find('7777')->name)->toBe('FB User');
});

test('callback handles missing picture in profile response', function () {
    Bus::fake();
    fakeFacebookOauth(meOverride: Http::response([
        'id' => '8888',
        'name' => 'No Pic',
        'email' => 'np@example.test',
        // no 'picture' key
    ], 200));

    $user = User::factory()->create();
    $workspace = Workspace::factory()->forOwner($user)->create();
    $state = json_encode(['auth_id' => $user->id, 'workspace_id' => $workspace->id]);

    $this->get('/auth/facebook/callback?code=ok&state='.urlencode($state))
        ->assertRedirect("workspaces/{$workspace->slug}/facebook-accounts");

    expect(FacebookAccount::find('8888')->picture_url)->toBeNull();
});
