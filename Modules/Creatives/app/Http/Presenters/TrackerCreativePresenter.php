<?php

namespace Modules\Creatives\Http\Presenters;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Modules\Creatives\Models\Creative;
use Modules\Creatives\Models\CreativeReview;
use Modules\Creatives\Services\CreativeMediaStorage;
use Modules\Creatives\Services\TrackerVisibility;

/**
 * A creative as the Creatives Tracker app reads it, from the signed-in user's
 * point of view: their own latest review and what they may do to it.
 */
class TrackerCreativePresenter
{
    public function __construct(
        private TrackerVisibility $visibility,
        private CreativeMediaStorage $storage,
    ) {}

    /** Relations present() reads; eager-load these first. */
    public static function relations(): array
    {
        return [
            'workspace:id,name,slug,owner_id',
            // The uploaded image/video (mediaFile() reads the loaded relation).
            'media',
            'creator:id,name',
            'approvedBy:id,name',
            'product:id,title',
            'assignedReviewers:id,name',
            'reviews' => fn ($q) => $q->with(['reviewer:id,name', 'media'])->oldest(),
        ];
    }

    public function present(Creative $c, User $user): array
    {
        $canReview = $this->visibility->can($user, $c->workspace, Permission::ReviewCreatives);

        $reviews = $c->reviews->map(fn ($r) => [
            'id' => $r->id,
            'status' => $r->status,
            'feedback' => $r->feedback,
            'timestamp_seconds' => $r->timestamp_seconds,
            // The area of the image it points at, as fractions (w = h = 0 is a point).
            'region' => $r->region,
            'voice' => $this->voice($c, $r),
            'reviewer' => $r->reviewer ? ['id' => $r->reviewer->id, 'name' => $r->reviewer->name] : null,
            'created_at' => $r->created_at?->toIso8601String(),
            // Only the author may delete, as on the web.
            'can_delete' => $canReview && $r->reviewer_id === $user->id,
        ])->values();

        $latestReview = $c->reviews->last();
        $myReview = $c->reviews->where('reviewer_id', $user->id)->last();
        $isAssigned = $c->assignedReviewers->contains('id', $user->id);

        return [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'description' => $c->description,
            'format' => $c->format,
            'creative_date' => $c->creative_date?->format('Y-m-d'),
            'submission_status' => $c->submission_status,
            'script' => $c->script,
            // Stored as a relative /storage/... path for the web app; the
            // phone needs a full URL.
            'picture_url' => $c->picture_url && str_starts_with($c->picture_url, '/') ? url($c->picture_url) : $c->picture_url,
            // The file uploaded to Artemis, if any — the preview to show first.
            'media' => $this->media($c),
            'reference_link' => $c->reference_link,
            'caption' => $c->caption,
            'headline' => $c->headline,
            'notes' => $c->notes,
            'ads_status' => $c->ads_status,
            'final_status' => $c->final_status,
            'approved_at' => $c->approved_at?->toIso8601String(),
            'approved_by' => $c->approvedBy ? ['id' => $c->approvedBy->id, 'name' => $c->approvedBy->name] : null,
            'workspace' => ['id' => $c->workspace->id, 'name' => $c->workspace->name, 'slug' => $c->workspace->slug],
            'creator' => $c->creator ? ['id' => $c->creator->id, 'name' => $c->creator->name] : null,
            'product' => $c->product ? ['id' => $c->product->id, 'title' => $c->product->title] : null,
            'assigned_reviewers' => $c->assignedReviewers->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->values(),
            'reviews' => $reviews,
            'review_count' => $reviews->count(),
            'latest_review' => $latestReview ? ['status' => $latestReview->status, 'feedback' => $latestReview->feedback] : null,
            'my_review' => $myReview ? ['status' => $myReview->status, 'created_at' => $myReview->created_at?->toIso8601String()] : null,
            'permissions' => [
                'update_final_status' => $this->visibility->can($user, $c->workspace, Permission::UpdateCreativeStatus),
                'review' => $isAssigned && $canReview,
            ],
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }

    /** The uploaded image or video, with a URL the app can load directly. */
    private function media(Creative $c): ?array
    {
        $media = $c->mediaFile();
        $url = $media ? $this->storage->signedUrl($media) : null;

        if (! $media || ! $url) {
            return null;
        }

        return [
            'url' => $url,
            'mime_type' => $media->mime_type,
            'file_name' => $media->file_name,
            'size' => $media->size,
        ];
    }

    /**
     * A voice message the app's player can load without the bearer token:
     * signed by the disk/CDN, or else a short-lived signed API URL.
     */
    private function voice(Creative $c, CreativeReview $r): ?array
    {
        $voice = $r->voice();

        if (! $voice) {
            return null;
        }

        return [
            'duration_seconds' => $voice->getCustomProperty('duration_seconds'),
            'url' => $this->storage->signedUrl($voice) ?? URL::temporarySignedRoute(
                'api.v1.creatives-tracker.creatives.reviews.voice',
                now()->addHours(4),
                ['creative' => $c->id, 'review' => $r->id],
            ),
        ];
    }
}
