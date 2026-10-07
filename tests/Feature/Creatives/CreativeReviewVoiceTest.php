<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Creatives\Models\Creative;
use Modules\Creatives\Models\CreativeReview;

beforeEach(function () {
    Storage::fake('s3');
    config(['filesystems.creative_media_disk' => 's3']);
});

function makeVoiceCreative($workspace, $user): Creative
{
    $creative = Creative::create([
        'workspace_id' => $workspace->id,
        'creator_id' => $user->id,
        'name' => 'Voice '.uniqid(),
        'creative_date' => '2026-10-05',
        'format' => 'video',
    ]);
    $creative->assignedReviewers()->sync([$user->id]);

    return $creative;
}

function makeVoiceReview(Creative $creative, $user): CreativeReview
{
    $review = CreativeReview::create([
        'creative_id' => $creative->id,
        'reviewer_id' => $user->id,
        'status' => 'revision',
    ]);
    $review->addMedia(UploadedFile::fake()->create('voice.webm', 40, 'audio/webm'))
        ->withCustomProperties(['duration_seconds' => 12])
        ->toMediaCollection(CreativeReview::VOICE_COLLECTION);

    return $review;
}

it('stores a review that is only a voice message', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeVoiceCreative($workspace, $user);

    $this->post("/workspaces/{$workspace->slug}/creatives/{$creative->id}/reviews", [
        'status' => 'revision',
        'voice' => UploadedFile::fake()->create('voice-1.webm', 40, 'audio/webm'),
        'voice_duration_seconds' => 9,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $voice = $creative->reviews()->sole()->voice();

    expect($voice)->not->toBeNull()
        ->and($voice->disk)->toBe('s3')
        ->and($voice->getCustomProperty('duration_seconds'))->toBe(9)
        ->and(Storage::disk('s3')->exists($voice->getPathRelativeToRoot()))->toBeTrue();
});

it('stores text and a voice message together, pinned to a timestamp', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeVoiceCreative($workspace, $user);

    $this->post("/workspaces/{$workspace->slug}/creatives/{$creative->id}/reviews", [
        'status' => 'revision',
        'feedback' => 'Listen to the note',
        'timestamp_seconds' => 4,
        'voice' => UploadedFile::fake()->create('voice-1.m4a', 40, 'audio/mp4'),
    ])->assertSessionHasNoErrors();

    $review = $creative->reviews()->sole();

    expect($review->feedback)->toBe('Listen to the note')
        ->and($review->timestamp_seconds)->toBe(4.0)
        ->and($review->voice())->not->toBeNull();
});

it('accepts the video/webm that Chrome audio recordings sniff as', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeVoiceCreative($workspace, $user);

    $this->post("/workspaces/{$workspace->slug}/creatives/{$creative->id}/reviews", [
        'status' => 'revision',
        'voice' => UploadedFile::fake()->create('voice-1.webm', 40, 'video/webm'),
    ])->assertSessionHasNoErrors();
});

it('rejects a voice message that is not audio, or is too large', function (UploadedFile $file) {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeVoiceCreative($workspace, $user);

    $this->post("/workspaces/{$workspace->slug}/creatives/{$creative->id}/reviews", [
        'status' => 'revision',
        'voice' => $file,
    ])->assertSessionHasErrors('voice');

    expect($creative->reviews()->count())->toBe(0);
})->with([
    'pdf' => fn () => UploadedFile::fake()->create('notes.pdf', 40, 'application/pdf'),
    'over 20 MB' => fn () => UploadedFile::fake()->create('long.webm', 20481, 'audio/webm'),
]);

it('keeps the voice message when the review text is edited', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeVoiceCreative($workspace, $user);
    $review = makeVoiceReview($creative, $user);

    $this->put("/workspaces/{$workspace->slug}/creatives/{$creative->id}/reviews/{$review->id}", [
        'status' => 'approved',
        'feedback' => 'All good now',
    ])->assertSessionHasNoErrors();

    expect($review->fresh()->voice())->not->toBeNull();
});

