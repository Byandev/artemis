<?php

namespace App\Jobs;

use App\Models\OutgoingApiLog;
use App\Services\Botcake;
use App\Services\Sms\SmsProviderFactory;
use DateTime;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Support\Facades\DB;
use Modules\Pancake\Models\ParcelJourneyNotification;

class SendParcelUpdateNotification implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * Dedupe the queue: two dispatches for the same notification collapse into
     * one running job. Lock auto-releases after 1 hour as a safety net so a
     * crashed worker can't permanently block a retry.
     */
    public function uniqueId(): string
    {
        return (string) $this->parcelJourneyNotification->id;
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    /**
     * Create a new job instance.
     */
    public function __construct(public ParcelJourneyNotification $parcelJourneyNotification) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (! config('settings.parcel_journey_notification_enabled')) {
            if (config('settings.parcel_journey_notification_logs_enabled')) {
                OutgoingApiLog::create([
                    'service' => 'botcake',
                    'action' => 'sendParcelUpdateNotification',
                    'http_method' => 'POST',
                    'url' => 'skipped',
                    'request_payload' => ['notification_id' => $this->parcelJourneyNotification->id],
                    'response_status' => null,
                    'response_body' => null,
                    'duration_ms' => null,
                    'context' => ['reason' => 'parcel_journey_notification_enabled is false'],
                ]);
            }

            return;
        }

        // Re-read the row before sending so we see any status update committed
        // by an earlier attempt (or by CheckParcelUpdateNotification).
        $this->parcelJourneyNotification->refresh();

        if ($this->alreadyProcessed()) {
            return;
        }

        $this->parcelJourneyNotification->load('order.page');

        if ($this->parcelJourneyNotification->type === 'sms') {
            $provider = app(SmsProviderFactory::class)->for($this->parcelJourneyNotification->order->page);

            $result = $provider->send(
                $this->parcelJourneyNotification->receiver_identity,
                $this->parcelJourneyNotification->message,
            );

            if ($result->accepted) {
                if ($result->tracksDelivery) {
                    if ($result->messageId) {
                        // Provider gave us a message id to poll — record it and hand
                        // off to CheckParcelUpdateNotification for the final status.
                        $this->parcelJourneyNotification->update(['sms_id' => $result->messageId]);

                        dispatch(new CheckParcelUpdateNotification($this->parcelJourneyNotification))->delay(now()->addMinutes(5))->onQueue('parcel-notifications');
                    } else {
                        $this->parcelJourneyNotification->update(['status' => 'failed', 'remarks' => $result->remarks]);
                    }
                } else {
                    // Provider doesn't expose delivery tracking — treat a
                    // successful send as sent.
                    $this->parcelJourneyNotification->update([
                        'status' => 'sent',
                        'sms_id' => $result->messageId,
                    ]);
                }
            } elseif ($result->failed) {
                $this->parcelJourneyNotification->update(['status' => 'failed', 'remarks' => $result->remarks]);
            } else {
                $this->parcelJourneyNotification->update(['remarks' => $result->remarks]);
            }

        } elseif ($this->parcelJourneyNotification->type === 'chat') {
            // Atomically claim the record — only the worker that flips status
            // from 'pending' to 'sent' gets to actually send. Any concurrent
            // duplicate job will see 0 affected rows and bail out.
            $claimed = DB::table('parcel_journey_notifications')
                ->where('id', $this->parcelJourneyNotification->id)
                ->where('status', 'pending')
                ->update(['status' => 'sent']);

            if (! $claimed) {
                return;
            }

            sleep(0.5);

            [$pageId, $psid] = explode('_', $this->parcelJourneyNotification->order->fb_id);

            try {
                $botcake = new Botcake($pageId, $this->parcelJourneyNotification->order->page->botcake_token);

                $botcake->updateCustomField($psid, $this->parcelJourneyNotification->order->page->parcel_journey_custom_field_id, $this->parcelJourneyNotification->message);

                $botcake->sendFlow($psid, $this->parcelJourneyNotification->order->page->parcel_journey_flow_id);
            } catch (\Exception $e) {
                $this->parcelJourneyNotification->update(['status' => 'failed', 'remarks' => $e->getMessage()]);
            }
        }
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new ThrottlesExceptions(3, 5 * 60)];
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): DateTime
    {
        return now()->addHour();
    }

    /**
     * Has this notification already been sent (or definitively failed) by an
     * earlier attempt? Used to short-circuit duplicate runs.
     *
     * - SMS: presence of `sms_id` means we got a response back from Infotxt;
     *   `CheckParcelUpdateNotification` will own the final status.
     * - Chat: `status` already reflects the outcome.
     */
    private function alreadyProcessed(): bool
    {
        if ($this->parcelJourneyNotification->type === 'sms'
            && $this->parcelJourneyNotification->sms_id !== null) {
            return true;
        }

        return in_array($this->parcelJourneyNotification->status, ['sent', 'failed'], true);
    }
}
