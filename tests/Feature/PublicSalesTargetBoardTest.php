<?php

use App\Models\Page;
use App\Models\SalesTarget;
use App\Models\SalesTargetTeam;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use App\Support\PublicWorkspaceGate;
use Illuminate\Support\Facades\DB;

/**
 * The public gameboard's day selection: it lands on today's target by default,
 * and a target id in the path — the link on a target's detail page — pins it to
 * that one instead, which is what makes several targets viewable one at a time.
 */

/** A workspace with the public gate already unlocked for this session. */
function unlockedWorkspace(): Workspace
{
    $workspace = Workspace::factory()->create(['public_password' => bcrypt('secret')]);

    session([PublicWorkspaceGate::sessionKey($workspace) => $workspace->publicPasswordFingerprint()]);

    return $workspace;
}

/** A dated target carrying one team's amount, so the board has something to score. */
function targetOn(Workspace $workspace, string $date, string $name): SalesTarget
{
    $target = SalesTarget::create([
        'workspace_id' => $workspace->id,
        'date' => $date,
        'name' => $name,
        'target_roas' => 5,
    ]);

    SalesTargetTeam::create([
        'sales_target_id' => $target->id,
        'team_id' => Team::factory()->create(['workspace_id' => $workspace->id])->id,
        'sales_target' => 10000,
        'ad_budget' => 2000,
    ]);

    return $target;
}

/**
 * A team whose sales land through a page it owns — the pages.owner_id → team_user
 * link the board attributes orders with. Returns the team.
 */
function teamSelling(Workspace $workspace, float $sales, string $date, string $orderNumber): Team
{
    $owner = User::factory()->create();
    $team = Team::factory()->create(['workspace_id' => $workspace->id]);
    $team->members()->attach($owner->id);

    $page = Page::factory()->forWorkspace($workspace)->forOwner($owner)->create();

    DB::table('pancake_orders')->insert([
        'order_number' => $orderNumber,
        'status' => 1,
        'status_name' => 'new',
        'shop_id' => $page->shop_id,
        'page_id' => $page->id,
        'workspace_id' => $workspace->id,
        'customer_id' => fake()->uuid(),
        'final_amount' => $sales,
        'inserted_at' => $date.' 09:00:00',
        'confirmed_at' => $date.' 09:00:00',
    ]);

    return $team;
}

/** A target covering exactly the given teams, each with the same goal. */
function targetIncluding(Workspace $workspace, string $date, array $teams, float $goal = 1000): SalesTarget
{
    $target = SalesTarget::create([
        'workspace_id' => $workspace->id,
        'date' => $date,
        'name' => 'Board',
        'target_roas' => 5,
    ]);

    foreach ($teams as $team) {
        SalesTargetTeam::create([
            'sales_target_id' => $target->id,
            'team_id' => $team->id,
            'sales_target' => $goal,
            'ad_budget' => 100,
        ]);
    }

    return $target;
}

it('defaults to today\'s target and reports no pin', function () {
    $workspace = unlockedWorkspace();
    targetOn($workspace, now()->subDays(3)->toDateString(), 'Older');
    $today = targetOn($workspace, now()->toDateString(), 'Today');

    $this->get("/public/workspaces/{$workspace->slug}/sales-targets")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('featured.id', $today->id)
            // Null so a board left up overnight rolls onto the new day.
            ->where('targetId', null));
});

it('pins the board to a target asked for by id', function () {
    $workspace = unlockedWorkspace();
    targetOn($workspace, now()->toDateString(), 'Today');
    $older = targetOn($workspace, now()->subDays(3)->toDateString(), 'Older');

    $this->get("/public/workspaces/{$workspace->slug}/sales-targets/{$older->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('featured.id', $older->id)
            ->where('featured.name', 'Older')
            ->where('targetId', $older->id));
});

