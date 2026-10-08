<?php

use App\Models\Workspace;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\OrderForDelivery;

function makeSheetDelivery(Workspace $workspace, string $trackingCode, ?string $date = null): OrderForDelivery
{
    $order = Order::create([
        'workspace_id' => $workspace->id,
        'shop_id' => 1,
        'customer_id' => (string) Str::uuid(),
        'order_number' => 'ORD-'.fake()->unique()->numberBetween(1, 999999),
        'status' => 2,
        'status_name' => 'shipped',
        'tracking_code' => $trackingCode,
        'inserted_at' => now(),
    ]);

    return OrderForDelivery::create([
        'order_id' => $order->id,
        'workspace_id' => $workspace->id,
        'shop_id' => 1,
        'delivery_date' => $date ?? now()->toDateString(),
        'rider_name' => fake()->name(),
        'rider_phone' => fake()->numerify('09#########'),
        'status' => 'PENDING',
    ]);
}

beforeEach(function () {
    $this->workspace = makeWorkspaceWithOwner()['workspace'];
    $this->apiKey = makeApiKey($this->workspace)['raw'];
    $this->workspace->rmoSetting()->create([
        'enable_external_team_sync' => true,
        'external_team_sheet_url' => 'https://docs.google.com/spreadsheets/d/18Bjq0JnI3Q8O2XQ669OjfMdRp8B_u6rmjsvA_dXrEpk/edit?usp=sharing',
    ]);
});

