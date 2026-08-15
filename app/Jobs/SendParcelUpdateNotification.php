<?php

namespace App\Jobs;

use App\Models\Page;
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
            return;
        }

        try {
            // Re-read the row before sending so we see any status update committed
            // by an earlier attempt (or by CheckParcelUpdateNotification).
            $this->parcelJourneyNotification->refresh();

            if ($this->alreadyProcessed()) {
                return;
            }

            $this->parcelJourneyNotification->load('order');

            if ($this->parcelJourneyNotification->type === 'sms') {
                $page = $this->parcelJourneyNotification->order->page_id ?
                    Page::find($this->parcelJourneyNotification->order->page_id) :
                    Page::where('shop_id', $this->parcelJourneyNotification->order->shop_id)
                        ->where('parcel_journey_enabled', true)
                        ->whereNotNull('sms_provider')
                        ->first();

                $provider = app(SmsProviderFactory::class)->for($page);

                $result = $provider->send(
                    $this->parcelJourneyNotification->receiver_identity,
                    $this->parcelJourneyNotification->message,
                );

                if ($result->accepted) {
                    if ($result->awaitsCallback) {
                        // The SIM Gateway device pushes the final status to our
                        // callback later — record the id and stay pending; the
                        // callback flips this notification to sent/failed.
                        $this->parcelJourneyNotification->update(['sms_id' => $result->messageId]);
                    } elseif ($result->tracksDelivery) {
                        // Provider gave us a message id to poll — record it and hand
                        // off to CheckParcelUpdateNotification for the final status.
                        $this->parcelJourneyNotification->update(['sms_id' => $result->messageId]);

                        dispatch(new CheckParcelUpdateNotification($this->parcelJourneyNotification))->delay(now()->addMinutes(5))->onQueue('sms');
                    } else {
                        // Provider doesn't expose delivery tracking (SendGate) — treat
                        // a successful send as sent.
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
            } elseif ($this->parcelJourneyNotification->type === 'chat' && $this->parcelJourneyNotification->order->fb_id) {
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

                $page = Page::where('id', $pageId)->firstOrFail();

                $botcake = new Botcake($pageId, $page->botcake_token);

                $botcake->updateCustomField($psid, $page->parcel_journey_custom_field_id, $this->parcelJourneyNotification->message);

                $botcake->sendFlow($psid, $page->parcel_journey_flow_id);

                $this->parcelJourneyNotification->update(['status' => 'sent']);
            }
        } catch (\Throwable $th) {
            $this->parcelJourneyNotification->update(['status' => 'failed', 'remarks' => $th->getMessage()]);
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
     * - SMS: a non-null `sms_id` means a provider already accepted the message.
     *   For Infotxt, `CheckParcelUpdateNotification` owns the final status; the
     *   non-tracking providers (SIM Gateway, SendGate) also stamp `status` on
     *   success, so a definitive `status` short-circuits too.
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