it('lets each of several targets be viewed one at a time', function () {
    $workspace = unlockedWorkspace();

    $targets = collect(range(1, 4))->map(fn ($i) => targetOn(
        $workspace,
        now()->subDays($i)->toDateString(),
        "Target {$i}",
    ));

    foreach ($targets as $i => $target) {
        $this->get("/public/workspaces/{$workspace->slug}/sales-targets/{$target->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('featured.id', $target->id)
                ->where('featured.name', 'Target '.($i + 1))
                ->where('featured.date', $target->date->toDateString()));
    }
});

it('scores the section endpoints on the pinned target, not today\'s', function () {
    $workspace = unlockedWorkspace();
    targetOn($workspace, now()->toDateString(), 'Today');
    $older = targetOn($workspace, now()->subDays(3)->toDateString(), 'Older');

    $this->getJson("/public/workspaces/{$workspace->slug}/sales-targets/kpis?id={$older->id}")
        ->assertOk()
        ->assertJsonPath('data.date', $older->date->toDateString());

    $this->getJson("/public/workspaces/{$workspace->slug}/sales-targets/kpis")
        ->assertOk()
        ->assertJsonPath('data.date', now()->toDateString());
});

it('falls back to the current board for another workspace\'s target id', function () {
    $workspace = unlockedWorkspace();
    $today = targetOn($workspace, now()->toDateString(), 'Today');

    $foreign = targetOn(Workspace::factory()->create(), now()->subDay()->toDateString(), 'Theirs');

    $this->get("/public/workspaces/{$workspace->slug}/sales-targets/{$foreign->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('featured.id', $today->id)
            // Dropped, so the board doesn't keep re-sending an id it ignored.
            ->where('targetId', null));
});

