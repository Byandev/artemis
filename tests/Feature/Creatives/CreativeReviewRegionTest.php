<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Creatives\Models\Creative;
use Modules\Creatives\Models\CreativeReview;

beforeEach(function () {
    Storage::fake('s3');
    config(['filesystems.creative_media_disk' => 's3']);
});

function makeImageCreative($workspace, $user, ?string $upload = 'banner.jpg'): Creative
{
    $creative = Creative::create([
        'workspace_id' => $workspace->id,
        'creator_id' => $user->id,
        'name' => 'Image '.uniqid(),
        'creative_date' => '2026-10-05',
        'format' => 'image',
    ]);
    $creative->assignedReviewers()->sync([$user->id]);

    if ($upload) {
        $creative->addMedia(UploadedFile::fake()->image($upload))->toMediaCollection(Creative::MEDIA_COLLECTION);
    }

    return $creative;
}

function reviewUrl($workspace, Creative $creative): string
{
    return "/workspaces/{$workspace->slug}/creatives/{$creative->id}/reviews";
}

it('stores a review pinned to an area of an uploaded image', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeImageCreative($workspace, $user);

    $this->post(reviewUrl($workspace, $creative), [
        'status' => 'revision',
        'feedback' => 'Price is hard to read',
        'region' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.3, 'h' => 0.25],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($creative->reviews()->sole()->region)->toEqual(['x' => 0.1, 'y' => 0.2, 'w' => 0.3, 'h' => 0.25]);
});

it('stores a single point as a zero-size region', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeImageCreative($workspace, $user);

    $this->post(reviewUrl($workspace, $creative), [
        'status' => 'revision',
        'region' => ['x' => 0.5, 'y' => 0.5, 'w' => 0, 'h' => 0],
    ])->assertSessionHasNoErrors();

    expect($creative->reviews()->sole()->region)->toEqual(['x' => 0.5, 'y' => 0.5, 'w' => 0, 'h' => 0]);
});

it('refuses a region on an image creative with no uploaded file', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeImageCreative($workspace, $user, upload: null);

    $this->post(reviewUrl($workspace, $creative), [
        'status' => 'revision',
        'region' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.1, 'h' => 0.1],
    ])->assertSessionHasErrors('region');
});

it('refuses a region on a video creative', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeImageCreative($workspace, $user, upload: null);
    $creative->update(['format' => 'video']);
    $creative->addMedia(UploadedFile::fake()->create('cut.mp4', 100, 'video/mp4'))->toMediaCollection(Creative::MEDIA_COLLECTION);

    $this->post(reviewUrl($workspace, $creative), [
        'status' => 'revision',
        'region' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.1, 'h' => 0.1],
    ])->assertSessionHasErrors('region');
});

it('rejects a region that is malformed or spills off the image', function (array $region) {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeImageCreative($workspace, $user);

    $this->post(reviewUrl($workspace, $creative), [
        'status' => 'revision',
        'region' => $region,
    ])->assertSessionHasErrors();

    expect($creative->reviews()->count())->toBe(0);
})->with([
    'outside 0–1' => [['x' => 1.2, 'y' => 0, 'w' => 0, 'h' => 0]],
    'negative' => [['x' => -0.1, 'y' => 0, 'w' => 0.1, 'h' => 0.1]],
    'spills right' => [['x' => 0.8, 'y' => 0, 'w' => 0.5, 'h' => 0.1]],
    'spills down' => [['x' => 0, 'y' => 0.9, 'w' => 0.1, 'h' => 0.2]],
    'missing h' => [['x' => 0.1, 'y' => 0.1, 'w' => 0.1]],
    'extra key' => [['x' => 0.1, 'y' => 0.1, 'w' => 0.1, 'h' => 0.1, 'evil' => 1]],
]);

it('keeps the region when the author edits the review text from the side panel', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeImageCreative($workspace, $user);
    $review = CreativeReview::create([
        'creative_id' => $creative->id,
        'reviewer_id' => $user->id,
        'status' => 'revision',
        'region' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.2, 'h' => 0.2],
    ]);

    $this->put(reviewUrl($workspace, $creative)."/{$review->id}", [
        'status' => 'approved',
        'feedback' => 'Fixed now',
        'timestamp_seconds' => null,
    ])->assertSessionHasNoErrors();

    expect($review->fresh()->region)->toEqual(['x' => 0.1, 'y' => 0.1, 'w' => 0.2, 'h' => 0.2]);
});

it('exposes the region on the full-screen review page', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeImageCreative($workspace, $user);
    CreativeReview::create([
        'creative_id' => $creative->id,
        'reviewer_id' => $user->id,
        'status' => 'revision',
        'region' => ['x' => 0.25, 'y' => 0.5, 'w' => 0, 'h' => 0],
    ]);

    $this->get("/workspaces/{$workspace->slug}/creatives/{$creative->id}/review")
        ->assertInertia(fn ($page) => $page
            ->where('creative.reviews.0.region.x', 0.25)
            ->where('creative.reviews.0.region.w', 0));
});
