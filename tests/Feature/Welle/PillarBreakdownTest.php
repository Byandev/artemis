<?php

use App\Enums\Permission;
use App\Models\User;
use App\Models\WelleDailyRecord;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| Pillar Breakdown
|--------------------------------------------------------------------------
|
| The three pillars side by side, each bar the same month of recorded days —
| so the three counts come back together rather than a bar at a time, which is
| what keeps two bars from being drawn over different months.
|
| The access rules are the page's, re-applied: an endpoint under /api can be
| reached without going through the page that shows it.
*/

beforeEach(function () {
    ['user' => $this->owner, 'workspace' => $this->workspace] = makeWorkspaceWithOwner();

    $this->workspace->update(['welle_module_enabled' => true]);

    $this->url = route('api.workspaces.welle.stats.pillar-breakdown', [
        'workspace' => $this->workspace->slug,
    ]);
});

it('counts every pillar over the same month of days', function () {
    // Sixteen recorded days: seven full ESC days, six more with movement only,
    // and three with nothing — thirteen movement days to seven of each other.
    foreach (range(1, 16) as $day) {
        welleDay($this->workspace->id, $this->owner->id, $day, $day <= 7, ['movement' => $day <= 13]);
    }

    $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->assertJson([
            'total_days' => 16,
            'month' => Carbon::today()->format('Y-m'),
            'month_label' => Carbon::today()->format('F Y'),
            'connected' => false,
            'pillars' => [
                ['pillar' => 'movement', 'days' => 13, 'rate' => 81.25],
                ['pillar' => 'meditation', 'days' => 7, 'rate' => 43.75],
                ['pillar' => 'learning', 'days' => 7, 'rate' => 43.75],
            ],
        ]);
});

it('draws the bars in the order welle lists the pillars', function () {
    welleDay($this->workspace->id, $this->owner->id, 1, true);

    $pillars = $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->json('pillars');

    expect(array_column($pillars, 'pillar'))->toBe(WelleDailyRecord::PILLARS);
});

it('counts a pillar day that was not an esc day', function () {
    welleDay($this->workspace->id, $this->owner->id, 1, false, ['movement' => true]);
    welleDay($this->workspace->id, $this->owner->id, 2, false, ['movement' => false]);

    $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->assertJson([
            'total_days' => 2,
            'pillars' => [
                ['pillar' => 'movement', 'days' => 1, 'rate' => 50],
                ['pillar' => 'meditation', 'days' => 0, 'rate' => 0],
                ['pillar' => 'learning', 'days' => 0, 'rate' => 0],
            ],
        ]);
});

it('reads only the signed-in user rows, in this workspace, in this month', function () {
    $other = makeWorkspaceMember($this->workspace);
    ['workspace' => $otherWorkspace] = makeWorkspaceWithOwner();

    welleDay($this->workspace->id, $this->owner->id, 1, true);

    // Someone else's day, the same day in another workspace, and a day of the
    // month before — none of them belong in these bars.
    welleDay($this->workspace->id, $other->id, 2, true);
    welleDay($otherWorkspace->id, $this->owner->id, 2, true);

    WelleDailyRecord::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
        'date' => Carbon::today()->startOfMonth()->subDay()->toDateString(),
        'movement' => true,
        'pillars_completed' => 1,
    ]);

    $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->assertJson([
            'total_days' => 1,
            'pillars' => [['pillar' => 'movement', 'days' => 1, 'rate' => 100]],
        ]);
});

it('reads the month asked for', function () {
    $lastMonth = Carbon::today()->startOfMonth()->subMonth();

    WelleDailyRecord::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
        'date' => $lastMonth->toDateString(),
        'meditation' => true,
        'pillars_completed' => 1,
    ]);

    $this->actingAs($this->owner)
        ->getJson($this->url.'?month='.$lastMonth->format('Y-m'))
        ->assertOk()
        ->assertJson([
            'month' => $lastMonth->format('Y-m'),
            'total_days' => 1,
            'pillars' => [
                ['pillar' => 'movement', 'days' => 0, 'rate' => 0],
                ['pillar' => 'meditation', 'days' => 1, 'rate' => 100],
            ],
        ]);
});

it('has no rates rather than rates of none while nothing is synced', function () {
    $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->assertJson([
            'total_days' => 0,
            'pillars' => [
                ['pillar' => 'movement', 'days' => 0, 'rate' => null],
                ['pillar' => 'meditation', 'days' => 0, 'rate' => null],
                ['pillar' => 'learning', 'days' => 0, 'rate' => null],
            ],
        ]);
});

it('says whether a welle account is connected at all', function () {
    connectWelleAccount($this->owner);

    $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->assertJson(['connected' => true]);
});

it('answers a member holding the grant', function () {
    $member = makeMemberWithPermissions($this->workspace, [Permission::ViewMyEsc->value], 'Welle');

    welleDay($this->workspace->id, $member->id, 1, true);

    $this->actingAs($member)
        ->getJson($this->url)
        ->assertOk()
        ->assertJson([
            'total_days' => 1,
            'pillars' => [['pillar' => 'movement', 'days' => 1, 'rate' => 100]],
        ]);
});

it('is gone while the welle module is switched off', function () {
    $this->workspace->update(['welle_module_enabled' => false]);

    $this->actingAs($this->owner)->getJson($this->url)->assertNotFound();
});

it('forbids a member without the grant', function () {
    $member = makeWorkspaceMember($this->workspace);

    $this->actingAs($member)->getJson($this->url)->assertForbidden();
});

it('forbids someone who is not in the workspace', function () {
    $this->actingAs(User::factory()->create())->getJson($this->url)->assertForbidden();
});

it('turns a guest away', function () {
    $this->getJson($this->url)->assertUnauthorized();
});
