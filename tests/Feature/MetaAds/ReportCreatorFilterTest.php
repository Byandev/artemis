<?php

use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\MetaAds\Models\Report;

/**
 * The Ads Report builder persists the internal-creator filter inside the saved
 * report config, so reopening a report restores it. The rows themselves come
 * from AdsManagerController::data(), which is covered in AdCreatorControllerTest.
 */
function reportConfig(array $overrides = []): array
{
    return array_merge([
        'accounts' => [],
        'since' => '2026-07-01',
        'until' => '2026-07-07',
        'group_by' => 'ad',
        'metrics' => ['spend', 'clicks'],
        'sort' => '-spend',
        'filters' => [],
        'creator_id' => null,
        'chart' => 'gallery',
        'view' => ['cardSize' => 2, 'hideThumbnails' => false, 'itemsLoaded' => 12],
    ], $overrides);
}

it('stores a creator filter with a new report', function () {
    ['workspace' => $workspace, 'user' => $owner] = actingAsWorkspaceOwner();

    $this->post(route('workspaces.metaads.reports.store', $workspace), [
        'name' => 'Creator report',
        'description' => null,
        'kind' => 'top_performers',
        'config' => reportConfig(['creator_id' => $owner->id]),
    ])->assertRedirect();

    expect(Report::first()->config['creator_id'])->toBe($owner->id);
});

it('persists a creator filter when the report is updated', function () {
    ['workspace' => $workspace, 'user' => $owner] = actingAsWorkspaceOwner();

    $report = Report::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'name' => 'Report',
        'kind' => 'top_performers',
        'config' => reportConfig(),
    ]);

    $this->patch(route('workspaces.metaads.reports.update', [$workspace, $report]), [
        'name' => 'Report',
        'description' => null,
        'config' => reportConfig(['creator_id' => $owner->id]),
    ])->assertRedirect();

    expect($report->fresh()->config['creator_id'])->toBe($owner->id);
});

it('persists the unassigned creator filter', function () {
    ['workspace' => $workspace, 'user' => $owner] = actingAsWorkspaceOwner();

    $report = Report::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'name' => 'Report',
        'kind' => 'top_performers',
        'config' => reportConfig(),
    ]);

    $this->patch(route('workspaces.metaads.reports.update', [$workspace, $report]), [
        'name' => 'Report',
        'description' => null,
        'config' => reportConfig(['creator_id' => 'unassigned']),
    ])->assertRedirect();

    expect($report->fresh()->config['creator_id'])->toBe('unassigned');
});

it('returns the saved creator filter and assignable members to the builder', function () {
    ['workspace' => $workspace, 'user' => $owner] = actingAsWorkspaceOwner();

    $report = Report::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'name' => 'Report',
        'kind' => 'top_performers',
        'config' => reportConfig(['creator_id' => $owner->id]),
    ]);

    $this->get(route('workspaces.metaads.reports.show', [$workspace, $report]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspaces/integrations/meta-ads/reports/show')
            ->where('report.config.creator_id', $owner->id)
            ->has('members')
        );
});

it('reports from another workspace are not reachable', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $other = Workspace::factory()->create();

    $report = Report::create([
        'workspace_id' => $other->id,
        'user_id' => $other->owner_id,
        'name' => 'Foreign',
        'kind' => 'top_performers',
        'config' => reportConfig(),
    ]);

    $this->get(route('workspaces.metaads.reports.show', [$workspace, $report]))
        ->assertNotFound();
});
