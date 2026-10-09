<?php

use App\Models\User;
use App\Models\Workspace;
use Modules\Creatives\Models\Creative;
use Modules\Creatives\Models\CreativeReview;

function mobileCreativesWorkspace(bool $moduleEnabled = true): Workspace
{
    $owner = User::factory()->withoutTwoFactor()->create();

    return Workspace::factory()->forOwner($owner)->create(['creatives_module_enabled' => $moduleEnabled]);
}

function mobileReviewer(Workspace ...$workspaces): User
{
    $user = User::factory()->withoutTwoFactor()->create();

    foreach ($workspaces as $workspace) {
        $workspace->users()->attach($user->id, ['role' => 'member']);
    }

    return $user;
}

function mobileCreative(Workspace $workspace, array $reviewers = [], array $overrides = []): Creative
{
    $creative = Creative::create(array_merge([
        'workspace_id' => $workspace->id,
        'creator_id' => $workspace->owner_id,
        'name' => 'Creative '.fake()->unique()->numberBetween(1, 99999),
        'creative_date' => '2026-07-01',
        'format' => 'video',
        'ads_status' => 'pending',
        'final_status' => 'for_approval',
    ], $overrides));

    $creative->assignedReviewers()->sync(collect($reviewers)->pluck('id'));

    return $creative;
}

function mobileHeaders(User $user): array
{
    return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
}

test('returns only creatives assigned to the token user, across workspaces', function () {
    $a = mobileCreativesWorkspace();
    $b = mobileCreativesWorkspace();
    $reviewer = mobileReviewer($a, $b);
    $someoneElse = mobileReviewer($a);

    $mineA = mobileCreative($a, [$reviewer]);
    $mineB = mobileCreative($b, [$reviewer, $someoneElse]);
    mobileCreative($a, [$someoneElse]);
    mobileCreative($a);

    $response = $this->getJson('/api/v1/creatives-tracker/creatives/assigned', mobileHeaders($reviewer))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'code', 'name', 'format', 'final_status', 'workspace' => ['id', 'name', 'slug'], 'assigned_reviewers', 'reviews']],
            'next_cursor', 'next_page_url', 'per_page', 'total',
        ]);

    expect($response->json('total'))->toBe(2)
        ->and(collect($response->json('data'))->pluck('id')->sort()->values()->all())
        ->toBe(collect([$mineA->id, $mineB->id])->sort()->values()->all());
});

test('hides creatives from workspaces with the creatives module off', function () {
    $off = mobileCreativesWorkspace(moduleEnabled: false);
    $reviewer = mobileReviewer($off);
    mobileCreative($off, [$reviewer]);

    $this->getJson('/api/v1/creatives-tracker/creatives/assigned', mobileHeaders($reviewer))
        ->assertOk()
        ->assertJsonPath('total', 0);
});

test('hides creatives from workspaces the user is no longer a member of', function () {
    $workspace = mobileCreativesWorkspace();
    $reviewer = mobileReviewer($workspace);
    mobileCreative($workspace, [$reviewer]);
    $workspace->users()->detach($reviewer->id);

    $this->getJson('/api/v1/creatives-tracker/creatives/assigned', mobileHeaders($reviewer))
        ->assertOk()
        ->assertJsonPath('total', 0);
});

test('keeps creatives the user already reviewed, marked with my_review', function () {
    $workspace = mobileCreativesWorkspace();
    $reviewer = mobileReviewer($workspace);
    $otherReviewer = mobileReviewer($workspace);

    $reviewedByMe = mobileCreative($workspace, [$reviewer, $otherReviewer], ['creative_date' => '2026-07-03']);
    $reviewedByOther = mobileCreative($workspace, [$reviewer, $otherReviewer], ['creative_date' => '2026-07-02']);
    $untouched = mobileCreative($workspace, [$reviewer], ['creative_date' => '2026-07-01']);

    CreativeReview::create(['creative_id' => $reviewedByMe->id, 'reviewer_id' => $reviewer->id, 'status' => 'approved']);
    CreativeReview::create(['creative_id' => $reviewedByOther->id, 'reviewer_id' => $otherReviewer->id, 'status' => 'revision']);

    $this->getJson('/api/v1/creatives-tracker/creatives/assigned', mobileHeaders($reviewer))
        ->assertOk()
        ->assertJsonPath('total', 3)
        ->assertJsonPath('to_review', 2)
        ->assertJsonPath('data.0.id', $reviewedByMe->id)
        ->assertJsonPath('data.0.my_review.status', 'approved')
        ->assertJsonPath('data.1.id', $reviewedByOther->id)
        ->assertJsonPath('data.1.my_review', null)
        ->assertJsonPath('data.2.id', $untouched->id);
});

test('only lists creatives that are still for approval', function () {
    $workspace = mobileCreativesWorkspace();
    $reviewer = mobileReviewer($workspace);

    $forApproval = mobileCreative($workspace, [$reviewer], ['final_status' => 'for_approval']);
    mobileCreative($workspace, [$reviewer], ['final_status' => 'for_revision']);
    mobileCreative($workspace, [$reviewer], ['final_status' => 'approved']);

    $this->getJson('/api/v1/creatives-tracker/creatives/assigned', mobileHeaders($reviewer))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.id', $forApproval->id);
});

