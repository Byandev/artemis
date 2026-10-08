<?php

namespace Modules\Creatives\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Modules\Creatives\Http\Presenters\TrackerCreativePresenter;
use Modules\Creatives\Http\Requests\StoreReviewRequest;
use Modules\Creatives\Models\Creative;
use Modules\Creatives\Models\CreativeReview;
use Modules\Creatives\Services\CreativeMediaStorage;
use Modules\Creatives\Services\TrackerVisibility;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mobile app: one creative's detail screen, and the two things it can do
 * there — set the final status and leave a review (text, a voice message, or
 * both; optionally pinned to an area of the image or a second of the video).
 * A creative the user cannot see answers 404, not 403, so ids of other
 * workspaces' creatives don't leak.
 */
class TrackerCreativeController extends Controller
{
    public function __construct(
        private TrackerVisibility $visibility,
        private TrackerCreativePresenter $presenter,
        private CreativeMediaStorage $storage,
    ) {}

    public function show(Request $request, Creative $creative): JsonResponse
    {
        $this->ensureVisible($request->user(), $creative);

        return $this->respond($request->user(), $creative);
    }

    public function updateFinalStatus(Request $request, Creative $creative): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureVisible($user, $creative);

        abort_unless(
            $this->visibility->can($user, $creative->workspace, Permission::UpdateCreativeStatus),
            403,
            'You are not allowed to change the final status.',
        );

        $validated = $request->validate([
            'final_status' => ['required', Rule::in(['for_approval', 'approved', 'for_revision'])],
        ]);

        // Same stamping as the web app: keep the first approval, clear it when
        // the creative leaves "approved".
        $isApproved = $validated['final_status'] === 'approved';

        $creative->update([
            'final_status' => $validated['final_status'],
            'approved_at' => $isApproved ? ($creative->approved_at ?? now()) : null,
            'approved_by' => $isApproved ? ($creative->approved_by ?? $user->id) : null,
        ]);

        return $this->respond($user, $creative);
    }

    public function storeReview(StoreReviewRequest $request, Creative $creative): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureVisible($user, $creative);

        abort_unless(
            $this->visibility->can($user, $creative->workspace, Permission::ReviewCreatives)
                && $creative->assignedReviewers()->whereKey($user->id)->exists(),
            403,
            'You are not an assigned reviewer for this creative.',
        );

        // The web's rules: status required; feedback, a voice message, an area
        // of an uploaded image or a second of a video all optional.
        $review = CreativeReview::create([
            'creative_id' => $creative->id,
            'reviewer_id' => $user->id,
            ...collect($request->validated())->except(['voice', 'voice_duration_seconds'])->all(),
        ]);

        if ($request->hasFile('voice')) {
            $review->addMediaFromRequest('voice')
                // Measured by the recorder; the server never decodes the audio.
                ->withCustomProperties(['duration_seconds' => $request->integer('voice_duration_seconds') ?: null])
                ->toMediaCollection(CreativeReview::VOICE_COLLECTION);
        }

        return $this->respond($user, $creative, 201);
    }

    /**
     * Same rule as the web app: only the reviewer who wrote a review may
     * delete it. Deleting the model (not a query delete) takes its voice
     * message out of the bucket too.
     */
    public function destroyReview(Request $request, Creative $creative, CreativeReview $review): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureVisible($user, $creative);

        abort_unless($review->creative_id === $creative->id, 404);

        abort_unless(
            $this->visibility->can($user, $creative->workspace, Permission::ReviewCreatives)
                && $review->reviewer_id === $user->id,
            403,
            'You can only delete your own reviews.',
        );

        $review->delete();

        return $this->respond($user, $creative);
    }

    /**
     * A review's voice message, for the app's player. Reached through a
     * short-lived signed URL (the player can't send the bearer token), handed
     * out by the presenter only when the disk itself can't sign one.
     */
    public function voice(Creative $creative, CreativeReview $review): Response
    {
        abort_unless($review->creative_id === $creative->id, 404);

        $voice = $review->voice();

        abort_unless($voice, 404, 'This review has no voice message.');

        $url = $this->storage->signedUrl($voice);

        return $url ? redirect()->away($url) : Storage::disk($voice->disk)->response($voice->getPathRelativeToRoot());
    }

    private function ensureVisible(User $user, Creative $creative): void
    {
        abort_unless($this->visibility->canSee($user, $creative), 404);
    }

    private function respond(User $user, Creative $creative, int $status = 200): JsonResponse
    {
        $creative->load(TrackerCreativePresenter::relations());

        return response()->json(['data' => $this->presenter->present($creative, $user)], $status);
    }
}
