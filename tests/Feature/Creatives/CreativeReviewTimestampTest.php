<?php

use App\Enums\Permission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Creatives\Models\Creative;
use Modules\Creatives\Models\CreativeReview;

function makeReviewableCreative($workspace, $user, string $format = 'video'): Creative
{
    $creative = Creative::create([
        'workspace_id' => $workspace->id,
        'creator_id' => $user->id,
        'name' => 'Reviewable '.uniqid(),
        'creative_date' => '2026-10-05',
        'format' => $format,
    ]);
    $creative->assignedReviewers()->sync([$user->id]);

    return $creative;
}

it('stores a review pinned to a second of a video creative', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeReviewableCreative($workspace, $user);

    $this->post("/workspaces/{$workspace->slug}/creatives/{$creative->id}/reviews", [
        'status' => 'revision',
        'feedback' => 'Logo is cut off here',
        'timestamp_seconds' => 12.5,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($creative->reviews()->sole()->timestamp_seconds)->toBe(12.5);
});

it('still stores a review of the whole video with no timestamp', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeReviewableCreative($workspace, $user);

    $this->post("/workspaces/{$workspace->slug}/creatives/{$creative->id}/reviews", [
        'status' => 'approved',
    ])->assertSessionHasNoErrors();

    expect($creative->reviews()->sole()->timestamp_seconds)->toBeNull();
});

it('refuses a timestamp on an image creative', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeReviewableCreative($workspace, $user, 'image');

    $this->post("/workspaces/{$workspace->slug}/creatives/{$creative->id}/reviews", [
        'status' => 'revision',
        'timestamp_seconds' => 3,
    ])->assertSessionHasErrors('timestamp_seconds');

    expect($creative->reviews()->count())->toBe(0);
});

it('rejects a negative or non-numeric timestamp', function (mixed $value) {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeReviewableCreative($workspace, $user);

    $this->post("/workspaces/{$workspace->slug}/creatives/{$creative->id}/reviews", [
        'status' => 'revision',
        'timestamp_seconds' => $value,
    ])->assertSessionHasErrors('timestamp_seconds');
})->with([-1, 'abc', '1:xx']);

it('lets the author move or clear the timestamp on their review', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeReviewableCreative($workspace, $user);
    $review = CreativeReview::create([
        'creative_id' => $creative->id,
        'reviewer_id' => $user->id,
        'status' => 'revision',
        'timestamp_seconds' => 4,
    ]);
    $url = "/workspaces/{$workspace->slug}/creatives/{$creative->id}/reviews/{$review->id}";

    $this->put($url, ['status' => 'revision', 'timestamp_seconds' => 9])->assertSessionHasNoErrors();
    expect($review->fresh()->timestamp_seconds)->toBe(9.0);

    $this->put($url, ['status' => 'revision', 'timestamp_seconds' => null])->assertSessionHasNoErrors();
    expect($review->fresh()->timestamp_seconds)->toBeNull();
});

it('refuses a timestamped review from someone not assigned to the creative', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $creative = makeReviewableCreative($workspace, $owner);
    $this->actingAs(makeMemberWithPermissions($workspace, [Permission::ReviewCreatives->value], 'Creatives'));

    $this->post("/workspaces/{$workspace->slug}/creatives/{$creative->id}/reviews", [
        'status' => 'revision',
        'timestamp_seconds' => 2,
    ])->assertForbidden();
});

it('exposes the timestamp on each review', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeReviewableCreative($workspace, $user);
    CreativeReview::create([
        'creative_id' => $creative->id,
        'reviewer_id' => $user->id,
        'status' => 'revision',
        'timestamp_seconds' => 7.25,
    ]);

    $this->get("/workspaces/{$workspace->slug}/creatives/{$creative->id}/edit")
        ->assertInertia(fn ($page) => $page->where('creative.reviews.0.timestamp_seconds', 7.25));
});

// ─── full-screen review page ────────────────────────────────────────────────

it('renders the full-screen review page with the media and timestamped reviews', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeReviewableCreative($workspace, $user);
    CreativeReview::create([
        'creative_id' => $creative->id,
        'reviewer_id' => $user->id,
        'status' => 'revision',
        'feedback' => 'Hook is slow',
        'timestamp_seconds' => 3,
    ]);

    $this->get("/workspaces/{$workspace->slug}/creatives/{$creative->id}/review")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/creatives/review')
            ->where('creative.id', $creative->id)
            ->has('creative.media')
            ->where('creative.reviews.0.timestamp_seconds', 3)
            ->has('creative.assigned_reviewers', 1));
});

it('requires authentication for the review page', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $creative = makeReviewableCreative($workspace, $user);

    $this->get("/workspaces/{$workspace->slug}/creatives/{$creative->id}/review")
        ->assertRedirect('/login');
});

it('refuses the review page to a member without view access', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $creative = makeReviewableCreative($workspace, $owner);
    $this->actingAs(makeWorkspaceMember($workspace));

    $this->get("/workspaces/{$workspace->slug}/creatives/{$creative->id}/review")->assertForbidden();
});

it('404s the review page for a creative in another workspace', function () {
    ['workspace' => $mine] = actingAsWorkspaceOwner();
    ['user' => $theirUser, 'workspace' => $theirs] = makeWorkspaceWithOwner();
    $creative = makeReviewableCreative($theirs, $theirUser);

    $this->get("/workspaces/{$mine->slug}/creatives/{$creative->id}/review")->assertNotFound();
});

