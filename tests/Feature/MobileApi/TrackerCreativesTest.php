<?php

use App\Enums\Permission;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Modules\Creatives\Models\Creative;
use Modules\Creatives\Models\CreativeReview;

function trackerWorkspace(bool $moduleEnabled = true): Workspace
{
    $owner = User::factory()->withoutTwoFactor()->create();

    return Workspace::factory()->forOwner($owner)->create(['creatives_module_enabled' => $moduleEnabled]);
}

/** A member holding exactly these Creatives permissions. */
function trackerMember(Workspace $workspace, array $permissions = []): User
{
    return makeMemberWithPermissions(
        $workspace,
        array_map(fn (Permission $p) => $p->value, $permissions),
        'Creatives',
    );
}

function trackerCreative(Workspace $workspace, array $reviewers = [], array $overrides = []): Creative
{
    $creative = Creative::create(array_merge([
        'workspace_id' => $workspace->id,
        'creator_id' => $workspace->owner_id,
        'name' => 'Creative '.fake()->unique()->numberBetween(1, 99999),
        'creative_date' => '2026-07-01',
        'format' => 'image',
        'ads_status' => 'pending',
        'final_status' => 'for_approval',
    ], $overrides));

    $creative->assignedReviewers()->sync(collect($reviewers)->pluck('id'));

    return $creative;
}

function trackerReview(Creative $creative, User $reviewer, string $status): CreativeReview
{
    return CreativeReview::create(['creative_id' => $creative->id, 'reviewer_id' => $reviewer->id, 'status' => $status]);
}

function trackerHeaders(User $user): array
{
    return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
}

function trackerListIds($test, User $user): array
{
    return collect($test->getJson('/api/v1/creatives-tracker/creatives/assigned', trackerHeaders($user))
        ->assertOk()
        ->json('data'))->pluck('id')->sort()->values()->all();
}

// List

test('the list keeps reviewed creatives, so they can be ticked off', function () {
    $workspace = trackerWorkspace();
    $reviewer = trackerMember($workspace, [Permission::ReviewCreatives]);
    $creative = trackerCreative($workspace, [$reviewer]);

    expect(trackerListIds($this, $reviewer))->toBe([$creative->id]);

    $this->postJson("/api/v1/creatives-tracker/creatives/{$creative->id}/reviews", ['status' => 'revision'], trackerHeaders($reviewer))
        ->assertCreated();

    $this->getJson('/api/v1/creatives-tracker/creatives/assigned', trackerHeaders($reviewer))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('to_review', 0)
        ->assertJsonPath('data.0.my_review.status', 'revision')
        ->assertJsonPath('data.0.permissions', ['update_final_status' => false, 'review' => true]);
});

test('the list leaves out creatives the user is not assigned to, even with View Creatives', function () {
    $workspace = trackerWorkspace();
    $viewer = trackerMember($workspace, [Permission::ViewCreatives, Permission::ViewAllWorkspaceData]);
    trackerCreative($workspace);

    expect(trackerListIds($this, $viewer))->toBe([]);
});

// Detail

test('guests cannot open a creative', function () {
    $creative = trackerCreative(trackerWorkspace());

    $this->getJson("/api/v1/creatives-tracker/creatives/{$creative->id}")->assertUnauthorized();
});

test('an assigned reviewer can open the detail', function () {
    $workspace = trackerWorkspace();
    $reviewer = trackerMember($workspace, [Permission::ReviewCreatives]);
    $creative = trackerCreative($workspace, [$reviewer], ['headline' => 'Big sale']);
    trackerReview($creative, $reviewer, 'revision');

    $this->getJson("/api/v1/creatives-tracker/creatives/{$creative->id}", trackerHeaders($reviewer))
        ->assertOk()
        ->assertJsonStructure(['data' => [
            'id', 'code', 'name', 'headline', 'final_status', 'workspace', 'assigned_reviewers',
            'reviews' => [['id', 'status', 'feedback', 'timestamp_seconds', 'voice', 'reviewer', 'created_at']],
            'my_review', 'permissions' => ['update_final_status', 'review'],
        ]])
        ->assertJsonPath('data.headline', 'Big sale')
        ->assertJsonPath('data.reviews.0.status', 'revision');
});

