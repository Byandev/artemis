<?php

namespace Modules\Creatives\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Creatives\Http\Controllers\Concerns\GuardsCreatives;
use Modules\Creatives\Models\Creative;
use Modules\Creatives\Services\CreativeMediaStorage;

/**
 * A creative's uploaded file: signing the upload, discarding one that was
 * never saved, and serving the saved file.
 */
class CreativeMediaController extends Controller
{
    use AuthorizesRequests, GuardsCreatives;

    public function __construct(private CreativeMediaStorage $storage) {}

    /**
     * Sign an upload. Scoped to the workspace rather than a creative: the
     * create form needs a key before the creative exists.
     */
    public function presign(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorizeUpload($request, $workspace);

        $validated = $request->validate([
            'file_name' => ['required', 'string', 'max:255'],
            'content_type' => ['required', 'string', 'regex:/^(image|video)\//'],
        ]);

        $signed = $this->storage->presignUpload($workspace, $validated['file_name'], $validated['content_type']);

        return response()->json($signed ? ['supported' => true, ...$signed] : ['supported' => false]);
    }

    /**
     * Delete an upload that was never saved — the user swapped it for another
     * file, cleared it, or left the form. Only this workspace's pending uploads
     * can be touched, so a saved creative's file (moved out of that prefix on
     * save) is never at risk. Anything this misses — a crashed tab, a request
     * that never landed — is swept by creatives:prune-pending-uploads.
     */
    public function discardPending(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorizeUpload($request, $workspace);

        $key = $request->validate(['key' => ['required', 'string', 'max:512']])['key'];

        abort_unless($this->storage->isPendingKeyOf($workspace, $key), 403, 'That upload does not belong to this workspace.');

        // Idempotent: the tab-close request and the form's own cleanup can both
        // fire for the same key.
        $this->storage->discardPending($key);

        return response()->noContent();
    }

    /**
     * Serve a creative's uploaded file: the Download link, and the fallback
     * for a preview whose signed URL has expired (pages load signed URLs
     * directly). Redirects to a signed URL, or streams the bytes when the disk
     * cannot sign (a local disk in development).
     */
    public function show(Request $request, Workspace $workspace, Creative $creative)
    {
        $this->guard($request, $workspace, $creative);
        $this->authorize(Permission::ViewCreatives->value, $workspace);

        $media = $creative->mediaFile();

        abort_unless($media, 404, 'No file uploaded for this creative.');

        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();

        if ($request->boolean('download')) {
            // A download needs S3's own content-disposition override, which a
            // CloudFront URL can't carry; it's a single request, so S3 is fine.
            return $disk->providesTemporaryUrls()
                ? redirect()->away($disk->temporaryUrl($path, Carbon::now()->addMinutes(30), [
                    'ResponseContentDisposition' => 'attachment; filename="'.addslashes($media->file_name).'"',
                ]))
                : $disk->download($path, $media->file_name);
        }

        $url = $this->storage->signedUrl($media);

        return $url ? redirect()->away($url) : $disk->response($path);
    }

    /** Both the create and the edit form upload, so either permission will do. */
    private function authorizeUpload(Request $request, Workspace $workspace): void
    {
        abort_unless(
            $request->user()->can(Permission::CreateCreatives->value, $workspace)
                || $request->user()->can(Permission::EditCreatives->value, $workspace),
            403,
        );
    }
}
