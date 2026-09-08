<?php

use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\NotificationSetting;
use Modules\MetaAds\Models\User as MetaUser;

const WEBHOOK = 'https://discord.com/api/webhooks/123456789/abcdefTOKEN';

/** Attach an ad account to the workspace through a connected Meta user. */
function seedWorkspaceAccount(Workspace $workspace, int $id, ?int $status, string $name = 'Acct'): AdAccount
{
    $metaUser = MetaUser::firstOrCreate(
        ['id' => 900000 + $workspace->id],
        ['name' => 'Token Owner', 'access_token' => 'fake-token'],
    );
    $metaUser->workspaces()->syncWithoutDetaching([$workspace->id]);

    $account = AdAccount::create(['id' => $id, 'name' => $name, 'account_status' => $status]);
    $metaUser->adAccounts()->attach($account->id);

    return $account;
}

function enableInactiveReport(Workspace $workspace, string $sendAt = '09:00', bool $enabled = true, ?string $webhook = WEBHOOK): void
{
    NotificationSetting::create([
        'workspace_id' => $workspace->id,
        'inactive_accounts_enabled' => $enabled,
        'inactive_accounts_webhook_url' => $webhook,
        'inactive_accounts_send_at' => $sendAt,
    ]);
}

/** The single Discord embed that was posted. */
function postedEmbed(): array
{
    $sent = null;
    Http::recorded(function ($request) use (&$sent) {
        $sent ??= $request->data();
    });

    return $sent['embeds'][0] ?? [];
}

/**
 * All rendered text of the posted embed — summary line plus every field
 * heading and body. Assertions care that an account was reported, not which
 * severity field it landed in.
 */
function postedDescription(): string
{
    $embed = postedEmbed();

    $fields = collect($embed['fields'] ?? [])
        ->map(fn ($f) => $f['name']."\n".$f['value'])
        ->implode("\n");

    return ($embed['description'] ?? '')."\n".$fields;
}

/** The body of the severity field whose heading contains $heading. */
function postedField(string $heading): string
{
    return collect(postedEmbed()['fields'] ?? [])
        ->first(fn ($f) => str_contains($f['name'], $heading))['value'] ?? '';
}

beforeEach(fn () => Http::fake([WEBHOOK => Http::response('', 204)]));

it('posts only the accounts Meta does not report as active', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableInactiveReport($workspace);

    seedWorkspaceAccount($workspace, 111, status: 1, name: 'Healthy Co');
    seedWorkspaceAccount($workspace, 222, status: 2, name: 'Disabled Co');
    seedWorkspaceAccount($workspace, 333, status: 3, name: 'Unsettled Co');

    test()->artisan('metaads:report-inactive-accounts', ['--force' => true])->assertSuccessful();

    $body = postedDescription();

    expect($body)->toContain('Disabled Co')
        ->and($body)->toContain('Unsettled Co')
        ->and($body)->not->toContain('Healthy Co');
});

it('treats an account Meta has never reported a status for as not active', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableInactiveReport($workspace);

    seedWorkspaceAccount($workspace, 444, status: null, name: 'Never Synced');

    test()->artisan('metaads:report-inactive-accounts', ['--force' => true])->assertSuccessful();

    expect(postedDescription())->toContain('Never Synced')->toContain('Unknown');
});

it('sends nothing when every account is active', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableInactiveReport($workspace);

    seedWorkspaceAccount($workspace, 555, status: 1, name: 'Healthy Co');

    test()->artisan('metaads:report-inactive-accounts', ['--force' => true])->assertSuccessful();

    Http::assertNothingSent();
});

it('only sends at the configured hour', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableInactiveReport($workspace, sendAt: '09:00');
    seedWorkspaceAccount($workspace, 666, status: 2);

    $this->travelTo(now()->setTime(10, 0));
    test()->artisan('metaads:report-inactive-accounts')->assertSuccessful();
    Http::assertNothingSent();

    $this->travelTo(now()->setTime(9, 0));
    test()->artisan('metaads:report-inactive-accounts')->assertSuccessful();
    Http::assertSentCount(1);
});

it('skips a workspace with the report disabled or no webhook', function (bool $enabled, ?string $webhook) {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableInactiveReport($workspace, enabled: $enabled, webhook: $webhook);
    seedWorkspaceAccount($workspace, 777, status: 2);

    test()->artisan('metaads:report-inactive-accounts', ['--force' => true])->assertSuccessful();

    Http::assertNothingSent();
})->with([
    'disabled' => [false, WEBHOOK],
    'no webhook' => [true, null],
]);

