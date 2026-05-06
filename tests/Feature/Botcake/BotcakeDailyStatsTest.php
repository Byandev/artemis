<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\Botcake\Jobs\FetchFlowStatistics;
use Modules\Botcake\Jobs\FetchSequenceStatistics;
use Modules\Botcake\Models\Flow;
use Modules\Botcake\Models\Sequence;

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');

    DB::purge('sqlite');
    DB::reconnect('sqlite');

    Schema::create('pages', function (Blueprint $table) {
        $table->id();
        $table->string('botcake_token')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('botcake_flows', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('page_id');
        $table->unsignedBigInteger('flow_id');
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->boolean('is_removed')->default(false);
        $table->unsignedBigInteger('delivery')->default(0);
        $table->unsignedBigInteger('is_clicked')->default(0);
        $table->unsignedBigInteger('seen')->default(0);
        $table->unsignedBigInteger('sent')->default(0);
        $table->unsignedBigInteger('total_phone_number')->default(0);
        $table->string('name');
        $table->timestamps();

        $table->unique(['page_id', 'flow_id']);
    });

    Schema::create('botcake_sequences', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('page_id');
        $table->unsignedBigInteger('sequence_id');
        $table->string('name');
        $table->timestamps();

        $table->unique(['page_id', 'sequence_id']);
    });

    Schema::create('botcake_sequence_messages', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedBigInteger('sequence_id');
        $table->unsignedBigInteger('message_id')->nullable();
        $table->unsignedBigInteger('delivery')->default(0);
        $table->unsignedBigInteger('is_clicked')->default(0);
        $table->unsignedBigInteger('seen')->default(0);
        $table->unsignedBigInteger('sent')->default(0);
        $table->unsignedBigInteger('total_phone_number')->default(0);
        $table->timestamps();

        $table->unique(['sequence_id', 'message_id']);
    });

    Schema::create('botcake_flow_daily_stats', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('flow_id');
        $table->date('date');
        $table->unsignedBigInteger('delivery')->default(0);
        $table->unsignedBigInteger('is_clicked')->default(0);
        $table->unsignedBigInteger('seen')->default(0);
        $table->unsignedBigInteger('sent')->default(0);
        $table->unsignedBigInteger('total_phone_number')->default(0);
        $table->timestamps();

        $table->unique(['flow_id', 'date']);
        $table->index('date');
    });

    Schema::create('botcake_sequence_daily_stats', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('sequence_id');
        $table->date('date');
        $table->unsignedBigInteger('delivery')->default(0);
        $table->unsignedBigInteger('seen')->default(0);
        $table->unsignedBigInteger('sent')->default(0);
        $table->unsignedBigInteger('total_phone_number')->default(0);
        $table->timestamps();

        $table->unique(['sequence_id', 'date']);
        $table->index('date');
    });

    Schema::create('botcake_sequence_message_daily_stats', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('sequence_message_id');
        $table->date('date');
        $table->unsignedBigInteger('delivery')->default(0);
        $table->unsignedBigInteger('seen')->default(0);
        $table->unsignedBigInteger('sent')->default(0);
        $table->unsignedBigInteger('total_phone_number')->default(0);
        $table->timestamps();

        $table->unique(['sequence_message_id', 'date']);
        $table->index('date');
    });
});

it('creates a flow daily snapshot on first run', function () {
    $pageId = DB::table('pages')->insertGetId([
        'botcake_token' => 'test-token',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $flow = Flow::query()->create([
        'page_id' => $pageId,
        'flow_id' => 1001,
        'parent_id' => null,
        'is_removed' => false,
        'name' => 'Welcome Flow',
    ]);

    Http::fake([
        "https://botcake.io/api/public_api/v1/pages/{$pageId}/flows/{$flow->flow_id}/statistics" => Http::response([
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

    expect(DB::table('botcake_flow_daily_stats')
        ->where('flow_id', $flow->id)
        ->where('date', now()->toDateString())
        ->where('delivery', 10)
        ->where('is_clicked', 2)
        ->where('seen', 8)
        ->where('sent', 12)
        ->where('total_phone_number', 7)
        ->exists())->toBeTrue();
});

it('updates same day sequence snapshots without creating duplicates', function () {
    $pageId = DB::table('pages')->insertGetId([
        'botcake_token' => 'test-token',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $sequence = Sequence::query()->create([
        'page_id' => $pageId,
        'sequence_id' => 2001,
        'name' => 'Abandoned Cart',
    ]);

    Http::fake([
        "https://botcake.io/api/public_api/v1/pages/{$pageId}/sequences/{$sequence->sequence_id}/statistics" => Http::sequence()
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
            ], 200),
    ]);

    (new FetchSequenceStatistics($sequence))->handle();
    (new FetchSequenceStatistics($sequence))->handle();

    expect(DB::table('botcake_sequence_daily_stats')->count())->toBe(1);
    expect(DB::table('botcake_sequence_message_daily_stats')->count())->toBe(1);

    expect(DB::table('botcake_sequence_message_daily_stats')
        ->where('date', now()->toDateString())
        ->where('delivery', 15)
        ->where('seen', 13)
        ->where('sent', 16)
        ->where('total_phone_number', 12)
        ->exists())->toBeTrue();

    expect(DB::table('botcake_sequence_daily_stats')
        ->where('sequence_id', $sequence->id)
        ->where('date', now()->toDateString())
        ->where('delivery', 15)
        ->where('seen', 13)
        ->where('sent', 16)
        ->where('total_phone_number', 12)
        ->exists())->toBeTrue();
});

it('stores sequence daily totals as sum of message snapshots', function () {
    $pageId = DB::table('pages')->insertGetId([
        'botcake_token' => 'test-token',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $sequence = Sequence::query()->create([
        'page_id' => $pageId,
        'sequence_id' => 3001,
        'name' => 'Reactivation',
    ]);

    Http::fake([
        "https://botcake.io/api/public_api/v1/pages/{$pageId}/sequences/{$sequence->sequence_id}/statistics" => Http::response([
            'data' => [
                [
                    'message_id' => 601,
                    'name' => 'Step A',
                    'delivery' => 20,
                    'seen' => 17,
                    'sends' => 22,
                    'total_phone_number' => 14,
                ],
                [
                    'message_id' => 602,
                    'name' => 'Step B',
                    'delivery' => 30,
                    'seen' => 28,
                    'sends' => 35,
                    'total_phone_number' => 19,
                ],
            ],
        ], 200),
    ]);

    (new FetchSequenceStatistics($sequence))->handle();

    expect(DB::table('botcake_sequence_message_daily_stats')->count())->toBe(2);

    expect(DB::table('botcake_sequence_daily_stats')
        ->where('sequence_id', $sequence->id)
        ->where('date', now()->toDateString())
        ->where('delivery', 50)
        ->where('seen', 45)
        ->where('sent', 57)
        ->where('total_phone_number', 33)
        ->exists())->toBeTrue();
});
