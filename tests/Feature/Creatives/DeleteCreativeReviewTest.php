<?php

use App\Enums\Permission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Creatives\Models\Creative;
use Modules\Creatives\Models\CreativeReview;

beforeEach(function () {
    Storage::fake('s3');
    config(['filesystems.creative_media_disk' => 's3']);
});

function makeDeletableCreative($workspace, $user, string $format = 'video'): Creative
{
    return Creative::create([
        'workspace_id' => $workspace->id,
        'creator_id' => $user->id,
        'name' => 'Deletable '.uniqid(),
        'creative_date' => '2026-10-06',
        'format' => $format,
    ]);
}

function makeOwnReview(Creative $creative, $user, array $attributes = []): CreativeReview
{
    return CreativeReview::create([
        'creative_id' => $creative->id,
        'reviewer_id' => $user->id,
        'status' => 'revision',
        ...$attributes,
    ]);
}

function deleteReviewUrl($workspace, Creative $creative, CreativeReview $review): string
{
    return "/workspaces/{$workspace->slug}/creatives/{$creative->id}/reviews/{$review->id}";
}

it('deletes the reviewer\'s own timestamped video review', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeDeletableCreative($workspace, $user);
    $review = makeOwnReview($creative, $user, ['timestamp_seconds' => 5]);

    $this->delete(deleteReviewUrl($workspace, $creative, $review))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(CreativeReview::find($review->id))->toBeNull();
});

it('deletes the reviewer\'s own area review on an image', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeDeletableCreative($workspace, $user, 'image');
    $review = makeOwnReview($creative, $user, ['region' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.2, 'h' => 0.2]]);

    $this->delete(deleteReviewUrl($workspace, $creative, $review))->assertRedirect();

    expect(CreativeReview::find($review->id))->toBeNull();
});

it('takes the voice message out of the bucket with the review', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeDeletableCreative($workspace, $user);
    $review = makeOwnReview($creative, $user);
    $path = $review->addMedia(UploadedFile::fake()->create('voice.webm', 40, 'audio/webm'))
        ->toMediaCollection(CreativeReview::VOICE_COLLECTION)
        ->getPathRelativeToRoot();

    $this->delete(deleteReviewUrl($workspace, $creative, $review))->assertRedirect();

    expect(Storage::disk('s3')->exists($path))->toBeFalse();
});

it('leaves the creative\'s other reviews alone', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeDeletableCreative($workspace, $user);
    $doomed = makeOwnReview($creative, $user);
    $kept = makeOwnReview($creative, $user, ['status' => 'approved']);

    $this->delete(deleteReviewUrl($workspace, $creative, $doomed))->assertRedirect();

    expect($creative->reviews()->pluck('id')->all())->toBe([$kept->id]);
});

it('refuses to delete someone else\'s review', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $creative = makeDeletableCreative($workspace, $owner);
    $review = makeOwnReview($creative, $owner);
    $this->actingAs(makeMemberWithPermissions($workspace, [Permission::ReviewCreatives->value], 'Creatives'));

    $this->delete(deleteReviewUrl($workspace, $creative, $review))->assertForbidden();

    expect(CreativeReview::find($review->id))->not->toBeNull();
});

it('refuses a member without the review permission, even on their own review', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $creative = makeDeletableCreative($workspace, $owner);
    $member = makeMemberWithPermissions($workspace, [Permission::ViewCreatives->value], 'Creatives');
    $review = makeOwnReview($creative, $member);
    $this->actingAs($member);

    $this->delete(deleteReviewUrl($workspace, $creative, $review))->assertForbidden();
});

it('404s a review that belongs to another creative', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeDeletableCreative($workspace, $user);
    $review = makeOwnReview(makeDeletableCreative($workspace, $user), $user);

    $this->delete(deleteReviewUrl($workspace, $creative, $review))->assertNotFound();

    expect(CreativeReview::find($review->id))->not->toBeNull();
});

it('404s a creative in another workspace', function () {
    ['workspace' => $mine] = actingAsWorkspaceOwner();
    ['user' => $theirUser, 'workspace' => $theirs] = makeWorkspaceWithOwner();
    $creative = makeDeletableCreative($theirs, $theirUser);
    $review = makeOwnReview($creative, $theirUser);

    $this->delete(deleteReviewUrl($mine, $creative, $review))->assertNotFound();
});

it('refuses a non-member', function () {
    actingAsWorkspaceOwner();
    ['user' => $theirUser, 'workspace' => $theirs] = makeWorkspaceWithOwner();
    $creative = makeDeletableCreative($theirs, $theirUser);
    $review = makeOwnReview($creative, $theirUser);

    $this->delete(deleteReviewUrl($theirs, $creative, $review))->assertForbidden();
});

it('requires authentication', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $creative = makeDeletableCreative($workspace, $owner);
    $review = makeOwnReview($creative, $owner);

    $this->delete(deleteReviewUrl($workspace, $creative, $review))->assertRedirect('/login');
});