it('does not leak another workspace\'s accounts', function () {
    ['workspace' => $mine] = actingAsWorkspaceOwner();
    ['workspace' => $theirs] = makeWorkspaceWithOwner();

    enableInactiveReport($mine);
    seedWorkspaceAccount($mine, 888, status: 2, name: 'Mine Disabled');
    seedWorkspaceAccount($theirs, 999, status: 2, name: 'Theirs Disabled');

    test()->artisan('metaads:report-inactive-accounts', ['--force' => true])->assertSuccessful();

    $body = postedDescription();

    expect($body)->toContain('Mine Disabled')->not->toContain('Theirs Disabled');
});
it('limits the report to the workspace named by --workspace', function (bool $bySlug) {
    ['workspace' => $mine] = actingAsWorkspaceOwner();
    ['workspace' => $theirs] = makeWorkspaceWithOwner();

    // Both are opted in and due, so only the option can narrow the send.
    enableInactiveReport($mine);
    enableInactiveReport($theirs);
    seedWorkspaceAccount($mine, 1111, status: 2, name: 'Mine Disabled');
    seedWorkspaceAccount($theirs, 2222, status: 2, name: 'Theirs Disabled');

    test()->artisan('metaads:report-inactive-accounts', [
        '--force' => true,
        '--workspace' => $bySlug ? $mine->slug : (string) $mine->id,
    ])->assertSuccessful();

    Http::assertSentCount(1);

    $body = postedDescription();

    expect($body)->toContain('Mine Disabled')->not->toContain('Theirs Disabled');
})->with([
    'by slug' => true,
    'by id' => false,
]);

it('sends nothing when --workspace matches no workspace', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableInactiveReport($workspace);
    seedWorkspaceAccount($workspace, 3333, status: 2, name: 'Mine Disabled');

    test()->artisan('metaads:report-inactive-accounts', [
        '--force' => true,
        '--workspace' => 'no-such-workspace',
    ])->assertSuccessful();

    Http::assertNothingSent();
});

// --workspace narrows which workspaces are considered; it does not override the
// configured send time. Without --force an out-of-hours run still sends nothing.
it('still respects the configured hour when --workspace is given', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableInactiveReport($workspace, sendAt: '09:00');
    seedWorkspaceAccount($workspace, 4444, status: 2, name: 'Mine Disabled');

    $this->travelTo(now()->setTime(10, 0));

    test()->artisan('metaads:report-inactive-accounts', ['--workspace' => $workspace->slug])
        ->assertSuccessful();

    Http::assertNothingSent();
});

// ── Embed design ────────────────────────────────────────────────────────────

it('groups accounts into severity tiers rather than one flat list', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableInactiveReport($workspace);

    seedWorkspaceAccount($workspace, 5001, status: 2, name: 'Disabled Co');
    seedWorkspaceAccount($workspace, 5002, status: 9, name: 'Grace Co');
    seedWorkspaceAccount($workspace, 5003, status: 101, name: 'Closed Co');

    test()->artisan('metaads:report-inactive-accounts', ['--force' => true])->assertSuccessful();

    expect(postedField('Action needed'))->toContain('Disabled Co')->not->toContain('Closed Co')
        ->and(postedField('Billing or review'))->toContain('Grace Co')
        ->and(postedField('Closed or never synced'))->toContain('Closed Co');
});

it('takes the colour of the worst tier present', function (int $status, int $expected) {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableInactiveReport($workspace);

    // A closed account is always present, so the colour can only come from the
    // more severe account under test.
    seedWorkspaceAccount($workspace, 6001, status: 101, name: 'Closed Co');
    seedWorkspaceAccount($workspace, 6002, status: $status, name: 'Under Test');

    test()->artisan('metaads:report-inactive-accounts', ['--force' => true])->assertSuccessful();

    expect(postedEmbed()['color'])->toBe($expected);
})->with([
    'disabled is red' => [2, 0xED4245],
    'unsettled is amber' => [3, 0xE67E22],
]);

it('is grey when nothing worse than a closed account is reported', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableInactiveReport($workspace);

    seedWorkspaceAccount($workspace, 6003, status: 101, name: 'Closed Co');

    test()->artisan('metaads:report-inactive-accounts', ['--force' => true])->assertSuccessful();

    expect(postedEmbed()['color'])->toBe(0x99AAB5);
});

it('names the workspace so two workspaces sharing a channel stay apart', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableInactiveReport($workspace);
    seedWorkspaceAccount($workspace, 7001, status: 2);

    test()->artisan('metaads:report-inactive-accounts', ['--force' => true])->assertSuccessful();

    expect(postedEmbed()['author']['name'])->toBe($workspace->name);
});

it('deep-links each account into ads manager', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableInactiveReport($workspace);
    seedWorkspaceAccount($workspace, 7002, status: 2, name: 'Disabled Co');

    test()->artisan('metaads:report-inactive-accounts', ['--force' => true])->assertSuccessful();

    expect(postedDescription())
        ->toContain('[Disabled Co](https://adsmanager.facebook.com/adsmanager/manage/campaigns?act=7002)');
});