it('hands the review page a signed URL to stream the video straight from the bucket', function () {
    Storage::fake('s3');
    config(['filesystems.creative_media_disk' => 's3']);
    // Stand in for S3's signer; the fake disk can't sign on its own.
    Storage::disk('s3')->buildTemporaryUrlsUsing(fn ($path) => "https://bucket.test/{$path}?X-Amz-Signature=x");

    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeReviewableCreative($workspace, $user);
    $creative->addMedia(UploadedFile::fake()->create('cut.mp4', 100, 'video/mp4'))
        ->toMediaCollection(Creative::MEDIA_COLLECTION);

    $this->get("/workspaces/{$workspace->slug}/creatives/{$creative->id}/review")
        ->assertInertia(fn ($page) => $page->where('creative.media.url', fn ($url) => str_starts_with($url, 'https://bucket.test/')
            // Not the app's own media route — that would put PHP back in front
            // of every range request the player makes.
            && ! str_contains($url, "/creatives/{$creative->id}/media")));
});

it('gives the review page no media when nothing was uploaded', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeReviewableCreative($workspace, $user);

    $this->get("/workspaces/{$workspace->slug}/creatives/{$creative->id}/review")
        ->assertInertia(fn ($page) => $page->where('creative.media', null));
});

it('streams the review video through CloudFront when a distribution is configured', function () {
    Storage::fake('s3');
    config(['filesystems.creative_media_disk' => 's3']);

    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);
    config(['filesystems.creative_cdn' => [
        'url' => 'https://d123.cloudfront.net/',
        'key_pair_id' => 'K2TESTKEY',
        'private_key' => $pem,
    ]]);

    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeReviewableCreative($workspace, $user);
    $media = $creative->addMedia(UploadedFile::fake()->create('final cut.mp4', 100, 'video/mp4'))
        ->toMediaCollection(Creative::MEDIA_COLLECTION);
    // Files adopted from a direct upload keep the browser's name, spaces and all.
    $media->update(['file_name' => 'final cut.mp4']);

    $this->get("/workspaces/{$workspace->slug}/creatives/{$creative->id}/review")
        ->assertInertia(fn ($page) => $page->where('creative.media.url', fn ($url) => str_starts_with($url, "https://d123.cloudfront.net/{$media->id}/final%20cut.mp4?")
            && str_contains($url, 'Key-Pair-Id=K2TESTKEY')
            && str_contains($url, 'Signature=')));

    // The media route (Download aside) and voice messages go through CloudFront too.
    expect($this->get("/workspaces/{$workspace->slug}/creatives/{$creative->id}/media")->headers->get('Location'))
        ->toStartWith('https://d123.cloudfront.net/');

    $review = CreativeReview::create(['creative_id' => $creative->id, 'reviewer_id' => $user->id, 'status' => 'revision']);
    $review->addMedia(UploadedFile::fake()->create('voice.webm', 40, 'audio/webm'))->toMediaCollection(CreativeReview::VOICE_COLLECTION);

    $this->get("/workspaces/{$workspace->slug}/creatives/{$creative->id}/review")
        ->assertInertia(fn ($page) => $page->where('creative.reviews.0.voice.url', fn ($url) => str_starts_with($url, 'https://d123.cloudfront.net/')));
});

it('serves plain CloudFront URLs when the distribution has no key pair', function () {
    Storage::fake('s3');
    config(['filesystems.creative_media_disk' => 's3']);
    config(['filesystems.creative_cdn' => ['url' => 'https://d123.cloudfront.net/', 'key_pair_id' => null, 'private_key' => null]]);

    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeReviewableCreative($workspace, $user);
    $media = $creative->addMedia(UploadedFile::fake()->create('final cut.mp4', 100, 'video/mp4'))
        ->toMediaCollection(Creative::MEDIA_COLLECTION);
    $media->update(['file_name' => 'final cut.mp4']);

    $this->get("/workspaces/{$workspace->slug}/creatives/{$creative->id}/review")
        ->assertInertia(fn ($page) => $page->where('creative.media.url', "https://d123.cloudfront.net/{$media->id}/final%20cut.mp4"));
});

it('signs the same URL for a file all hour, so the browser can cache it', function () {
    Storage::fake('s3');
    config(['filesystems.creative_media_disk' => 's3']);
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);
    config(['filesystems.creative_cdn' => ['url' => 'https://d123.cloudfront.net', 'key_pair_id' => 'K2TESTKEY', 'private_key' => $pem]]);

    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeReviewableCreative($workspace, $user);
    $creative->addMedia(UploadedFile::fake()->create('cut.mp4', 100, 'video/mp4'))->toMediaCollection(Creative::MEDIA_COLLECTION);
    $page = "/workspaces/{$workspace->slug}/creatives/{$creative->id}/review";

    $this->travelTo(now()->startOfHour()->addMinutes(5));
    $first = $this->get($page)->viewData('page')['props']['creative']['media']['url'];

    $this->travelTo(now()->startOfHour()->addMinutes(50));
    $later = $this->get($page)->viewData('page')['props']['creative']['media']['url'];

    expect($later)->toBe($first);
});
