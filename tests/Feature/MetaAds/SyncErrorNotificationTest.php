<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\MetaAds\Exceptions\MetaGraphException;
use Modules\MetaAds\Jobs\SyncInsights;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\SyncRun;
use Modules\MetaAds\Models\User as MetaUser;

/**
 * The sync itself posts to Discord when it gives up on an ad account — no
 * schedule, no digest. These run the real job against a faked Graph error, so
 * they cover the wiring as well as the message.
 */
const HOOK = 'https://discord.com/api/webhooks/888/sync-errors';

beforeEach(function () {
    config()->set('services.discord.meta_ads_webhook_url', HOOK);
    Cache::flush();
});

function failingSyncAccount(?int $status = 1): AdAccount
{
    $metaUser = MetaUser::create(['id' => 7401, 'name' => 'Token Owner', 'access_token' => 'tok']);
    $account = AdAccount::create(['id' => 555000333, 'name' => 'Shanna Mae', 'account_status' => $status]);
    $metaUser->adAccounts()->attach($account->id);

    return $account;
}

/** Meta returns $code; Discord accepts the post. */
function fakeGraph(int $code, string $message = 'Boom', int $status = 400): void
{
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'error' => [
                'message' => $message,
                'code' => $code,
                'error_subcode' => 463,
                'type' => 'OAuthException',
                'fbtrace_id' => 'TraceMe123',
            ],
        ], $status),
        HOOK => Http::response('', 204),
    ]);
}

function postedEmbeds(): array
{
    $embeds = [];
    Http::recorded(function ($request) use (&$embeds) {
        if (str_contains($request->url(), 'discord.com')) {
            $embeds[] = $request->data()['embeds'][0];
        }
    });

    return $embeds;
}

function runFailingSync(AdAccount $account): void
{
    try {
        (new SyncInsights($account, '2026-06-07'))->handle();
    } catch (Throwable) {
        // The job rethrows by design; the notification is what we're asserting.
    }
}

it('posts to Discord while syncing when an ad account errors', function () {
    fakeGraph(190, 'Error validating access token: Session has expired.');

    runFailingSync(failingSyncAccount());

    $embeds = postedEmbeds();

    expect($embeds)->toHaveCount(1);

    $embed = $embeds[0];
    $fields = collect($embed['fields'])->keyBy('name');

    expect($embed['title'])->toBe('🔴 Token expired — Shanna Mae')
        // The body says what to do, not what the exception said.
        ->and($embed['description'])->toContain('Reconnect')
        ->and($embed['color'])->toBe(0xED4245)
        ->and($fields['Ad account']['value'])->toContain('555000333')
        ->and($fields['Ad account']['value'])->toContain('adsmanager.facebook.com')
        ->and($fields['Sync']['value'])->toBe('SyncInsights')
        ->and($fields['Meta says']['value'])->toContain('Session has expired')
        ->and($fields['Reference']['value'])->toContain('code 190')
        ->and($fields['Reference']['value'])->toContain('TraceMe123');
});

// An unsettled or grace-period account is usually the real cause, so it is
// flagged rather than left for someone to go and look up.
it('flags a non-active account status', function (?int $status, string $label) {
    fakeGraph(190);

    runFailingSync(failingSyncAccount($status));

    expect(collect(postedEmbeds()[0]['fields'])->firstWhere('name', 'Status')['value'])
        ->toBe("⚠️ {$label}");
})->with([
    'unsettled' => [3, 'Unsettled'],
    'in grace period' => [9, 'In Grace Period'],
    'never reported' => [null, 'Unknown'],
]);

// Unsettled billing is the cause; the token error is a symptom of it, so the
// account status leads the headline rather than the error code.
it('leads with the account status when it outranks the error code', function () {
    fakeGraph(190);

    runFailingSync(failingSyncAccount(3));

    $embed = postedEmbeds()[0];

    expect($embed['title'])->toBe('🔴 Unsettled — Shanna Mae')
        ->and($embed['description'])->toContain('Settle it in Ads Manager');
});

it('maps known Meta codes to a plain-language headline', function (int $code, string $headline) {
    fakeGraph($code);

    runFailingSync(failingSyncAccount(1));

    expect(postedEmbeds()[0]['title'])->toBe("🔴 {$headline} — Shanna Mae");
})->with([
    'token' => [190, 'Token expired'],
    'permission' => [200, 'Permission denied'],
    'missing object' => [803, 'Object not found'],
]);

// An unmapped code must still produce a usable post, not a blank headline.
it('falls back to a generic headline for an unknown code', function () {
    fakeGraph(999999);

    runFailingSync(failingSyncAccount(1));

    expect(postedEmbeds()[0]['title'])->toBe('🔴 Sync failed — Shanna Mae');
});

it('does not flag an active account', function () {
    fakeGraph(190);

    runFailingSync(failingSyncAccount(1));

    expect(collect(postedEmbeds()[0]['fields'])->firstWhere('name', 'Status')['value'])
        ->toBe('Active');
});

// Rate-limited and transient errors release the job for a retry — nothing has
// failed yet, so the channel stays quiet.
it('stays quiet while the job is still going to retry', function (int $code) {
    fakeGraph($code, 'Please retry');

    (new SyncInsights(failingSyncAccount(), '2026-06-07'))->handle();

    expect(postedEmbeds())->toBeEmpty();
})->with([
    'rate limited' => 17,
    'transient' => 1,
]);

// One expired token fails every job under it; without this the channel would
// get dozens of identical posts from a single outage.
it('reports an account once, then mutes it briefly', function () {
    fakeGraph(190);
    $account = failingSyncAccount();

    runFailingSync($account);
    runFailingSync($account);
    runFailingSync($account);

    expect(postedEmbeds())->toHaveCount(1);
});

it('still records the failure on the sync run', function () {
    fakeGraph(190, 'Session has expired.');

    runFailingSync(failingSyncAccount());

    $run = SyncRun::latest('id')->first();

    expect($run->status)->toBe(SyncRun::STATUS_FAILED)
        ->and(((array) $run->meta)['error_code'])->toBe(190);
});

it('sends nothing when no webhook is configured', function () {
    config()->set('services.discord.meta_ads_webhook_url', null);
    config()->set('services.discord.webhook_url', null);
    fakeGraph(190);

    runFailingSync(failingSyncAccount());

    expect(postedEmbeds())->toBeEmpty();
});

// The job has already failed; a dead webhook must not mask the real error.
it('swallows a webhook failure instead of throwing', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Boom', 'code' => 190]], 400),
        HOOK => fn () => throw new RuntimeException('Discord unreachable'),
    ]);

    expect(fn () => (new SyncInsights(failingSyncAccount(), '2026-06-07'))->handle())
        ->toThrow(MetaGraphException::class);
});