it('reports how stale the status is', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableInactiveReport($workspace);

    seedWorkspaceAccount($workspace, 7003, status: 2, name: 'Synced Co')
        ->update(['last_synced_at' => now()->subDays(3)]);
    seedWorkspaceAccount($workspace, 7004, status: null, name: 'Never Co');

    test()->artisan('metaads:report-inactive-accounts', ['--force' => true])->assertSuccessful();

    expect(postedField('Action needed'))->toContain('synced')
        ->and(postedField('Closed or never synced'))->toContain('never synced');
});

// Meta lets people put underscores and asterisks in an account name, which
// would otherwise turn the rest of the Discord line italic or bold.
it('escapes markdown in account names', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableInactiveReport($workspace);
    seedWorkspaceAccount($workspace, 7005, status: 2, name: 'Bold*Name_Here');

    test()->artisan('metaads:report-inactive-accounts', ['--force' => true])->assertSuccessful();

    expect(postedDescription())->toContain('Bold\*Name\_Here');
});

// Discord rejects an embed whose field value exceeds 1024 characters outright,
// which would drop the whole report rather than truncate it.
it('caps a long tier and says how many it left out', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableInactiveReport($workspace);

    foreach (range(1, 14) as $i) {
        seedWorkspaceAccount($workspace, 8000 + $i, status: 2, name: "Disabled Co {$i}");
    }

    test()->artisan('metaads:report-inactive-accounts', ['--force' => true])->assertSuccessful();

    $field = postedField('Action needed');

    expect(mb_strlen($field))->toBeLessThanOrEqual(1024)
        ->and($field)->toContain('more');

    foreach (postedEmbed()['fields'] as $f) {
        expect(mb_strlen($f['value']))->toBeLessThanOrEqual(1024);
    }
});

// The account id is what someone pastes into Ads Manager or a support ticket,
// so it is rendered as inline code — the bare id, with no `act_` prefix to
// strip off after copying.
it('shows the bare account id for each account', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableInactiveReport($workspace);
    seedWorkspaceAccount($workspace, 9101, status: 2, name: 'Disabled Co');

    test()->artisan('metaads:report-inactive-accounts', ['--force' => true])->assertSuccessful();

    expect(postedField('Action needed'))->toContain('`9101`')
        ->and(postedField('Action needed'))->not->toContain('act_9101');
});

// ── Hierarchy ───────────────────────────────────────────────────────────────

// The title is the verdict, not the total: one disabled account among five
// closed ones is "1 account needs action", not "6 accounts".
it('titles the embed with the worst tier and its own count', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableInactiveReport($workspace);

    seedWorkspaceAccount($workspace, 9201, status: 2, name: 'Disabled Co');
    foreach (range(1, 5) as $i) {
        seedWorkspaceAccount($workspace, 9210 + $i, status: 101, name: "Closed Co {$i}");
    }

    test()->artisan('metaads:report-inactive-accounts', ['--force' => true])->assertSuccessful();

    expect(postedEmbed()['title'])->toBe('🔴 Action needed — 1 account');
});

it('pluralises the title count', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableInactiveReport($workspace);

    seedWorkspaceAccount($workspace, 9301, status: 2, name: 'Disabled One');
    seedWorkspaceAccount($workspace, 9302, status: 2, name: 'Disabled Two');

    test()->artisan('metaads:report-inactive-accounts', ['--force' => true])->assertSuccessful();

    expect(postedEmbed()['title'])->toBe('🔴 Action needed — 2 accounts');
});

it('summarises the split only when more than one tier is present', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableInactiveReport($workspace);

    seedWorkspaceAccount($workspace, 9401, status: 2, name: 'Disabled Co');
    seedWorkspaceAccount($workspace, 9402, status: 101, name: 'Closed Co');

    test()->artisan('metaads:report-inactive-accounts', ['--force' => true])->assertSuccessful();

    expect(postedEmbed()['description'])->toBe('1 action needed · 1 closed or never synced');
});

// With one tier the summary would only restate the title, so it is dropped.
it('omits the summary when a single tier says it all', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableInactiveReport($workspace);

    seedWorkspaceAccount($workspace, 9501, status: 2, name: 'Disabled Co');

    test()->artisan('metaads:report-inactive-accounts', ['--force' => true])->assertSuccessful();

    expect(postedEmbed()['description'])->toBe('');
});

// The status is why the row exists, so it sits on the name line rather than
// buried in the dot-separated identity line below it.
it('puts the status on the name line, not in the identity line', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    enableInactiveReport($workspace);

    seedWorkspaceAccount($workspace, 9601, status: 2, name: 'Disabled Co');

    test()->artisan('metaads:report-inactive-accounts', ['--force' => true])->assertSuccessful();

    [$nameLine, $identityLine] = explode("\n", postedField('Action needed'));

    expect($nameLine)->toContain('Disabled Co')->toContain('**Disabled**')
        ->and($identityLine)->toContain('`9601`')->not->toContain('Disabled');
});
