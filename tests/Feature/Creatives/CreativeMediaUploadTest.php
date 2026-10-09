<?php

use App\Enums\Permission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Creatives\Models\Creative;
use Modules\Products\Models\Product;

beforeEach(function () {
    Storage::fake('s3');
    config(['filesystems.creative_media_disk' => 's3']);
});

function creativePayload($workspace, array $overrides = []): array
{
    $product = Product::factory()->create(['workspace_id' => $workspace->id, 'owner_id' => $workspace->owner_id]);

    return array_merge([
        'name' => 'Upload '.Str::random(6),
        'creative_date' => '2026-10-05',
        'format' => 'video',
        'product_id' => $product->id,
        'caption' => 'Caption',
        'headline' => 'Headline',
    ], $overrides);
}

function pendingCreativeKey($workspace, string $ext = 'mp4'): string
{
    $key = "pending/creatives/{$workspace->id}/".Str::uuid().'.'.$ext;
    Storage::disk('s3')->put($key, 'fake-bytes');

    return $key;
}

function makeUploadCreative($workspace, $user, array $attributes = []): Creative
{
    return Creative::create(array_merge([
        'workspace_id' => $workspace->id,
        'creator_id' => $user->id,
        'name' => 'Existing '.Str::random(6),
        'creative_date' => '2026-10-05',
        'format' => 'image',
    ], $attributes));
}

// ─── presign ────────────────────────────────────────────────────────────────

it('signs an upload scoped to the workspace', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $response = $this->postJson("/workspaces/{$workspace->slug}/creatives/media/presign", [
        'file_name' => 'Ad Cut.MP4',
        'content_type' => 'video/mp4',
    ])->assertOk()->assertJsonStructure(['supported', 'key', 'url', 'headers']);

    expect($response->json('supported'))->toBeTrue()
        ->and($response->json('key'))
        ->toStartWith("pending/creatives/{$workspace->id}/")
        ->toEndWith('.mp4');
});

it('refuses to sign an upload that is not an image or video', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $this->postJson("/workspaces/{$workspace->slug}/creatives/media/presign", [
        'file_name' => 'notes.pdf',
        'content_type' => 'application/pdf',
    ])->assertStatus(422)->assertJsonValidationErrors('content_type');
});

it('requires authentication to sign an upload', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->postJson("/workspaces/{$workspace->slug}/creatives/media/presign", [
        'file_name' => 'a.jpg',
        'content_type' => 'image/jpeg',
    ])->assertUnauthorized();
});

it('refuses to sign an upload for a member who can neither create nor edit creatives', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs(makeMemberWithPermissions($workspace, [Permission::ViewCreatives->value], 'Creatives'));

    $this->postJson("/workspaces/{$workspace->slug}/creatives/media/presign", [
        'file_name' => 'a.jpg',
        'content_type' => 'image/jpeg',
    ])->assertForbidden();
});

it('signs an upload for a member who can only edit creatives', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs(makeMemberWithPermissions($workspace, [Permission::EditCreatives->value], 'Creatives'));

    $this->postJson("/workspaces/{$workspace->slug}/creatives/media/presign", [
        'file_name' => 'a.jpg',
        'content_type' => 'image/jpeg',
    ])->assertOk();
});

it('refuses to sign an upload for a non-member', function () {
    actingAsWorkspaceOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();

    $this->postJson("/workspaces/{$other->slug}/creatives/media/presign", [
        'file_name' => 'a.jpg',
        'content_type' => 'image/jpeg',
    ])->assertForbidden();
});

// ─── store ──────────────────────────────────────────────────────────────────

it('creates a creative from a file already uploaded to the bucket, with no link', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $key = pendingCreativeKey($workspace);

    $this->post("/workspaces/{$workspace->slug}/creatives", creativePayload($workspace, [
        'media_key' => $key,
        'media_name' => 'Hook v2.mp4',
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $media = Creative::where('workspace_id', $workspace->id)->sole()->mediaFile();

    expect($media)->not->toBeNull()
        ->and($media->disk)->toBe('s3')
        ->and($media->file_name)->toBe('Hook v2.mp4')
        ->and(Storage::disk('s3')->exists($media->getPathRelativeToRoot()))->toBeTrue()
        // Moved out of the pending prefix, not left behind.
        ->and(Storage::disk('s3')->exists($key))->toBeFalse();
});

it('creates a creative from a file posted through the app when the disk cannot sign', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $this->post("/workspaces/{$workspace->slug}/creatives", creativePayload($workspace, [
        'format' => 'image',
        'media_file' => UploadedFile::fake()->image('static.jpg'),
    ]))->assertRedirect()->assertSessionHasNoErrors();

    expect(Creative::where('workspace_id', $workspace->id)->sole()->mediaFile()?->file_name)->toBe('static.jpg');
});

it('still accepts a link with no upload', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $this->post("/workspaces/{$workspace->slug}/creatives", creativePayload($workspace, [
        'picture_url' => 'https://drive.google.com/file/d/abc',
    ]))->assertRedirect()->assertSessionHasNoErrors();

    expect(Creative::where('workspace_id', $workspace->id)->sole()->mediaFile())->toBeNull();
});