it('exposes each voice message with a signed URL to play from directly', function () {
    Storage::disk('s3')->buildTemporaryUrlsUsing(fn ($path) => "https://bucket.test/{$path}?X-Amz-Signature=x");
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeVoiceCreative($workspace, $user);
    makeVoiceReview($creative, $user);

    $this->get("/workspaces/{$workspace->slug}/creatives/{$creative->id}/review")
        ->assertInertia(fn ($page) => $page
            ->where('creative.reviews.0.voice.duration_seconds', 12)
            ->where('creative.reviews.0.voice.url', fn ($url) => str_starts_with($url, 'https://bucket.test/')));
});

it('falls back to the voice route when the disk cannot sign', function () {
    useUnsignableCreativeDisk();
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeVoiceCreative($workspace, $user);
    $review = makeVoiceReview($creative, $user);

    $this->get("/workspaces/{$workspace->slug}/creatives/{$creative->id}/review")
        ->assertInertia(fn ($page) => $page->where(
            'creative.reviews.0.voice.url',
            "/workspaces/{$workspace->slug}/creatives/{$creative->id}/reviews/{$review->id}/voice",
        ));
});

it('deletes voice messages from the bucket when the creative is deleted', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeVoiceCreative($workspace, $user);
    $path = makeVoiceReview($creative, $user)->voice()->getPathRelativeToRoot();

    $this->delete("/workspaces/{$workspace->slug}/creatives/{$creative->id}")->assertRedirect();

    expect(Storage::disk('s3')->exists($path))->toBeFalse()
        ->and(CreativeReview::where('creative_id', $creative->id)->count())->toBe(0);
});

// ─── playback route ─────────────────────────────────────────────────────────

it('redirects to a signed URL for a voice message', function () {
    Storage::disk('s3')->buildTemporaryUrlsUsing(fn ($path) => "https://bucket.test/{$path}?X-Amz-Signature=x");
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeVoiceCreative($workspace, $user);
    $review = makeVoiceReview($creative, $user);

    $location = $this->get("/workspaces/{$workspace->slug}/creatives/{$creative->id}/reviews/{$review->id}/voice")
        ->assertRedirect()
        ->headers->get('Location');

    expect($location)->toStartWith('https://bucket.test/');
});

it('404s the voice route for a review with no voice message', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeVoiceCreative($workspace, $user);
    $review = CreativeReview::create(['creative_id' => $creative->id, 'reviewer_id' => $user->id, 'status' => 'approved']);

    $this->get("/workspaces/{$workspace->slug}/creatives/{$creative->id}/reviews/{$review->id}/voice")->assertNotFound();
});

it('404s the voice route for a review that belongs to another creative', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $mine = makeVoiceCreative($workspace, $user);
    $review = makeVoiceReview(makeVoiceCreative($workspace, $user), $user);

    $this->get("/workspaces/{$workspace->slug}/creatives/{$mine->id}/reviews/{$review->id}/voice")->assertNotFound();
});

it('404s the voice route for a creative in another workspace', function () {
    ['workspace' => $mine] = actingAsWorkspaceOwner();
    ['user' => $theirUser, 'workspace' => $theirs] = makeWorkspaceWithOwner();
    $creative = makeVoiceCreative($theirs, $theirUser);
    $review = makeVoiceReview($creative, $theirUser);

    $this->get("/workspaces/{$mine->slug}/creatives/{$creative->id}/reviews/{$review->id}/voice")->assertNotFound();
});

it('refuses the voice route to a member without view access', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $creative = makeVoiceCreative($workspace, $owner);
    $review = makeVoiceReview($creative, $owner);
    $this->actingAs(makeWorkspaceMember($workspace));

    $this->get("/workspaces/{$workspace->slug}/creatives/{$creative->id}/reviews/{$review->id}/voice")->assertForbidden();
});

it('requires authentication for the voice route', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $creative = makeVoiceCreative($workspace, $owner);
    $review = makeVoiceReview($creative, $owner);

    $this->get("/workspaces/{$workspace->slug}/creatives/{$creative->id}/reviews/{$review->id}/voice")->assertRedirect('/login');
});