test('detail is 404 for a creative the user cannot see', function (string $case) {
    $workspace = trackerWorkspace(moduleEnabled: $case !== 'module off');
    $user = trackerMember($workspace, $case === 'no permission' ? [] : [Permission::ViewCreatives]);
    $creative = trackerCreative($case === 'other workspace' ? trackerWorkspace() : $workspace);

    $this->getJson("/api/v1/creatives-tracker/creatives/{$creative->id}", trackerHeaders($user))->assertNotFound();
})->with(['module off', 'no permission', 'other workspace']);

// Final status

test('a user with Update Creative Status can approve, stamping approved_at/by', function () {
    $workspace = trackerWorkspace();
    $manager = trackerMember($workspace, [Permission::ViewCreatives, Permission::ViewAllWorkspaceData, Permission::UpdateCreativeStatus]);
    $creative = trackerCreative($workspace);

    $this->patchJson("/api/v1/creatives-tracker/creatives/{$creative->id}/final-status", ['final_status' => 'approved'], trackerHeaders($manager))
        ->assertOk()
        ->assertJsonPath('data.final_status', 'approved')
        ->assertJsonPath('data.approved_by.id', $manager->id);

    $creative->refresh();
    expect($creative->approved_at)->not->toBeNull();

    $this->patchJson("/api/v1/creatives-tracker/creatives/{$creative->id}/final-status", ['final_status' => 'for_revision'], trackerHeaders($manager))
        ->assertOk();

    $creative->refresh();
    expect($creative->final_status)->toBe('for_revision')
        ->and($creative->approved_at)->toBeNull()
        ->and($creative->approved_by)->toBeNull();
});

test('changing the final status needs Update Creative Status', function () {
    $workspace = trackerWorkspace();
    $reviewer = trackerMember($workspace, [Permission::ViewCreatives, Permission::ReviewCreatives]);
    $creative = trackerCreative($workspace, [$reviewer]);

    $this->patchJson("/api/v1/creatives-tracker/creatives/{$creative->id}/final-status", ['final_status' => 'approved'], trackerHeaders($reviewer))
        ->assertForbidden();

    expect($creative->refresh()->final_status)->toBe('for_approval');
});

test('final status is validated', function () {
    $workspace = trackerWorkspace();
    $creative = trackerCreative($workspace);

    $this->patchJson("/api/v1/creatives-tracker/creatives/{$creative->id}/final-status", ['final_status' => 'done'], trackerHeaders($workspace->owner))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('final_status');
});

test('final status of an invisible creative is 404', function () {
    $user = trackerMember(trackerWorkspace(), [Permission::UpdateCreativeStatus]);
    $creative = trackerCreative(trackerWorkspace());

    $this->patchJson("/api/v1/creatives-tracker/creatives/{$creative->id}/final-status", ['final_status' => 'approved'], trackerHeaders($user))
        ->assertNotFound();
});

// Reviews

test('an assigned reviewer can leave a review', function () {
    $workspace = trackerWorkspace();
    $reviewer = trackerMember($workspace, [Permission::ReviewCreatives]);
    $creative = trackerCreative($workspace, [$reviewer]);

    $this->postJson("/api/v1/creatives-tracker/creatives/{$creative->id}/reviews", ['status' => 'revision', 'feedback' => 'Logo too small'], trackerHeaders($reviewer))
        ->assertCreated()
        ->assertJsonPath('data.my_review.status', 'revision')
        ->assertJsonPath('data.reviews.0.feedback', 'Logo too small');
});