it('flags every sheet row and auto-tags confirmed, delivered and returned', function () {
    $confirmed = makeSheetDelivery($this->workspace, 'JT001');
    $delivered = makeSheetDelivery($this->workspace, 'JT002');
    $returned = makeSheetDelivery($this->workspace, 'JT003');
    $ringing = makeSheetDelivery($this->workspace, 'JT004');
    $notInSheet = makeSheetDelivery($this->workspace, 'JT005');

    $this->withToken($this->apiKey)
        ->postJson('/api/v1/public/rmo-orders/external-team', [
            'date' => now()->toDateString(),
            'rows' => [
                ['tracking_number' => 'JT001', 'status' => 'Confirmed'],
                ['tracking_number' => ' jt002 ', 'status' => 'Delivered'],
                ['tracking_number' => 'JT003', 'status' => 'Returned'],
                ['tracking_number' => 'JT004', 'status' => 'Ringing'],
                ['tracking_number' => 'JT999', 'status' => 'Confirmed'],
                ['tracking_number' => '', 'status' => null],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.received', 5)
        ->assertJsonPath('data.matched', 4)
        ->assertJsonPath('data.flagged', 4)
        ->assertJsonPath('data.tagged', 3)
        ->assertJsonPath('data.unmatched', ['JT999']);

    // A re-run still matches the row, but doesn't count it as freshly tagged.
    $this->withToken($this->apiKey)
        ->postJson('/api/v1/public/rmo-orders/external-team', [
            'rows' => [['tracking_number' => 'JT001', 'status' => 'Confirmed']],
        ])
        ->assertOk()
        ->assertJsonPath('data.matched', 1)
        ->assertJsonPath('data.tagged', 0)
        ->assertJsonMissingPath('data.synced');

    // Untouched settings tag the default rider statuses by name.
    $rider = $this->workspace->rmoRiderStatuses()->pluck('id', 'name');

    expect($confirmed->fresh()->rider_status_id)->toBe($rider['RIDER OTW'])
        ->and($delivered->fresh()->rider_status_id)->toBe($rider['DELIVERED'])
        ->and($returned->fresh()->rider_status_id)->toBe($rider['RETURNING'])
        ->and($ringing->fresh()->rider_status_id)->toBeNull();

    expect($confirmed->fresh())->status->toBe('RIDER OTW')->rmo_by_external_team->toBeTrue()
        ->and($delivered->fresh())->status->toBe('DELIVERED')->rmo_by_external_team->toBeTrue()
        ->and($returned->fresh())->status->toBe('RETURNING')->rmo_by_external_team->toBeTrue()
        ->and($ringing->fresh())->status->toBe('PENDING')->rmo_by_external_team->toBeTrue()
        ->and($notInSheet->fresh())->status->toBe('PENDING')->rmo_by_external_team->toBeFalse();
});

it('tags the customer / rider status picked in settings, or none', function () {
    $cxCancelled = $this->workspace->rmoCxStatuses()->where('name', 'CANCELLED')->value('id');
    $this->workspace->rmoSetting->update(['external_team_status_map' => [
        'confirmed' => '',
        'delivered' => 'rider:'.$this->workspace->rmoRiderStatuses()->where('name', 'DELIVERED')->value('id'),
        'returned' => "cx:{$cxCancelled}",
    ]]);

    $confirmed = makeSheetDelivery($this->workspace, 'JT001');
    $returned = makeSheetDelivery($this->workspace, 'JT003');

    $this->withToken($this->apiKey)
        ->postJson('/api/v1/public/rmo-orders/external-team', [
            'rows' => [
                ['tracking_number' => 'JT001', 'status' => 'Confirmed'],
                ['tracking_number' => 'JT003', 'status' => 'Returned'],
            ],
        ])
        ->assertOk();

    // The main status is still tagged either way.
    expect($confirmed->fresh())->status->toBe('RIDER OTW')->rider_status_id->toBeNull()->cx_status_id->toBeNull()
        ->and($returned->fresh())->status->toBe('RETURNING')->cx_status_id->toBe($cxCancelled)->rider_status_id->toBeNull();
});

it('only touches rows on the given delivery date and workspace', function () {
    $yesterday = makeSheetDelivery($this->workspace, 'JT001', now()->subDay()->toDateString());
    $otherWorkspace = makeSheetDelivery(makeWorkspaceWithOwner()['workspace'], 'JT001');

    $this->withToken($this->apiKey)
        ->postJson('/api/v1/public/rmo-orders/external-team', [
            'rows' => [['tracking_number' => 'JT001', 'status' => 'Confirmed']],
        ])
        ->assertOk()
        ->assertJsonPath('data.matched', 0);

    expect($yesterday->fresh()->rmo_by_external_team)->toBeFalse()
        ->and($otherWorkspace->fresh()->rmo_by_external_team)->toBeFalse();
});

it('rejects the callback while external team sync is off', function () {
    $this->workspace->rmoSetting->update(['enable_external_team_sync' => false]);

    $this->withToken($this->apiKey)
        ->postJson('/api/v1/public/rmo-orders/external-team', ['rows' => []])
        ->assertForbidden();
});

it('rejects a callback without an API key', function () {
    $this->postJson('/api/v1/public/rmo-orders/external-team', ['rows' => []])
        ->assertUnauthorized();
});

it('posts the workspace and date to the n8n webhook', function () {
    Http::fake(['n8n.test/*' => Http::response(['ok' => true])]);

    $this->artisan('rmo:trigger-external-team-sync', [
        '--workspace' => [$this->workspace->slug],
        '--date' => '2026-10-01',
        '--webhook' => 'https://n8n.test/webhook/rmo',
    ])->assertSuccessful();

    Http::assertSent(fn ($request) => $request->url() === 'https://n8n.test/webhook/rmo'
        && $request['workspace_id'] === $this->workspace->id
        && $request['date'] === '2026-10-01'
        && $request['sheet_id'] === '18Bjq0JnI3Q8O2XQ669OjfMdRp8B_u6rmjsvA_dXrEpk'
        && str_starts_with($request['sheet_url'], 'https://docs.google.com/spreadsheets/d/')
        && str_ends_with($request['callback_url'], '/api/v1/public/rmo-orders/external-team')
        && ! isset($request['api_key']));
});

it('runs for every workspace with external team sync on when run by the scheduler', function () {
    Http::fake(['n8n.test/*' => Http::response(['ok' => true])]);

    $off = makeWorkspaceWithOwner()['workspace'];
    makeApiKey($off);
    $off->rmoSetting()->create(['enable_external_team_sync' => false, 'external_team_sheet_url' => 'https://docs.google.com/spreadsheets/d/18Bjq0JnI3Q8O2XQ669OjfMdRp8B_u6rmjsvA_dXrEpk/edit?usp=sharing']);

    $noLink = makeWorkspaceWithOwner()['workspace'];
    makeApiKey($noLink);
    $noLink->rmoSetting()->create(['enable_external_team_sync' => true]);

    $this->artisan('rmo:trigger-external-team-sync', ['--webhook' => 'https://n8n.test/webhook/rmo'])
        ->assertSuccessful();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['workspace_id'] === $this->workspace->id);
});

it('skips a named workspace that has external team sync off', function () {
    Http::fake();
    $this->workspace->rmoSetting->update(['enable_external_team_sync' => false]);

    $this->artisan('rmo:trigger-external-team-sync', [
        '--workspace' => [$this->workspace->id],
        '--webhook' => 'https://n8n.test/webhook/rmo',
    ])->assertFailed();

    Http::assertNothingSent();
});

it('does nothing when no workspace has external team sync on', function () {
    Http::fake();
    $this->workspace->rmoSetting->update(['enable_external_team_sync' => false]);

    $this->artisan('rmo:trigger-external-team-sync', ['--webhook' => 'https://n8n.test/webhook/rmo'])
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('is scheduled every ten minutes', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command ?? '', 'rmo:trigger-external-team-sync'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/10 * * * *');
});

it('fails without a webhook URL', function () {
    config(['services.n8n.rmo_external_team_webhook_url' => null]);

    $this->artisan('rmo:trigger-external-team-sync', ['--workspace' => [$this->workspace->id]])
        ->assertFailed();
});