it('requires either a link or an upload', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $this->post("/workspaces/{$workspace->slug}/creatives", creativePayload($workspace))
        ->assertSessionHasErrors('picture_url');

    expect(Creative::where('workspace_id', $workspace->id)->count())->toBe(0);
});

it('refuses an upload key from another workspace', function () {
    ['workspace' => $mine] = actingAsWorkspaceOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();
    $foreign = pendingCreativeKey($other);

    $this->post("/workspaces/{$mine->slug}/creatives", creativePayload($mine, [
        'media_key' => $foreign,
    ]))->assertForbidden();

    expect(Storage::disk('s3')->exists($foreign))->toBeTrue();
});

it('rejects an upload key whose object is not in the bucket', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $this->post("/workspaces/{$workspace->slug}/creatives", creativePayload($workspace, [
        'media_key' => "pending/creatives/{$workspace->id}/".Str::uuid().'.mp4',
    ]))->assertStatus(422);
});

it('refuses a file that does not match the creative format', function (string $format, string $ext) {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $this->post("/workspaces/{$workspace->slug}/creatives", creativePayload($workspace, [
        'format' => $format,
        'media_key' => pendingCreativeKey($workspace, $ext),
    ]))->assertSessionHasErrors('media_key');

    expect(Creative::where('workspace_id', $workspace->id)->count())->toBe(0);
})->with([
    'image key on a video creative' => ['video', 'jpg'],
    'video key on an image creative' => ['image', 'mp4'],
]);

it('refuses a posted image on a video creative', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $this->post("/workspaces/{$workspace->slug}/creatives", creativePayload($workspace, [
        'format' => 'video',
        'media_file' => UploadedFile::fake()->image('static.jpg'),
    ]))->assertSessionHasErrors('media_file');

    expect(Creative::where('workspace_id', $workspace->id)->count())->toBe(0);
});

// ─── update ─────────────────────────────────────────────────────────────────

it('replaces the uploaded file when a new key is sent on update', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeUploadCreative($workspace, $user);
    $creative->addMedia(UploadedFile::fake()->image('old.jpg'))->toMediaCollection(Creative::MEDIA_COLLECTION);

    $this->put("/workspaces/{$workspace->slug}/creatives/{$creative->id}", [
        'media_key' => pendingCreativeKey($workspace, 'jpg'),
        'media_name' => 'new.jpg',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($creative->fresh()->getMedia(Creative::MEDIA_COLLECTION))->toHaveCount(1)
        ->and($creative->fresh()->mediaFile()->file_name)->toBe('new.jpg');
});

it('checks a new upload against the saved format when the update leaves it out', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeUploadCreative($workspace, $user, ['format' => 'image']);

    $this->put("/workspaces/{$workspace->slug}/creatives/{$creative->id}", [
        'media_key' => pendingCreativeKey($workspace, 'mp4'),
        'media_name' => 'clip.mp4',
    ])->assertSessionHasErrors('media_key');

    expect($creative->fresh()->mediaFile())->toBeNull();
});

it('removes the uploaded file when a link remains', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeUploadCreative($workspace, $user, ['picture_url' => 'https://drive.google.com/x']);
    $creative->addMedia(UploadedFile::fake()->image('old.jpg'))->toMediaCollection(Creative::MEDIA_COLLECTION);

    $this->put("/workspaces/{$workspace->slug}/creatives/{$creative->id}", [
        'remove_media' => true,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($creative->fresh()->mediaFile())->toBeNull();
});

it('refuses to remove the last piece of media on update', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeUploadCreative($workspace, $user);
    $creative->addMedia(UploadedFile::fake()->image('only.jpg'))->toMediaCollection(Creative::MEDIA_COLLECTION);

    $this->put("/workspaces/{$workspace->slug}/creatives/{$creative->id}", [
        'picture_url' => '',
        'remove_media' => true,
    ])->assertSessionHasErrors('picture_url');

    expect($creative->fresh()->mediaFile())->not->toBeNull();
});

