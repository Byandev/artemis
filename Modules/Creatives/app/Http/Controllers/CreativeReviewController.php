<?php

namespace Modules\Creatives\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Modules\Creatives\Http\Controllers\Concerns\GuardsCreatives;
use Modules\Creatives\Http\Presenters\CreativePresenter;
use Modules\Creatives\Http\Requests\StoreReviewRequest;
use Modules\Creatives\Models\Creative;
use Modules\Creatives\Models\CreativeReview;
use Modules\Creatives\Services\CreativeMediaStorage;

/**
 * Reviews on a creative — text, a voice message, or both, optionally pinned to
 * a second of a video or an area of an image — and the full-screen page they're
 * left from.
 */
class CreativeReviewController extends Controller
{
    use AuthorizesRequests, GuardsCreatives;

    /** Request fields that go to the voice media collection, not a column. */
    private const VOICE_FIELDS = ['voice', 'voice_duration_seconds'];

    public function __construct(
        private CreativePresenter $presenter,
        private CreativeMediaStorage $storage,
    ) {}

    /**
     * Full-screen review page: the media large on one side, the reviews on the
     * other, ordered by the moment or area each one points at.
     */
    public function show(Request $request, Workspace $workspace, Creative $creative)
    {
        $this->guard($request, $workspace, $creative);
        $this->authorize(Permission::ViewCreatives->value, $workspace);

        $creative->load(CreativePresenter::relations());

        return Inertia::render('workspaces/creatives/review', [
            'workspace' => $workspace,
            'creative' => $this->presenter->present($creative, $workspace),
        ]);
    }

    public function store(StoreReviewRequest $request, Workspace $workspace, Creative $creative)
    {
        $this->guard($request, $workspace, $creative);
        $this->authorize(Permission::ReviewCreatives->value, $workspace);

        // Having the permission is not enough — only reviewers assigned to this
        // specific creative may review it.
        if (! $creative->assignedReviewers()->whereKey($request->user()->id)->exists()) {
            abort(403, 'You are not an assigned reviewer for this creative.');
        }

        $review = CreativeReview::create([
            'creative_id' => $creative->id,
            'reviewer_id' => $request->user()->id,
            ...collect($request->validated())->except(self::VOICE_FIELDS)->all(),
        ]);

        if ($request->hasFile('voice')) {
            $review->addMediaFromRequest('voice')
                // Measured by the recorder; the server never decodes the audio.
                ->withCustomProperties(['duration_seconds' => $request->integer('voice_duration_seconds') ?: null])
                ->toMediaCollection(CreativeReview::VOICE_COLLECTION);
        }

        return back();
    }

    public function update(StoreReviewRequest $request, Workspace $workspace, Creative $creative, CreativeReview $review)
    {
        $this->authorizeAuthor($request, $workspace, $creative, $review, 'You can only edit your own reviews.');

        // A voice message is kept as recorded; edits change the text, status
        // and pin only.
        $review->update(collect($request->validated())->except(self::VOICE_FIELDS)->all());

        return back();
    }

    /**
     * Delete a review — timestamped, area-marked or whole-creative alike.
     * Deleting the model (not a query delete) takes its voice message out of
     * the bucket too.
     */
    public function destroy(Request $request, Workspace $workspace, Creative $creative, CreativeReview $review)
    {
        $this->authorizeAuthor($request, $workspace, $creative, $review, 'You can only delete your own reviews.');

        $review->delete();

        return back()->with('success', 'Review deleted.');
    }

    /**
     * Play a review's voice message: the fallback for when the signed URL the
     * page was given has expired. Redirects to a fresh one, or streams when
     * the disk cannot sign.
     */
    public function voice(Request $request, Workspace $workspace, Creative $creative, CreativeReview $review)
    {
        $this->guard($request, $workspace, $creative);
        $this->authorize(Permission::ViewCreatives->value, $workspace);

        abort_unless($review->creative_id === $creative->id, 404);

        $voice = $review->voice();

        abort_unless($voice, 404, 'This review has no voice message.');

        $url = $this->storage->signedUrl($voice);

        return $url ? redirect()->away($url) : Storage::disk($voice->disk)->response($voice->getPathRelativeToRoot());
    }

    /** Only the reviewer who wrote a review may change or delete it. */
    private function authorizeAuthor(Request $request, Workspace $workspace, Creative $creative, CreativeReview $review, string $message): void
    {
        $this->guard($request, $workspace, $creative);
        $this->authorize(Permission::ReviewCreatives->value, $workspace);

        if ($review->creative_id !== $creative->id) {
            abort(404);
        }

        if ($review->reviewer_id !== $request->user()->id) {
            abort(403, $message);
        }
    }
}
