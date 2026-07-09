<?php

use Modules\GencysERP\Models\GencysInternDailyRecord;
use Modules\GencysERP\Models\Intern;

function recordsUrl($workspace, array $query = []): string
{
    $url = "/workspaces/{$workspace->slug}/gencys/intern-daily-records";

    return $query ? $url.'?'.http_build_query($query) : $url;
}

function makeRecord($workspace, Intern $intern, array $attrs = []): GencysInternDailyRecord
{
    return GencysInternDailyRecord::create(array_merge([
        'workspace_id' => $workspace->id,
        'gencys_intern_id' => $intern->id,
        'record_date' => '2026-07-06',
        'sales' => 1000,
        'roas' => 2,
        'ad_spent' => 500,
        'rts_rate' => 10,
        'rts_amount' => 100,
    ], $attrs));
}

test('the page lists workspace records joined with the intern name', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();

    $intern = Intern::factory()->for($workspace)->create(['intern_id' => 1, 'full_name' => 'Juan']);
    makeRecord($workspace, $intern);

    $otherIntern = Intern::factory()->for($other)->create(['intern_id' => 1]);
    makeRecord($other, $otherIntern);

    $this->actingAs($user)
        ->get(recordsUrl($workspace))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/gencys/intern-daily-records/index')
            ->has('records.data', 1)
            ->where('records.data.0.intern_name', 'Juan')
        );
});

test('search matches the intern name / company / username', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $a = Intern::factory()->for($workspace)->create(['intern_id' => 1, 'full_name' => 'Alpha', 'company_name' => 'Acme']);
    $b = Intern::factory()->for($workspace)->create(['intern_id' => 2, 'full_name' => 'Beta', 'company_name' => 'Globex']);
    makeRecord($workspace, $a);
    makeRecord($workspace, $b);

    $this->actingAs($user)
        ->get(recordsUrl($workspace, ['filter' => ['search' => 'Globex']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('records.data', 1)
            ->where('records.data.0.intern_name', 'Beta')
        );
});

test('the intern filter narrows to one intern', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $a = Intern::factory()->for($workspace)->create(['intern_id' => 1]);
    $b = Intern::factory()->for($workspace)->create(['intern_id' => 2]);
    makeRecord($workspace, $a);
    makeRecord($workspace, $b);

    $this->actingAs($user)
        ->get(recordsUrl($workspace, ['filter' => ['gencys_intern_id' => $a->id]]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('records.data', 1));
});

test('the date range filter bounds records inclusively', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $intern = Intern::factory()->for($workspace)->create(['intern_id' => 1]);
    makeRecord($workspace, $intern, ['record_date' => '2026-07-01', 'sales' => 111]);
    makeRecord($workspace, $intern, ['record_date' => '2026-07-06', 'sales' => 777]);
    makeRecord($workspace, $intern, ['record_date' => '2026-07-10', 'sales' => 999]);

    $this->actingAs($user)
        ->get(recordsUrl($workspace, ['filter' => ['date_start' => '2026-07-05', 'date_end' => '2026-07-08']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('records.data', 1)
            ->where('records.data.0.sales', fn ($s) => (float) $s === 777.0)
        );
});

test('records can be sorted by sales and paginated', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $intern = Intern::factory()->for($workspace)->create(['intern_id' => 1]);
    makeRecord($workspace, $intern, ['record_date' => '2026-07-01', 'sales' => 300]);
    makeRecord($workspace, $intern, ['record_date' => '2026-07-02', 'sales' => 100]);
    makeRecord($workspace, $intern, ['record_date' => '2026-07-03', 'sales' => 200]);

    $this->actingAs($user)
        ->get(recordsUrl($workspace, ['sort' => 'sales', 'per_page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('records.per_page', 2)
            ->where('records.total', 3)
            ->where('records.data.0.sales', fn ($s) => (float) $s === 100.0)
        );
});

test('a member without the permission is forbidden', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = makeWorkspaceMember($workspace, 'member');

    $this->actingAs($member)
        ->get(recordsUrl($workspace))
        ->assertForbidden();
});
