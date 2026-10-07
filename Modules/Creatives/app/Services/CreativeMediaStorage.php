<?php

namespace Modules\Creatives\Services;

use App\Models\Workspace;
use Aws\CloudFront\UrlSigner;
use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Creatives\Models\Creative;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

/**
 * Where creative files live and how the browser reaches them: signed upload
 * URLs, adopting an upload on save, cleaning up unsaved ones, and signed
 * read URLs (through CloudFront when configured).
 */
class CreativeMediaStorage
{
    /**
     * Where a workspace's not-yet-saved uploads live. Swept by
     * creatives:prune-pending-uploads.
     */
    public function pendingPrefix(Workspace $workspace): string
    {
        return "pending/creatives/{$workspace->id}/";
    }

    /**
     * Whether a key the browser sent is one of this workspace's pending
     * uploads. Without this a member could point a creative at — or delete —
     * any object in the bucket, including another workspace's files.
     */
    public function isPendingKeyOf(Workspace $workspace, string $key): bool
    {
        return str_starts_with($key, $this->pendingPrefix($workspace)) && ! str_contains($key, '..');
    }

    /**
     * A URL the browser can PUT a file straight to, so neither nginx nor PHP
     * sees the bytes and a large video has no server-side size ceiling. The key
     * is minted here, never accepted from the client. Null when the disk can't
     * sign an upload (a local disk in development) — the form then posts the
     * file through the app instead.
     *
     * @return array{key: string, url: string, headers: array<string, mixed>}|null
     */
    public function presignUpload(Workspace $workspace, string $fileName, string $contentType): ?array
    {
        $extension = Str::lower(pathinfo($fileName, PATHINFO_EXTENSION)) ?: 'bin';
        $key = $this->pendingPrefix($workspace).Str::uuid().'.'.$extension;

        try {
            // FilesystemAdapter always declares this and throws only when the
            // driver can't, so try it rather than inspect the class.
            $signed = $this->disk()->temporaryUploadUrl($key, Carbon::now()->addMinutes(60), ['ContentType' => $contentType]);
        } catch (RuntimeException) {
            return null;
        }

        return ['key' => $key, 'url' => $signed['url'], 'headers' => $signed['headers']];
    }

    /** Delete an upload that was never saved. Idempotent. */
    public function discardPending(string $key): void
    {
        $this->disk()->delete($key);
    }

    /**
     * Apply the media fields of a create/edit request: adopt an uploaded key,
     * store a file posted through the app, or clear the current file.
     */
    public function attachFromRequest(Request $request, Creative $creative): void
    {
        $key = $request->string('media_key')->toString();

        if ($key !== '') {
            $this->adopt($creative, $key, $request->string('media_name')->toString());

            return;
        }

        if ($request->hasFile('media_file')) {
            // The collection is singleFile(), so this replaces any existing
            // file and deletes the old object from the bucket.
            $creative->addMediaFromRequest('media_file')
                ->toMediaCollection(Creative::MEDIA_COLLECTION);

            return;
        }

        // Clearing without picking a replacement. Checked only when no file
        // was sent, so clearing then choosing a new file still keeps the new one.
        if ($request->boolean('remove_media')) {
            $creative->clearMediaCollection(Creative::MEDIA_COLLECTION);
        }
    }

    /**
     * A signed URL for a file on the creative disk: through CloudFront when a
     * distribution is configured, else straight from the bucket. Null when the
     * disk cannot sign (a local disk in development).
     *
     * Every preview loads from this directly. Pointing a <video> at an app
     * route instead sends each of its many range requests through PHP for a
     * redirect, which stalls playback.
     */
    public function signedUrl(Media $media): ?string
    {
        // Expiry on an hour boundary, 3–4h out: CloudFront's signature depends
        // only on it, so a file's URL stays the same for the hour and the
        // browser can cache it across page loads.
        $expires = Carbon::now()->startOfHour()->addHours(4);

        $cdn = config('filesystems.creative_cdn');

        if (filled($cdn['url'] ?? null) && filled($cdn['key_pair_id'] ?? null) && filled($cdn['private_key'] ?? null)) {
            // The object key, percent-encoded per segment: file names keep
            // the browser's spaces and parentheses.
            $path = collect(explode('/', $media->getPathRelativeToRoot()))->map(rawurlencode(...))->implode('/');

            return (new UrlSigner($cdn['key_pair_id'], $cdn['private_key']))->getSignedUrl(
                rtrim($cdn['url'], '/').'/'.$path,
                $expires->getTimestamp(),
            );
        }

        $disk = Storage::disk($media->disk);

        if (! $disk->providesTemporaryUrls()) {
            return null;
        }

        return $disk->temporaryUrl($media->getPathRelativeToRoot(), $expires);
    }

    /**
     * Adopt a file the browser already uploaded, moving it out of the pending
     * prefix into the collection's own path with a bucket-side copy.
     *
     * Deliberately not addMediaFromDisk(): that streams the object down and
     * uploads it back, which defeats the point of uploading straight to the
     * bucket. See CourseLessonController@attachVideo for the same reasoning.
     */
    private function adopt(Creative $creative, string $key, string $originalName): void
    {
        abort_unless(
            $this->isPendingKeyOf($creative->workspace, $key),
            403,
            'That upload does not belong to this workspace.',
        );

        $diskName = config('filesystems.creative_media_disk');
        $disk = Storage::disk($diskName);

        abort_unless($disk->exists($key), 422, 'The upload could not be found. Please try again.');

        $creative->clearMediaCollection(Creative::MEDIA_COLLECTION);

        // Keep the browser's file name for display/download, but strip anything
        // that could escape the collection's own directory.
        $fileName = Str::limit(basename(str_replace('\\', '/', $originalName ?: $key)), 180, '');

        $media = $creative->media()->create([
            'collection_name' => Creative::MEDIA_COLLECTION,
            'name' => pathinfo($fileName, PATHINFO_FILENAME),
            'file_name' => $fileName,
            'mime_type' => $disk->mimeType($key) ?: 'application/octet-stream',
            'disk' => $diskName,
            'conversions_disk' => $diskName,
            'size' => $disk->size($key),
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
            // Normally stamped by the FileAdder, which is bypassed here.
            'uuid' => (string) Str::uuid(),
        ]);

        // On S3 this is a CopyObject: the bytes never leave the bucket.
        $disk->copy($key, PathGeneratorFactory::create($media)->getPath($media).$fileName);
        $disk->delete($key);

        $creative->unsetRelation('media');
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.creative_media_disk'));
    }
}
