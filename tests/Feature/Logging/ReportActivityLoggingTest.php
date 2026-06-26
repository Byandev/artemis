<?php

use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\MetaAds\Models\Report;

uses(RefreshDatabase::class);

test('deleting a saved report logs the report title, not a generic label', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $report = Report::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'name' => 'Q3 Sales Report',
        'config' => ['group_by' => 'campaign'],
    ]);

    $this->actingAs($owner);
    $report->delete();

    $log = ActivityLog::query()->where('action_type', 'report.deleted')->first();

    expect($log)->not->toBeNull()
        ->and($log->workspace_id)->toBe($workspace->id)
        // The specific report's title, not the mangled generic "RePORTS".
        ->and($log->message)->toContain('Report "Q3 Sales Report"')
        ->and($log->message)->not->toContain('RePORTS');
});

test('creating a saved report logs the report title', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner);

    Report::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'name' => 'Weekly Spend',
        'config' => ['group_by' => 'ad'],
    ]);

    $log = ActivityLog::query()->where('action_type', 'report.created')->first();

    expect($log)->not->toBeNull()
        ->and($log->message)->toContain('Report "Weekly Spend"');
});