test('a review needs a status', function () {
    $workspace = trackerWorkspace();
    $reviewer = trackerMember($workspace, [Permission::ReviewCreatives]);
    $creative = trackerCreative($workspace, [$reviewer]);

    $this->postJson("/api/v1/creatives-tracker/creatives/{$creative->id}/reviews", ['feedback' => 'Just a comment'], trackerHeaders($reviewer))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

test('only assigned reviewers with Review Creatives can review', function () {
    $workspace = trackerWorkspace();
    $notAssigned = trackerMember($workspace, [Permission::ViewCreatives, Permission::ViewAllWorkspaceData, Permission::ReviewCreatives]);
    $noPermission = trackerMember($workspace, [Permission::ViewCreatives]);
    $creative = trackerCreative($workspace, [$noPermission]);

    foreach ([$notAssigned, $noPermission] as $user) {
        $this->postJson("/api/v1/creatives-tracker/creatives/{$creative->id}/reviews", ['status' => 'approved'], trackerHeaders($user))
            ->assertForbidden();
    }

    expect($creative->reviews()->count())->toBe(0);
});

// Voice messages

test('a review can carry a voice message, returned with a playable url', function () {
    Storage::fake('s3');
    config(['filesystems.creative_media_disk' => 's3']);

    $workspace = trackerWorkspace();
    $reviewer = trackerMember($workspace, [Permission::ReviewCreatives]);
    $creative = trackerCreative($workspace, [$reviewer]);

    $response = $this->post("/api/v1/creatives-tracker/creatives/{$creative->id}/reviews", [
        'status' => 'revision',
        'voice' => UploadedFile::fake()->create('voice.m4a', 40, 'audio/mp4'),
        'voice_duration_seconds' => 14,
    ], [...trackerHeaders($reviewer), 'Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.reviews.0.voice.duration_seconds', 14);

    expect($response->json('data.reviews.0.voice.url'))->toBeString()->not->toBeEmpty();

    $voice = $creative->reviews()->sole()->voice();
    expect($voice)->not->toBeNull()
        ->and(Storage::disk('s3')->exists($voice->getPathRelativeToRoot()))->toBeTrue();
});

test('reviews without a voice message return voice null', function () {
    $workspace = trackerWorkspace();
    $reviewer = trackerMember($workspace, [Permission::ReviewCreatives]);
    $creative = trackerCreative($workspace, [$reviewer]);
    trackerReview($creative, $reviewer, 'approved');

    $this->getJson("/api/v1/creatives-tracker/creatives/{$creative->id}", trackerHeaders($reviewer))
        ->assertOk()
        ->assertJsonPath('data.reviews.0.voice', null);
});

test('a voice upload must be audio', function () {
    $workspace = trackerWorkspace();
    $reviewer = trackerMember($workspace, [Permission::ReviewCreatives]);
    $creative = trackerCreative($workspace, [$reviewer]);

    $this->post("/api/v1/creatives-tracker/creatives/{$creative->id}/reviews", [
        'status' => 'revision',
        'voice' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
    ], [...trackerHeaders($reviewer), 'Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('voice');

    expect($creative->reviews()->count())->toBe(0);
});

test('the voice route needs a valid signature', function () {
    Storage::fake('s3');
    config(['filesystems.creative_media_disk' => 's3']);

    $workspace = trackerWorkspace();
    $reviewer = trackerMember($workspace, [Permission::ReviewCreatives]);
    $creative = trackerCreative($workspace, [$reviewer]);
    $review = trackerReview($creative, $reviewer, 'revision');
    $review->addMedia(UploadedFile::fake()->create('voice.webm', 40, 'audio/webm'))
        ->toMediaCollection(CreativeReview::VOICE_COLLECTION);

    $this->get("/api/v1/creatives-tracker/creatives/{$creative->id}/reviews/{$review->id}/voice")
        ->assertForbidden();

    $signed = URL::temporarySignedRoute('api.v1.creatives-tracker.creatives.reviews.voice', now()->addHour(), [
        'creative' => $creative->id,
        'review' => $review->id,
    ]);
    expect($this->get($signed)->status())->toBeIn([200, 302]);

    // A signature for one creative can't be replayed against another's review.
    $other = trackerCreative($workspace, [$reviewer]);
    $mismatched = URL::temporarySignedRoute('api.v1.creatives-tracker.creatives.reviews.voice', now()->addHour(), [
        'creative' => $other->id,
        'review' => $review->id,
    ]);
    $this->get($mismatched)->assertNotFound();
});

// Deleting reviews

test('a reviewer can delete their own review, voice message included', function () {
    Storage::fake('s3');
    config(['filesystems.creative_media_disk' => 's3']);

    $workspace = trackerWorkspace();
    $reviewer = trackerMember($workspace, [Permission::ReviewCreatives]);
    $creative = trackerCreative($workspace, [$reviewer]);
    $review = trackerReview($creative, $reviewer, 'revision');
    $voice = $review->addMedia(UploadedFile::fake()->create('voice.m4a', 40, 'audio/mp4'))
        ->toMediaCollection(CreativeReview::VOICE_COLLECTION);

    $this->getJson("/api/v1/creatives-tracker/creatives/{$creative->id}", trackerHeaders($reviewer))
        ->assertJsonPath('data.reviews.0.can_delete', true);

    $this->deleteJson("/api/v1/creatives-tracker/creatives/{$creative->id}/reviews/{$review->id}", [], trackerHeaders($reviewer))
        ->assertOk()
        ->assertJsonPath('data.reviews', [])
        ->assertJsonPath('data.my_review', null);

    expect(CreativeReview::find($review->id))->toBeNull()
        ->and(Storage::disk('s3')->exists($voice->getPathRelativeToRoot()))->toBeFalse();
});

test('guests cannot delete a review', function () {
    $workspace = trackerWorkspace();
    $reviewer = trackerMember($workspace, [Permission::ReviewCreatives]);
    $creative = trackerCreative($workspace, [$reviewer]);
    $review = trackerReview($creative, $reviewer, 'approved');

    $this->deleteJson("/api/v1/creatives-tracker/creatives/{$creative->id}/reviews/{$review->id}")
        ->assertUnauthorized();
});

test('a reviewer cannot delete someone else\'s review', function () {
    $workspace = trackerWorkspace();
    $author = trackerMember($workspace, [Permission::ReviewCreatives]);
    $other = trackerMember($workspace, [Permission::ReviewCreatives]);
    $creative = trackerCreative($workspace, [$author, $other]);
    $review = trackerReview($creative, $author, 'approved');

    $this->getJson("/api/v1/creatives-tracker/creatives/{$creative->id}", trackerHeaders($other))
        ->assertJsonPath('data.reviews.0.can_delete', false);

    $this->deleteJson("/api/v1/creatives-tracker/creatives/{$creative->id}/reviews/{$review->id}", [], trackerHeaders($other))
        ->assertForbidden();

    expect(CreativeReview::find($review->id))->not->toBeNull();
});

test('deleting a review needs Review Creatives', function () {
    $workspace = trackerWorkspace();
    $author = trackerMember($workspace, [Permission::ViewCreatives]);
    $creative = trackerCreative($workspace, [$author]);
    $review = trackerReview($creative, $author, 'approved');

    $this->deleteJson("/api/v1/creatives-tracker/creatives/{$creative->id}/reviews/{$review->id}", [], trackerHeaders($author))
        ->assertForbidden();
});

test('deleting is 404 for a review of another creative or an invisible creative', function () {
    $workspace = trackerWorkspace();
    $reviewer = trackerMember($workspace, [Permission::ReviewCreatives]);
    $creative = trackerCreative($workspace, [$reviewer]);
    $other = trackerCreative($workspace, [$reviewer]);
    $review = trackerReview($other, $reviewer, 'approved');

    $this->deleteJson("/api/v1/creatives-tracker/creatives/{$creative->id}/reviews/{$review->id}", [], trackerHeaders($reviewer))
        ->assertNotFound();

    $hidden = trackerCreative(trackerWorkspace());
    $hiddenReview = trackerReview($hidden, $reviewer, 'approved');
    $this->deleteJson("/api/v1/creatives-tracker/creatives/{$hidden->id}/reviews/{$hiddenReview->id}", [], trackerHeaders($reviewer))
        ->assertNotFound();

    expect(CreativeReview::count())->toBe(2);
});

// Media preview

test('a creative carries its uploaded media with a loadable url', function () {
    Storage::fake('s3');
    config(['filesystems.creative_media_disk' => 's3', 'filesystems.creative_cdn' => ['url' => 'https://cdn.test', 'key_pair_id' => null, 'private_key' => null]]);

    $workspace = trackerWorkspace();
    $reviewer = trackerMember($workspace, [Permission::ReviewCreatives]);
    $creative = trackerCreative($workspace, [$reviewer]);
    $media = $creative->addMedia(UploadedFile::fake()->image('hero shot.png', 40, 40))->toMediaCollection(Creative::MEDIA_COLLECTION);

    $this->getJson("/api/v1/creatives-tracker/creatives/{$creative->id}", trackerHeaders($reviewer))
        ->assertOk()
        ->assertJsonPath('data.media.url', "https://cdn.test/{$media->id}/hero-shot.png")
        ->assertJsonPath('data.media.mime_type', 'image/png');

    $this->getJson('/api/v1/creatives-tracker/creatives/assigned', trackerHeaders($reviewer))
        ->assertOk()
        ->assertJsonPath('data.0.media.mime_type', 'image/png');
});

test('a creative without an upload has media null', function () {
    $workspace = trackerWorkspace();
    $reviewer = trackerMember($workspace, [Permission::ReviewCreatives]);
    $creative = trackerCreative($workspace, [$reviewer]);

    $this->getJson("/api/v1/creatives-tracker/creatives/{$creative->id}", trackerHeaders($reviewer))
        ->assertOk()
        ->assertJsonPath('data.media', null);
});

// Area reviews

function trackerImageCreative(Workspace $workspace, User $reviewer): Creative
{
    Storage::fake('s3');
    config(['filesystems.creative_media_disk' => 's3']);

    $creative = trackerCreative($workspace, [$reviewer]);
    $creative->addMedia(UploadedFile::fake()->image('ad.png', 40, 40))->toMediaCollection(Creative::MEDIA_COLLECTION);

    return $creative;
}

test('a review can point at an area or a point of the uploaded image', function (array $region) {
    $workspace = trackerWorkspace();
    $reviewer = trackerMember($workspace, [Permission::ReviewCreatives]);
    $creative = trackerImageCreative($workspace, $reviewer);

    $this->postJson("/api/v1/creatives-tracker/creatives/{$creative->id}/reviews", [
        'status' => 'revision',
        'feedback' => 'Logo here is blurry',
        'region' => $region,
    ], trackerHeaders($reviewer))
        ->assertCreated()
        ->assertJson(['data' => ['reviews' => [['region' => $region]]]]);

    expect($creative->reviews()->sole()->region)->toEqual($region);
})->with([
    'area' => [['x' => 0.1, 'y' => 0.2, 'w' => 0.3, 'h' => 0.25]],
    'point' => [['x' => 0.5, 'y' => 0.5, 'w' => 0, 'h' => 0]],
]);

test('an area must sit inside the image', function () {
    $workspace = trackerWorkspace();
    $reviewer = trackerMember($workspace, [Permission::ReviewCreatives]);
    $creative = trackerImageCreative($workspace, $reviewer);

    $this->postJson("/api/v1/creatives-tracker/creatives/{$creative->id}/reviews", [
        'status' => 'revision',
        'region' => ['x' => 0.8, 'y' => 0.1, 'w' => 0.5, 'h' => 0.1],
    ], trackerHeaders($reviewer))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('region');
});

test('an area needs an uploaded image', function () {
    $workspace = trackerWorkspace();
    $reviewer = trackerMember($workspace, [Permission::ReviewCreatives]);
    $creative = trackerCreative($workspace, [$reviewer]);

    $this->postJson("/api/v1/creatives-tracker/creatives/{$creative->id}/reviews", [
        'status' => 'revision',
        'region' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.1, 'h' => 0.1],
    ], trackerHeaders($reviewer))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('region');

    expect($creative->reviews()->count())->toBe(0);
});

test('reviews without an area return region null', function () {
    $workspace = trackerWorkspace();
    $reviewer = trackerMember($workspace, [Permission::ReviewCreatives]);
    $creative = trackerCreative($workspace, [$reviewer]);
    trackerReview($creative, $reviewer, 'approved');

    $this->getJson("/api/v1/creatives-tracker/creatives/{$creative->id}", trackerHeaders($reviewer))
        ->assertOk()
        ->assertJsonPath('data.reviews.0.region', null);
});

// Timestamp reviews

test('a review can point at a moment of a video creative', function () {
    $workspace = trackerWorkspace();
    $reviewer = trackerMember($workspace, [Permission::ReviewCreatives]);
    $creative = trackerCreative($workspace, [$reviewer], ['format' => 'video']);

    $this->postJson("/api/v1/creatives-tracker/creatives/{$creative->id}/reviews", [
        'status' => 'revision',
        'feedback' => 'Cut the pause here',
        'timestamp_seconds' => 12.4,
    ], trackerHeaders($reviewer))
        ->assertCreated()
        ->assertJsonPath('data.reviews.0.timestamp_seconds', 12.4);

    expect($creative->reviews()->sole()->timestamp_seconds)->toBe(12.4);
});

test('a timestamp needs a video creative', function () {
    $workspace = trackerWorkspace();
    $reviewer = trackerMember($workspace, [Permission::ReviewCreatives]);
    $creative = trackerCreative($workspace, [$reviewer], ['format' => 'image']);

    $this->postJson("/api/v1/creatives-tracker/creatives/{$creative->id}/reviews", [
        'status' => 'revision',
        'timestamp_seconds' => 3,
    ], trackerHeaders($reviewer))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('timestamp_seconds');

    expect($creative->reviews()->count())->toBe(0);
});
