<?php

use App\Models\SalesTarget;
use App\Models\Team;
use App\Models\Workspace;

/**
 * A target's date is the one field a user can put a value in that the `date`
 * column will not take — the form's picker header year is a plain number input.
 * These cover it coming back as a message on the field rather than a failed
 * insert.
 */

/** A workspace with the S&M dashboard on, its owner, and a team to target. */
function salesTargetContext(): array
{
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->update(['sales_marketing_dashboard_module_enabled' => true]);

    return [
        'owner' => $owner,
        'workspace' => $workspace,
        'team' => Team::factory()->create(['workspace_id' => $workspace->id]),
    ];
}

/** The store payload, with the date swapped for whatever is under test. */
function salesTargetPayload(Team $team, mixed $date): array
{
    return [
        'date' => $date,
        'name' => '7:7',
        'target_roas' => '5.00',
        'teams' => [
            ['team_id' => $team->id, 'sales_target' => '10000', 'ad_budget' => '2000'],
        ],
    ];
}

test('a valid date creates the target', function () {
    ['owner' => $owner, 'workspace' => $workspace, 'team' => $team] = salesTargetContext();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/sales-marketing/dashboard/sales-targets")
        ->post("/workspaces/{$workspace->slug}/sales-marketing/dashboard/sales-targets", salesTargetPayload($team, '2026-08-11'))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(SalesTarget::where('workspace_id', $workspace->id)->count())->toBe(1);
});

test('an out-of-range year is a field error, not a failed insert', function (string $date) {
    ['owner' => $owner, 'workspace' => $workspace, 'team' => $team] = salesTargetContext();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/sales-marketing/dashboard/sales-targets")
        ->post("/workspaces/{$workspace->slug}/sales-marketing/dashboard/sales-targets", salesTargetPayload($team, $date))
        ->assertRedirect()
        ->assertSessionHasErrors('date');

    expect(SalesTarget::count())->toBe(0);
})->with([
    'five-digit year' => '20265-08-11',
    'year zero' => '0000-08-11',
    'before the window' => '1999-12-31',
    'after the window' => '2101-01-01',
]);

test('a date the calendar does not have is rejected', function (mixed $date) {
    ['owner' => $owner, 'workspace' => $workspace, 'team' => $team] = salesTargetContext();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/sales-marketing/dashboard/sales-targets")
        ->post("/workspaces/{$workspace->slug}/sales-marketing/dashboard/sales-targets", salesTargetPayload($team, $date))
        ->assertRedirect()
        ->assertSessionHasErrors('date');

    expect(SalesTarget::count())->toBe(0);
})->with([
    'february 30th' => '2026-02-30',
    'month 13' => '2026-13-01',
    'not a date at all' => 'tomorrow',
    'empty' => '',
    'missing' => null,
]);

test('the same date twice is rejected with a message on the field', function () {
    ['owner' => $owner, 'workspace' => $workspace, 'team' => $team] = salesTargetContext();

    $url = "/workspaces/{$workspace->slug}/sales-marketing/dashboard/sales-targets";

    $this->actingAs($owner)->from($url)->post($url, salesTargetPayload($team, '2026-08-11'));

    $this->actingAs($owner)
        ->from($url)
        ->post($url, salesTargetPayload($team, '2026-08-11'))
        ->assertSessionHasErrors(['date' => 'A sales target already exists for this date.']);

    expect(SalesTarget::count())->toBe(1);
});

test('a workspace can hold the same date as another workspace', function () {
    ['owner' => $owner, 'workspace' => $workspace, 'team' => $team] = salesTargetContext();
    ['owner' => $otherOwner, 'workspace' => $other, 'team' => $otherTeam] = salesTargetContext();

    foreach ([[$owner, $workspace, $team], [$otherOwner, $other, $otherTeam]] as [$user, $ws, $t]) {
        $url = "/workspaces/{$ws->slug}/sales-marketing/dashboard/sales-targets";

        $this->actingAs($user)
            ->from($url)
            ->post($url, salesTargetPayload($t, '2026-08-11'))
            ->assertSessionHasNoErrors();
    }

    expect(SalesTarget::count())->toBe(2);
});

test('editing a target without moving its date is not a clash with itself', function () {
    ['owner' => $owner, 'workspace' => $workspace, 'team' => $team] = salesTargetContext();

    $base = "/workspaces/{$workspace->slug}/sales-marketing/dashboard/sales-targets";

    $this->actingAs($owner)->from($base)->post($base, salesTargetPayload($team, '2026-08-11'));

    $target = SalesTarget::firstOrFail();

    $this->actingAs($owner)
        ->from($base)
        ->put("{$base}/{$target->id}", [...salesTargetPayload($team, '2026-08-11'), 'name' => '8:8'])
        ->assertSessionHasNoErrors();

    expect($target->refresh()->name)->toBe('8:8');
});

test('an out-of-range date is rejected on update too', function () {
    ['owner' => $owner, 'workspace' => $workspace, 'team' => $team] = salesTargetContext();

    $base = "/workspaces/{$workspace->slug}/sales-marketing/dashboard/sales-targets";

    $this->actingAs($owner)->from($base)->post($base, salesTargetPayload($team, '2026-08-11'));

    $target = SalesTarget::firstOrFail();

    $this->actingAs($owner)
        ->from($base)
        ->put("{$base}/{$target->id}", salesTargetPayload($team, '20265-08-11'))
        ->assertSessionHasErrors('date');

    expect($target->refresh()->date->toDateString())->toBe('2026-08-11');
});

test('deleting from a target detail page lands on the list, not the deleted page', function () {
    ['owner' => $owner, 'workspace' => $workspace, 'team' => $team] = salesTargetContext();

    $base = "/workspaces/{$workspace->slug}/sales-marketing/dashboard/sales-targets";

    $this->actingAs($owner)->from($base)->post($base, salesTargetPayload($team, '2026-08-11'));

    $target = SalesTarget::firstOrFail();

    $this->actingAs($owner)
        ->from("{$base}/{$target->id}")
        ->delete("{$base}/{$target->id}")
        ->assertRedirect($base);

    expect(SalesTarget::count())->toBe(0);
});

test('deleting from the list stays on the list page it was fired from', function () {
    ['owner' => $owner, 'workspace' => $workspace, 'team' => $team] = salesTargetContext();

    $base = "/workspaces/{$workspace->slug}/sales-marketing/dashboard/sales-targets";

    $this->actingAs($owner)->from($base)->post($base, salesTargetPayload($team, '2026-08-11'));

    $target = SalesTarget::firstOrFail();

    // The page the row was on is worth keeping — it still exists.
    $this->actingAs($owner)
        ->from("{$base}?page=2")
        ->delete("{$base}/{$target->id}")
        ->assertRedirect("{$base}?page=2");
});

test('a negative amount is rejected rather than stored', function () {
    ['owner' => $owner, 'workspace' => $workspace, 'team' => $team] = salesTargetContext();

    $url = "/workspaces/{$workspace->slug}/sales-marketing/dashboard/sales-targets";

    $this->actingAs($owner)
        ->from($url)
        ->post($url, [
            ...salesTargetPayload($team, '2026-08-11'),
            'teams' => [
                ['team_id' => $team->id, 'sales_target' => '-5000', 'ad_budget' => '2000'],
            ],
        ])
        ->assertSessionHasErrors('teams.0.sales_target');

    expect(SalesTarget::count())->toBe(0);
});
