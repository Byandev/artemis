<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\MetaAds\Exceptions\MetaGraphException;
use Modules\MetaAds\Jobs\SyncInsights;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\SyncRun;
use Modules\MetaAds\Models\User as MetaUser;

/**
 * The sync posts to Discord itself when it gives up on an ad account. The
 * channel is read by clients, so these assert plain language and the absence of
 * anything technical, as well as the wiring — the real job runs against a faked
 * Graph error.
 */
const HOOK = 'https://discord.com/api/webhooks/888/sync-errors';

beforeEach(function () {
    config()->set('services.discord.meta_ads_webhook_url', HOOK);
    Cache::flush();
});

/** A Facebook account owning ad accounts with the given statuses. */
function fbAccountWith(array $accounts): array
{
    $metaUser = MetaUser::create(['id' => 7501, 'name' => 'Bryan M', 'access_token' => 'tok']);
    $made = [];

    foreach ($accounts as $i => [$name, $status]) {
        $account = AdAccount::create([
            'id' => 555000400 + $i,
            'name' => $name,
            'account_status' => $status,
        ]);
        $metaUser->adAccounts()->attach($account->id);
        $made[] = $account;
    }

    return $made;
}

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
        // The job rethrows by design; the notification is what we assert.
    }
}

it('names the Facebook account and lists its problem ad accounts', function () {
    fakeGraph(190);
    [$failed] = fbAccountWith([
        ['Shanna Mae', 1],      // active, but this is the one that failed
        ['Andree Boston', 2],   // disabled
        ['Igie Almazora', 3],   // unsettled
        ['Healthy Co', 1],      // active and fine — must not be listed
    ]);

    runFailingSync($failed);

    $embed = postedEmbeds()[0];

    expect($embed['title'])->toBe('⚠️ Meta Ads needs your attention')
        ->and($embed['description'])->toContain('Bryan M');

    $list = $embed['fields'][0];

    expect($list['name'])->toBe('3 ad accounts affected')
        // The id travels with each account so a client can quote it to Meta.
        ->and($list['value'])->toContain('`555000401`')
        ->and($list['value'])->toContain('Andree Boston')
        ->toContain('Igie Almazora')
        ->toContain('Shanna Mae')
        ->not->toContain('Healthy Co');
});

// The channel is read by clients: an error code or a job name is noise they
// cannot act on, and it must not leak into the post.
it('shows Meta\'s own status wording, with nothing technical', function () {
    fakeGraph(190, 'Error validating access token: Session has expired.');
    [$failed] = fbAccountWith([['Unsettled Co', 3]]);

    runFailingSync($failed);

    $embed = postedEmbeds()[0];
    $text = json_encode($embed);

    expect($embed['fields'][0]['value'])->toContain('Unsettled')
        ->and($text)->not->toContain('190')
        ->not->toContain('OAuthException')
        ->not->toContain('TraceMe123')
        ->not->toContain('SyncInsights')
        ->not->toContain('access token');
});

// Same wording as the ad-accounts table in the dashboard, so the channel and
// the UI never disagree about what an account's state is called.
it('shows the status Meta reports for each account', function (int $status, string $label) {
    fakeGraph(190);
    [$failed] = fbAccountWith([['Problem Co', $status]]);

    runFailingSync($failed);

    expect(postedEmbeds()[0]['fields'][0]['value'])->toContain($label);
})->with([
    'disabled' => [2, 'Disabled'],
    'unsettled' => [3, 'Unsettled'],
    'grace period' => [9, 'In Grace Period'],
    'risk review' => [7, 'Pending Risk Review'],
    'closed' => [101, 'Closed'],
]);

// Meta may still call the account active while its sync is broken; an alert
// listing no accounts would leave the reader with nothing to act on.
it('still names the failed account when Meta calls it active', function () {
    fakeGraph(190);
    [$failed] = fbAccountWith([['All Good Co', 1]]);

    runFailingSync($failed);

    $list = postedEmbeds()[0]['fields'][0];

    // "Active" would read as nonsense on an alert, so this one case says what
    // actually happened rather than echoing the status.
    expect($list['name'])->toBe('1 ad account affected')
        ->and($list['value'])->toContain('All Good Co')
        ->toContain('Sync error')
        ->not->toContain('Active');
});

// Rate-limited and transient errors release the job for a retry — nothing has
// failed yet, so the client channel stays quiet.
it('stays quiet while the job is still going to retry', function (int $code) {
    fakeGraph($code, 'Please retry');
    [$failed] = fbAccountWith([['Retrying Co', 1]]);

    (new SyncInsights($failed, '2026-06-07'))->handle();

    expect(postedEmbeds())->toBeEmpty();
})->with([
    'rate limited' => 17,
    'transient' => 1,
]);

// One expired token fails every job under the same Facebook account; without
// this a single outage would arrive as dozens of identical posts.
it('reports a Facebook account once, then mutes it briefly', function () {
    fakeGraph(190);
    [$first, $second] = fbAccountWith([['One', 2], ['Two', 3]]);

    runFailingSync($first);
    runFailingSync($second);
    runFailingSync($first);

    expect(postedEmbeds())->toHaveCount(1);
});

it('still records the technical failure on the sync run', function () {
    fakeGraph(190, 'Session has expired.');
    [$failed] = fbAccountWith([['Shanna Mae', 1]]);

    runFailingSync($failed);

    $run = SyncRun::latest('id')->first();

    // Plain language for the client, full detail for whoever debugs it.
    expect($run->status)->toBe(SyncRun::STATUS_FAILED)
        ->and(((array) $run->meta)['error_code'])->toBe(190)
        ->and($run->error_message)->toContain('Session has expired');
});

it('sends nothing when no webhook is configured', function () {
    config()->set('services.discord.meta_ads_webhook_url', null);
    config()->set('services.discord.webhook_url', null);
    fakeGraph(190);
    [$failed] = fbAccountWith([['Nobody Listening', 2]]);

    runFailingSync($failed);

    expect(postedEmbeds())->toBeEmpty();
});

// The job has already failed; a dead webhook must not mask the real error.
it('swallows a webhook failure instead of throwing', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Boom', 'code' => 190]], 400),
        HOOK => fn () => throw new RuntimeException('Discord unreachable'),
    ]);
    [$failed] = fbAccountWith([['Shanna Mae', 2]]);

    expect(fn () => (new SyncInsights($failed, '2026-06-07'))->handle())
        ->toThrow(MetaGraphException::class);
});

// Discord drops the whole post if a field runs past 1024 characters.
it('caps a long list and says how many it left out', function () {
    fakeGraph(190);
    $accounts = [];
    foreach (range(1, 14) as $i) {
        $accounts[] = ["Ad Account Number {$i}", 3];
    }
    $made = fbAccountWith($accounts);

    runFailingSync($made[0]);

    $value = postedEmbeds()[0]['fields'][0]['value'];

    expect(mb_strlen($value))->toBeLessThanOrEqual(1024)
        ->and($value)->toContain('more');
});

// The id is what a client pastes into a Meta support ticket, so it rides with
// every account — bare, in backticks, with no act_ prefix to strip off.
it('shows the ad account id beside each name', function () {
    fakeGraph(190);
    [$failed] = fbAccountWith([['Andree Boston', 2]]);

    runFailingSync($failed);

    $value = postedEmbeds()[0]['fields'][0]['value'];

    expect($value)->toContain('**Andree Boston** · `555000400`')
        ->not->toContain('act_555000400');
});
