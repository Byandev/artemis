<?php

use App\Models\Page;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Botcake\Jobs\FetchFlowStatistics;
use Modules\Botcake\Jobs\FetchSequenceStatistics;
use Modules\Botcake\Models\Flow;
use Modules\Botcake\Models\Sequence;

function makeBotcakePage(): Page
{
    $workspace = Workspace::factory()->create();

    return Page::factory()->forWorkspace($workspace)->create([
        'botcake_token' => 'test-token',
    ]);
}

it('does not write a flow daily stat on the first run (no baseline) but baselines cumulative', function () {
    $page = makeBotcakePage();

    $flow = Flow::query()->create([
        'id' => 1001,
        'page_id' => $page->id,
        'parent_id' => null,
        'is_removed' => false,
        'name' => 'Welcome Flow',
    ]);

    Http::fake([
        "https://botcake.io/api/public_api/v1/pages/{$page->id}/flows/{$flow->id}/statistics" => Http::response([
            'data' => [
                'delivery' => 10,
                'is_clicked' => 2,
                'seen' => 8,
                'sent' => 12,
                'total_phone_number' => 7,
            ],
        ], 200),
    ]);

    (new FetchFlowStatistics($flow))->handle();

    expect(DB::table('botcake_flow_daily_stats')->where('flow_id', $flow->id)->exists())->toBeFalse();

    $flow->refresh();
    expect((int) $flow->delivery)->toBe(10)
        ->and((int) $flow->is_clicked)->toBe(2)
        ->and((int) $flow->seen)->toBe(8)
        ->and((int) $flow->sent)->toBe(12)
        ->and((int) $flow->total_phone_number)->toBe(7);
});

it('writes the delta to today on a subsequent flow stats run', function () {
    $page = makeBotcakePage();

    $flow = Flow::query()->create([
        'id' => 1002,
        'page_id' => $page->id,
        'parent_id' => null,
        'is_removed' => false,
        'name' => 'Welcome Flow',
        'delivery' => 10,
        'is_clicked' => 2,
        'seen' => 8,
        'sent' => 12,
        'total_phone_number' => 7,
    ]);

    Http::fake([
        "https://botcake.io/api/public_api/v1/pages/{$page->id}/flows/{$flow->id}/statistics" => Http::response([
            'data' => [
                'delivery' => 15,
                'is_clicked' => 5,
                'seen' => 10,
                'sent' => 18,
                'total_phone_number' => 9,
            ],
        ], 200),
    ]);

    (new FetchFlowStatistics($flow))->handle();

    expect(DB::table('botcake_flow_daily_stats')
        ->where('flow_id', $flow->id)
        ->where('date', now()->toDateString())
        ->where('delivery', 5)
        ->where('is_clicked', 3)
        ->where('seen', 2)
        ->where('sent', 6)
        ->where('total_phone_number', 2)
        ->exists())->toBeTrue();
});

it('updates same day sequence snapshots without creating duplicates after baseline', function () {
    $page = makeBotcakePage();

    $sequence = Sequence::query()->create([
        'id' => 2001,
        'page_id' => $page->id,
        'name' => 'Abandoned Cart',
    ]);

    Http::fake([
        "https://botcake.io/api/public_api/v1/pages/{$page->id}/sequences/{$sequence->id}/statistics" => Http::sequence()
            ->push([
                'data' => [
                    [
                        'message_id' => 501,
                        'name' => 'Step 1',
                        'delivery' => 10,
                        'seen' => 9,
                        'sends' => 11,
                        'total_phone_number' => 8,
                    ],
                ],
            ], 200)
            ->push([
                'data' => [
                    [
                        'message_id' => 501,
                        'name' => 'Step 1',
                        'delivery' => 15,
                        'seen' => 13,
                        'sends' => 16,
                        'total_phone_number' => 12,
                    ],
                ],
            ], 200)
            ->push([
                'data' => [
                    [
                        'message_id' => 501,
                        'name' => 'Step 1',
                        'delivery' => 20,
                        'seen' => 16,
                        'sends' => 22,
                        'total_phone_number' => 15,
                    ],
                ],
            ], 200),
    ]);

    // First call baselines, no daily stats yet.
    (new FetchSequenceStatistics($sequence))->handle();
    expect(DB::table('botcake_sequence_message_daily_stats')->count())->toBe(0);

    // Second call: delta = 15-10 = 5, etc.
    (new FetchSequenceStatistics($sequence))->handle();
    // Third call: same-day delta should accumulate, not duplicate.
    (new FetchSequenceStatistics($sequence))->handle();

    expect(DB::table('botcake_sequence_daily_stats')->count())->toBe(1);
    expect(DB::table('botcake_sequence_message_daily_stats')->count())->toBe(1);

    expect(DB::table('botcake_sequence_message_daily_stats')
        ->where('date', now()->toDateString())
        ->where('delivery', 10)   // (15-10) + (20-15)
        ->where('seen', 7)         // (13-9)  + (16-13)
        ->where('sent', 11)        // (16-11) + (22-16)
        ->where('total_phone_number', 7) // (12-8) + (15-12)
        ->exists())->toBeTrue();
});

it('rolls per-message deltas up into the sequence daily total', function () {
    $page = makeBotcakePage();

    $sequence = Sequence::query()->create([
        'id' => 3001,
        'page_id' => $page->id,
        'name' => 'Reactivation',
    ]);

    Http::fake([
        "https://botcake.io/api/public_api/v1/pages/{$page->id}/sequences/{$sequence->id}/statistics" => Http::sequence()
            ->push([
                'data' => [
                    ['message_id' => 601, 'name' => 'A', 'delivery' => 10, 'seen' => 8, 'sends' => 12, 'total_phone_number' => 7],
                    ['message_id' => 602, 'name' => 'B', 'delivery' => 20, 'seen' => 18, 'sends' => 25, 'total_phone_number' => 14],
                ],
            ], 200)
            ->push([
                'data' => [
                    ['message_id' => 601, 'name' => 'A', 'delivery' => 20, 'seen' => 17, 'sends' => 22, 'total_phone_number' => 14],
                    ['message_id' => 602, 'name' => 'B', 'delivery' => 30, 'seen' => 28, 'sends' => 35, 'total_phone_number' => 19],
                ],
            ], 200),
    ]);

    // Baseline.
    (new FetchSequenceStatistics($sequence))->handle();
    // Apply deltas.
    (new FetchSequenceStatistics($sequence))->handle();

    // Per-message deltas: A=(10,9,10,7), B=(10,10,10,5)
    // Sequence total:    delivery=20, seen=19, sent=20, total_phone_number=12
    expect(DB::table('botcake_sequence_message_daily_stats')->count())->toBe(2);

    expect(DB::table('botcake_sequence_daily_stats')
        ->where('sequence_id', $sequence->id)
        ->where('date', now()->toDateString())
        ->where('delivery', 20)
        ->where('seen', 19)
        ->where('sent', 20)
        ->where('total_phone_number', 12)
        ->exists())->toBeTrue();
});
