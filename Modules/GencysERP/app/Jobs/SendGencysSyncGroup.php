<?php

namespace Modules\GencysERP\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\GencysERP\Support\SyncCallbackFields;
use Throwable;

/**
 * POSTs one batch group to its n8n webhook — the single outbound step for every
 * Gencys sync type, since what differs between them is only the payload, which
 * the flow already built.
 *
 * A successful handshake means nothing has synced yet; it means n8n took the job.
 * What the response does carry is the id of the execution n8n started, which is
 * stamped onto the group's runs so they can be traced even if no callback ever
 * arrives. The runs stay pending until n8n posts the data back (which advances
 * the batch) or their timeout expires. Only the handshake failing is this job's
 * business:
 * that request is never going to produce a callback, so the runs go back in the
 * queue for another attempt, or are failed once the batch's retries are spent.
 *
 * Deliberately not retried by the queue — the batch owns retry policy, and a
 * queue-level retry would re-send a group whose runs it has already re-queued.
 */
class SendGencysSyncGroup implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public string $webhookUrl,
        public array $payload,
        public array $syncRunIds,
    ) {
        $this->onQueue('erp');
    }

    public function handle(): void
    {
        try {
            $response = Http::timeout(30)->post($this->webhookUrl, $this->payload);
        } catch (Throwable $e) {
            $this->releaseRuns('n8n webhook unreachable: '.$e->getMessage());

            Log::warning('n8n webhook unreachable for Gencys sync group', [
                'workspace_id' => $this->payload['workspace_id'] ?? null,
                'runs' => count($this->syncRunIds),
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($response->successful()) {
            $this->recordExecutionId($response);

            return;
        }

        $this->releaseRuns("n8n webhook returned HTTP {$response->status()}");

        Log::warning('n8n webhook call failed for Gencys sync group', [
            'workspace_id' => $this->payload['workspace_id'] ?? null,
            'runs' => count($this->syncRunIds),
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
    }

    /**
     * Stamp the n8n execution that took this group onto its runs.
     *
     * n8n reports the execution it started in the response to our call, which is
     * the earliest and most reliable moment to capture it: a run whose callback
     * never arrives is exactly the one you want to look up in n8n, and by then
     * there is no callback to carry the id.
     *
     * Runs that already have an id are left alone — a callback that came back
     * before this response was processed got there first, and it is the same
     * execution either way.
     */
    private function recordExecutionId(Response $response): void
    {
        $executionId = SyncCallbackFields::executionIdFromResponse(
            $response->json(),
            $response->headers(),
        );

        if (! $executionId || empty($this->syncRunIds)) {
            return;
        }

        GencysSyncRun::query()
            ->whereIn('id', $this->syncRunIds)
            ->whereNull('n8n_execution_id')
            ->update(['n8n_execution_id' => $executionId]);
    }

    /**
     * Hand this group's runs back to the batch: another attempt if it has retries
     * left for them, otherwise failed.
     *
     * The batch is deliberately not nudged forward here. A handshake failure
     * usually means n8n is down or rate-limiting, and re-sending in the same
     * breath would just fail again — the timeout sweeper picks the runs up on its
     * next minute, which gives the far side room to recover.
     */
    private function releaseRuns(string $message): void
    {
        GencysSyncRun::query()
            ->whereIn('id', $this->syncRunIds)
            ->pending()
            ->with('batch')
            ->get()
            ->each(function (GencysSyncRun $run) use ($message) {
                $maxRetries = $run->batch?->max_retries ?? 0;

                if ($run->attempt < $maxRetries) {
                    $run->requeueForRetry($message.' — retrying.');

                    return;
                }

                $run->fail($message);
            });
    }
}
