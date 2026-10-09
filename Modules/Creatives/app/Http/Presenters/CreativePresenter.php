<?php

namespace Modules\Creatives\Http\Presenters;

use App\Models\Workspace;
use Modules\Creatives\Models\Creative;
use Modules\Creatives\Models\CreativeReview;
use Modules\Creatives\Services\CreativeMediaStorage;

/** A creative as the pages receive it. */
class CreativePresenter
{
    public function __construct(private CreativeMediaStorage $storage) {}

    /**
     * Everything present() reads, for eager loading — media included, so
     * signing each file's URL doesn't fire a query per row.
     *
     * @return array<int|string, mixed>
     */
    public static function relations(): array
    {
        return [
            'creator:id,name',
            'approvedBy:id,name',
            'product:id,title',
            'assignedReviewers:id,name',
            'reviews' => fn ($q) => $q->with(['reviewer:id,name', 'media'])->oldest(),
            'media',
        ];
    }

    /** @return array<string, mixed> */
    public function present(Creative $c, Workspace $workspace): array
    {
        $reviews = $c->reviews->map(fn (CreativeReview $r) => $this->review($r, $c, $workspace))->values()->all();

        $latestReview = $c->reviews->last();

        return [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'description' => $c->description,
            'format' => $c->format,
            'creative_date' => $c->creative_date?->format('Y-m-d'),
            'creative_date_label' => $c->creative_date_label,
            'submission_status' => $c->submission_status,
            'created_at' => $c->created_at?->format('M j, Y g:i A'),
            'script' => $c->script,
            'picture_url' => $c->picture_url,
            'media' => ($media = $c->mediaFile()) ? [
                'id' => $media->id,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                // Signed, for previews to load directly; the app route is the
                // fallback when the disk can't sign (local development).
                'url' => $this->storage->signedUrl($media)
                    ?? route('workspaces.creatives.media.show', [$workspace, $c], absolute: false),
            ] : null,
            'reference_link' => $c->reference_link,
            'ads_status' => $c->ads_status,
            'ads_manager_link' => $c->ads_manager_link,
            'ads_remarks' => $c->ads_remarks,
            'final_status' => $c->final_status,
            'approved_at' => $c->approved_at?->format('M d, Y g:i A'),
            'approved_by' => $c->approvedBy ? ['id' => $c->approvedBy->id, 'name' => $c->approvedBy->name] : null,
            'caption' => $c->caption,
            'headline' => $c->headline,
            'notes' => $c->notes,
            'creator' => $c->creator ? ['id' => $c->creator->id, 'name' => $c->creator->name] : null,
            'product' => $c->product ? ['id' => $c->product->id, 'title' => $c->product->title] : null,
            'assigned_reviewers' => $c->assignedReviewers->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->values()->all(),
            'reviews' => $reviews,
            'review_count' => count($reviews),
            'latest_review' => $latestReview ? [
                'status' => $latestReview->status,
                'feedback' => $latestReview->feedback,
            ] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function review(CreativeReview $r, Creative $c, Workspace $workspace): array
    {
        return [
            'id' => $r->id,
            'status' => $r->status,
            'feedback' => $r->feedback,
            'timestamp_seconds' => $r->timestamp_seconds,
            'region' => $r->region,
            'voice' => ($voice = $r->voice()) ? [
                'duration_seconds' => $voice->getCustomProperty('duration_seconds'),
                // Signed, for the player to load directly; the app route is
                // the fallback when the disk can't sign.
                'url' => $this->storage->signedUrl($voice)
                    ?? route('workspaces.creatives.reviews.voice', [$workspace, $c, $r], absolute: false),
            ] : null,
            'reviewer' => $r->reviewer ? ['id' => $r->reviewer->id, 'name' => $r->reviewer->name] : null,
            'created_at' => $r->created_at->format('M d, Y g:i A'),
        ];
    }
}