it('lets a creative with an uploaded file drop its link', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeUploadCreative($workspace, $user, ['picture_url' => 'https://drive.google.com/x']);
    $creative->addMedia(UploadedFile::fake()->image('keep.jpg'))->toMediaCollection(Creative::MEDIA_COLLECTION);

    $this->put("/workspaces/{$workspace->slug}/creatives/{$creative->id}", [
        'picture_url' => '',
    ])->assertSessionHasNoErrors();

    expect($creative->fresh()->picture_url)->toBeNull();
});

it('deletes the uploaded file from the bucket when the creative is deleted', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeUploadCreative($workspace, $user);
    $media = $creative->addMedia(UploadedFile::fake()->image('gone.jpg'))->toMediaCollection(Creative::MEDIA_COLLECTION);
    $path = $media->getPathRelativeToRoot();

    $this->delete("/workspaces/{$workspace->slug}/creatives/{$creative->id}")->assertRedirect();

    expect(Storage::disk('s3')->exists($path))->toBeFalse();
});

// ─── show ───────────────────────────────────────────────────────────────────

it('hands every page a signed URL to preview the file from directly', function () {
    Storage::disk('s3')->buildTemporaryUrlsUsing(fn ($path) => "https://bucket.test/{$path}?X-Amz-Signature=x");
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeUploadCreative($workspace, $user);
    $creative->addMedia(UploadedFile::fake()->image('see.jpg'))->toMediaCollection(Creative::MEDIA_COLLECTION);

    // Straight to the bucket — never the app's own route, which would put PHP
    // in front of every range request a <video> makes.
    $signed = fn ($url) => str_starts_with($url, 'https://bucket.test/') && ! str_contains($url, '/media');

    $this->get("/workspaces/{$workspace->slug}/creatives")
        ->assertInertia(fn ($page) => $page->where('creatives.data.0.media.url', $signed));

    $this->get("/workspaces/{$workspace->slug}/creatives/{$creative->id}/edit")
        ->assertInertia(fn ($page) => $page
            ->where('creative.media.file_name', 'see.jpg')
            ->where('creative.media.url', $signed));

    $this->get("/workspaces/{$workspace->slug}/creatives/{$creative->id}/review")
        ->assertInertia(fn ($page) => $page->where('creative.media.url', $signed));
});

it('falls back to the app route when the disk cannot sign', function () {
    useUnsignableCreativeDisk();
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeUploadCreative($workspace, $user);
    $creative->addMedia(UploadedFile::fake()->image('local.jpg'))->toMediaCollection(Creative::MEDIA_COLLECTION);

    $this->get("/workspaces/{$workspace->slug}/creatives/{$creative->id}/edit")
        ->assertInertia(fn ($page) => $page
            ->where('creative.media.url', "/workspaces/{$workspace->slug}/creatives/{$creative->id}/media"));

    // …and that route serves the bytes itself.
    $this->get("/workspaces/{$workspace->slug}/creatives/{$creative->id}/media")->assertOk();
});

it('redirects the media route to a signed URL, and to S3 with an attachment header for downloads', function () {
    Storage::disk('s3')->buildTemporaryUrlsUsing(
        fn ($path, $expiration, $options) => "https://bucket.test/{$path}?".http_build_query($options)
    );
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeUploadCreative($workspace, $user);
    $creative->addMedia(UploadedFile::fake()->image('dl.jpg'))->toMediaCollection(Creative::MEDIA_COLLECTION);
    $url = "/workspaces/{$workspace->slug}/creatives/{$creative->id}/media";

    expect($this->get($url)->assertRedirect()->headers->get('Location'))
        ->toStartWith('https://bucket.test/');

    expect(urldecode($this->get("{$url}?download=1")->assertRedirect()->headers->get('Location')))
        ->toContain('attachment; filename="dl.jpg"');
});

it('404s the media route when nothing was uploaded', function () {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    $creative = makeUploadCreative($workspace, $user);

    $this->get("/workspaces/{$workspace->slug}/creatives/{$creative->id}/media")->assertNotFound();
});