it('totals only the teams the target included, not the whole workspace', function () {
    $workspace = unlockedWorkspace();
    $date = now()->toDateString();

    $inA = teamSelling($workspace, 1500, $date, 'IN-A');
    $inB = teamSelling($workspace, 500, $date, 'IN-B');
    // Selling on the same day but left out of the target — must not count.
    teamSelling($workspace, 9000, $date, 'OUT');

    targetIncluding($workspace, $date, [$inA, $inB], goal: 1000);

    $this->getJson("/public/workspaces/{$workspace->slug}/sales-targets/kpis")
        ->assertOk()
        ->assertJsonPath('data.today.total_sales', 2000)
        ->assertJsonPath('data.today.target_sales', 2000);

    // Achievement, ROAS and the team counts are derived in the browser from
    // these rows, so the endpoint just has to hand over both teams.
    $this->getJson("/public/workspaces/{$workspace->slug}/sales-targets/teams")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('counts an order once when its page owner is on two included teams', function () {
    $workspace = unlockedWorkspace();
    $date = now()->toDateString();

    $team = teamSelling($workspace, 1000, $date, 'SHARED');

    // The same owner also sits on a second included team. Summing the per-team
    // rows would double this order; the headline must still read 1000.
    $owner = $team->members()->first();
    $second = Team::factory()->create(['workspace_id' => $workspace->id]);
    $second->members()->attach($owner->id);

    targetIncluding($workspace, $date, [$team, $second], goal: 1000);

    $this->getJson("/public/workspaces/{$workspace->slug}/sales-targets/kpis")
        ->assertOk()
        ->assertJsonPath('data.today.total_sales', 1000)
        ->assertJsonPath('data.today.target_sales', 2000);
});

it('narrows the total to one team when the board is filtered', function () {
    $workspace = unlockedWorkspace();
    $date = now()->toDateString();

    $inA = teamSelling($workspace, 1500, $date, 'IN-A');
    $inB = teamSelling($workspace, 500, $date, 'IN-B');

    targetIncluding($workspace, $date, [$inA, $inB], goal: 1000);

    $this->getJson("/public/workspaces/{$workspace->slug}/sales-targets/kpis?team_id={$inB->id}")
        ->assertOk()
        ->assertJsonPath('data.today.total_sales', 500)
        ->assertJsonPath('data.today.target_sales', 1000);

    $this->getJson("/public/workspaces/{$workspace->slug}/sales-targets/teams?team_id={$inB->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.team_id', $inB->id)
        ->assertJsonPath('data.0.sales', 500);
});

it('totals zero for a target that included no teams', function () {
    $workspace = unlockedWorkspace();
    $date = now()->toDateString();

    teamSelling($workspace, 9000, $date, 'OUT');
    targetIncluding($workspace, $date, []);

    $this->getJson("/public/workspaces/{$workspace->slug}/sales-targets/kpis")
        ->assertOk()
        ->assertJsonPath('data.today.total_sales', 0)
        ->assertJsonPath('data.today.ad_budget', null);

    $this->getJson("/public/workspaces/{$workspace->slug}/sales-targets/teams")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('returns measured team rows without scoring them', function () {
    $workspace = unlockedWorkspace();
    $date = now()->toDateString();
    $team = teamSelling($workspace, 1500, $date, 'IN-A');
    targetIncluding($workspace, $date, [$team], goal: 1000);

    $row = $this->getJson("/public/workspaces/{$workspace->slug}/sales-targets/teams")
        ->assertOk()
        ->json('data.0');

    // Facts only — the browser derives achievement, ROAS, rank and the flags.
    expect(array_keys($row))->toEqualCanonicalizing([
        'team_id', 'name', 'sales', 'target', 'ad_budget',
    ]);
});

it('carries the previous day so the trend arrows can be derived', function () {
    $workspace = unlockedWorkspace();
    $today = now()->toDateString();
    $yesterday = now()->subDay()->toDateString();

    $team = teamSelling($workspace, 1500, $today, 'TODAY');
    targetIncluding($workspace, $yesterday, [$team], goal: 1000);
    targetIncluding($workspace, $today, [$team], goal: 1000);

    $this->getJson("/public/workspaces/{$workspace->slug}/sales-targets/kpis")
        ->assertOk()
        ->assertJsonPath('data.today.total_sales', 1500)
        ->assertJsonPath('data.previous.target_sales', 1000)
        // Nothing sold yesterday, and that zero is what the arrow compares to.
        ->assertJsonPath('data.previous.total_sales', 0);
});

it('serves the leader sparkline for a team on the board', function () {
    $workspace = unlockedWorkspace();
    $date = now()->toDateString();
    $team = teamSelling($workspace, 1500, $date, 'IN-A');
    targetIncluding($workspace, $date, [$team], goal: 1000);

    $trend = $this->getJson("/public/workspaces/{$workspace->slug}/sales-targets/team-trend?team_id={$team->id}")
        ->assertOk()
        ->json('data');

    expect($trend)->toHaveCount(14)
        // JSON renders a whole float as an int, so compare loosely on the amount.
        ->and(end($trend))->toEqual(['date' => $date, 'sales' => 1500]);

    // A team that isn't on the board has no trend to draw.
    $this->getJson("/public/workspaces/{$workspace->slug}/sales-targets/team-trend?team_id=999999")
        ->assertOk()
        ->assertJsonPath('data', null);
});

it('no longer exposes the endpoints the board now derives', function () {
    $workspace = unlockedWorkspace();
    targetOn($workspace, now()->toDateString(), 'Today');

    foreach (['leader', 'leaderboard', 'sales-vs-target', 'achievement-distribution'] as $gone) {
        $this->getJson("/public/workspaces/{$workspace->slug}/sales-targets/{$gone}")
            ->assertNotFound();
    }
});

it('keeps the pinned target behind the password gate', function () {
    $workspace = Workspace::factory()->create(['public_password' => bcrypt('secret')]);
    $target = targetOn($workspace, now()->subDay()->toDateString(), 'Older');

    $this->get("/public/workspaces/{$workspace->slug}/sales-targets/{$target->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('locked', true)->missing('featured'));

    $this->getJson("/public/workspaces/{$workspace->slug}/sales-targets/kpis?id={$target->id}")
        ->assertForbidden();
});