test('workspace narrows the list to one workspace', function () {
    $a = mobileCreativesWorkspace();
    $b = mobileCreativesWorkspace();
    $reviewer = mobileReviewer($a, $b);

    $inA = mobileCreative($a, [$reviewer]);
    mobileCreative($b, [$reviewer]);

    $this->getJson("/api/v1/creatives-tracker/creatives/assigned?workspace={$a->slug}", mobileHeaders($reviewer))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.id', $inA->id);
});

test('search matches code, name and headline', function () {
    $workspace = mobileCreativesWorkspace();
    $reviewer = mobileReviewer($workspace);

    $byName = mobileCreative($workspace, [$reviewer], ['name' => 'Summer Promo']);
    $byHeadline = mobileCreative($workspace, [$reviewer], ['headline' => 'Big promo sale']);
    $byCode = mobileCreative($workspace, [$reviewer], ['code' => 'ZZPROMO234']);
    mobileCreative($workspace, [$reviewer], ['name' => 'Something else']);

    $response = $this->getJson('/api/v1/creatives-tracker/creatives/assigned?search=promo', mobileHeaders($reviewer))
        ->assertOk()
        ->assertJsonPath('total', 3);

    expect(collect($response->json('data'))->pluck('id')->sort()->values()->all())
        ->toBe(collect([$byName->id, $byHeadline->id, $byCode->id])->sort()->values()->all());
});

test('format filters image, video, or all', function () {
    $workspace = mobileCreativesWorkspace();
    $reviewer = mobileReviewer($workspace);
    $headers = mobileHeaders($reviewer);

    $video = mobileCreative($workspace, [$reviewer], ['format' => 'video']);
    $image = mobileCreative($workspace, [$reviewer], ['format' => 'image']);

    $this->getJson('/api/v1/creatives-tracker/creatives/assigned?format=video', $headers)
        ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $video->id);

    $this->getJson('/api/v1/creatives-tracker/creatives/assigned?format=image', $headers)
        ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $image->id);

    $this->getJson('/api/v1/creatives-tracker/creatives/assigned?format=all', $headers)
        ->assertOk()->assertJsonPath('total', 2);

    $this->getJson('/api/v1/creatives-tracker/creatives/assigned?format=gif', $headers)
        ->assertStatus(422)->assertJsonValidationErrors('format');
});

test('next_cursor walks the whole list without repeats, newest first', function () {
    $workspace = mobileCreativesWorkspace();
    $reviewer = mobileReviewer($workspace);
    $headers = mobileHeaders($reviewer);

    $ids = collect(range(1, 5))->map(fn ($day) => mobileCreative($workspace, [$reviewer], [
        'creative_date' => "2026-07-0{$day}",
    ])->id);

    $seen = [];
    $url = '/api/v1/creatives-tracker/creatives/assigned?per_page=2';

    while ($url) {
        $response = $this->getJson($url, $headers)->assertOk()->assertJsonPath('total', 5);
        $seen = [...$seen, ...collect($response->json('data'))->pluck('id')->all()];
        $cursor = $response->json('next_cursor');
        $url = $cursor ? "/api/v1/creatives-tracker/creatives/assigned?per_page=2&cursor={$cursor}" : null;
    }

    expect($seen)->toBe($ids->reverse()->values()->all());
});

test('an item leaving for-approval on page one does not make page two skip one', function () {
    $workspace = mobileCreativesWorkspace();
    $reviewer = mobileReviewer($workspace);
    $headers = mobileHeaders($reviewer);

    $ids = collect(range(1, 4))->map(fn ($day) => mobileCreative($workspace, [$reviewer], [
        'creative_date' => "2026-07-0{$day}",
    ])->id)->reverse()->values();

    $first = $this->getJson('/api/v1/creatives-tracker/creatives/assigned?per_page=2', $headers)->assertOk();

    Creative::whereKey($ids[0])->update(['final_status' => 'approved']);

    $second = $this->getJson('/api/v1/creatives-tracker/creatives/assigned?per_page=2&cursor='.$first->json('next_cursor'), $headers)
        ->assertOk();

    expect(collect($second->json('data'))->pluck('id')->all())->toBe([$ids[2], $ids[3]]);
});

test('per_page is capped at 100', function () {
    $workspace = mobileCreativesWorkspace();
    $reviewer = mobileReviewer($workspace);

    $this->getJson('/api/v1/creatives-tracker/creatives/assigned?per_page=500', mobileHeaders($reviewer))
        ->assertStatus(422)->assertJsonValidationErrors('per_page');
});

test('requires a sanctum token', function () {
    $this->getJson('/api/v1/creatives-tracker/creatives/assigned')->assertStatus(401);

    $this->getJson('/api/v1/creatives-tracker/creatives/assigned', ['Authorization' => 'Bearer not-a-token'])
        ->assertStatus(401);
});

test('a revoked token can no longer list creatives', function () {
    $workspace = mobileCreativesWorkspace();
    $reviewer = mobileReviewer($workspace);
    $headers = mobileHeaders($reviewer);

    $this->postJson('/api/v1/creatives-tracker/logout', [], $headers)->assertOk();
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/creatives-tracker/creatives/assigned', $headers)->assertStatus(401);
});