it('404s the media route for a creative in another workspace', function () {
    ['workspace' => $mine] = actingAsWorkspaceOwner();
    ['user' => $theirUser, 'workspace' => $theirs] = makeWorkspaceWithOwner();
    $creative = makeUploadCreative($theirs, $theirUser);
    $creative->addMedia(UploadedFile::fake()->image('secret.jpg'))->toMediaCollection(Creative::MEDIA_COLLECTION);

    $this->get("/workspaces/{$mine->slug}/creatives/{$creative->id}/media")->assertNotFound();
});

it('refuses the media route to a member without view access', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $creative = makeUploadCreative($workspace, $owner);
    $creative->addMedia(UploadedFile::fake()->image('x.jpg'))->toMediaCollection(Creative::MEDIA_COLLECTION);
    $this->actingAs(makeWorkspaceMember($workspace));

    $this->get("/workspaces/{$workspace->slug}/creatives/{$creative->id}/media")->assertForbidden();
});

// ─── prune ──────────────────────────────────────────────────────────────────

it('prunes abandoned pending uploads but leaves fresh ones', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $old = pendingCreativeKey($workspace);
    $fresh = pendingCreativeKey($workspace);
    // The fake disk is a real directory, so age is the file's own mtime.
    touch(Storage::disk('s3')->path($old), now()->subHours(25)->getTimestamp());

    $this->artisan('creatives:prune-pending-uploads')->assertSuccessful();

    expect(Storage::disk('s3')->exists($old))->toBeFalse()
        ->and(Storage::disk('s3')->exists($fresh))->toBeTrue();
});

// ─── discard pending upload ─────────────────────────────────────────────────

it('deletes an unsaved upload when the form is abandoned', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $key = pendingCreativeKey($workspace);

    $this->deleteJson("/workspaces/{$workspace->slug}/creatives/media/pending", ['key' => $key])
        ->assertNoContent();

    expect(Storage::disk('s3')->exists($key))->toBeFalse();
});

it('is a no-op for a pending upload that is already gone', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    // The tab-close request and the form's own cleanup can both fire.
    $this->deleteJson("/workspaces/{$workspace->slug}/creatives/media/pending", [
        'key' => "pending/creatives/{$workspace->id}/".Str::uuid().'.mp4',
    ])->assertNoContent();
});

it('refuses to delete anything outside the workspace\'s pending uploads', function (string $case) {
    ['user' => $user, 'workspace' => $workspace] = actingAsWorkspaceOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();

    $target = match ($case) {
        'another workspace' => "pending/creatives/{$other->id}/".Str::uuid().'.mp4',
        'saved file' => makeUploadCreative($workspace, $user)
            ->addMedia(UploadedFile::fake()->image('saved.jpg'))->toMediaCollection(Creative::MEDIA_COLLECTION)
            ->getPathRelativeToRoot(),
        'path traversal' => "pending/creatives/{$workspace->id}/../../1/saved.jpg",
        'course upload' => "pending/course-covers/{$workspace->id}/x.jpg",
    };
    if (! Storage::disk('s3')->exists($target)) {
        Storage::disk('s3')->put($target, 'keep me');
    }

    $this->deleteJson("/workspaces/{$workspace->slug}/creatives/media/pending", ['key' => $target])
        ->assertForbidden();

    expect(Storage::disk('s3')->exists($target))->toBeTrue();
})->with([
    'another workspace\'s pending upload' => ['another workspace'],
    'a saved creative\'s file' => ['saved file'],
    'path traversal out of the prefix' => ['path traversal'],
    'a course upload' => ['course upload'],
]);

it('requires a key', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();

    $this->deleteJson("/workspaces/{$workspace->slug}/creatives/media/pending", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('key');
});

it('refuses a member who can neither create nor edit creatives', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs(makeMemberWithPermissions($workspace, [Permission::ViewCreatives->value], 'Creatives'));
    $key = pendingCreativeKey($workspace);

    $this->deleteJson("/workspaces/{$workspace->slug}/creatives/media/pending", ['key' => $key])
        ->assertForbidden();

    expect(Storage::disk('s3')->exists($key))->toBeTrue();
});

it('refuses a non-member', function () {
    actingAsWorkspaceOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();
    $key = pendingCreativeKey($other);

    $this->deleteJson("/workspaces/{$other->slug}/creatives/media/pending", ['key' => $key])
        ->assertForbidden();

    expect(Storage::disk('s3')->exists($key))->toBeTrue();
});

it('requires authentication to discard an upload', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->deleteJson("/workspaces/{$workspace->slug}/creatives/media/pending", ['key' => 'x'])
        ->assertUnauthorized();
});
