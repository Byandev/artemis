<?php

namespace Modules\Creatives\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Creatives\Models\Creative;
use Modules\Creatives\Services\ReviewerPush;

/** Tells newly assigned reviewers, on their phones and web app, that a creative is waiting. */
class SendCreativeAssignedPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param  array<int>  $userIds */
    public function __construct(public int $creativeId, public array $userIds)
    {
        $this->afterCommit();
        $this->onQueue('push');
    }

    public function handle(ReviewerPush $push): void
    {
        $creative = Creative::with(['workspace', 'product:id,title'])->find($this->creativeId);

        // Things may have moved on between assigning and this job running.
        if (! $creative || $creative->final_status !== 'for_approval' || ! $creative->workspace?->creatives_module_enabled) {
            return;
        }

        $stillAssigned = $creative->assignedReviewers()
            ->whereIn('users.id', $this->userIds)
            ->pluck('users.id')
            ->all();

        if (! $stillAssigned) {
            return;
        }

        $push->sendToUsers(
            $stillAssigned,
            'New creative to review',
            collect([$creative->product?->title, $creative->name, $creative->workspace->name])->filter()->implode(' · '),
            ['type' => 'creative_assigned', 'creative_id' => $creative->id],
            urgent: true,
        );
    }

    /**
     * Queues a push for the reviewers a pivot sync() just attached. Takes the
     * array sync() / syncWithoutDetaching() return.
     */
    public static function forSync(Creative $creative, array $changes): void
    {
        $attached = array_map('intval', $changes['attached'] ?? []);

        if ($attached && $creative->final_status === 'for_approval') {
            dispatch(new self($creative->id, $attached));
        }
    }
}
