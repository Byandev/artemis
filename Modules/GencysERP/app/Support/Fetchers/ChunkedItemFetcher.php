<?php

namespace Modules\GencysERP\Support\Fetchers;

use App\Models\Workspace;
use Illuminate\Support\Collection;
use Modules\GencysERP\Models\GencysSyncRun;

/**
 * The sweep shared by the two per-inventory-item types (transaction history and
 * purchase orders): for each set of parameters, for each workspace, chunk the
 * items, open a run per item and hand the chunk to n8n.
 *
 * dispatch() is final — that loop is the same for both types and getting it
 * subtly wrong is how runs go missing. What actually differs is filled in by the
 * hooks below: the parameters swept, how far apart the chunks are spaced, any
 * extra payload keys, and which job carries the call.
 */
abstract class ChunkedItemFetcher extends GencysDataFetcher
{
    /** Items per webhook call. One ERP login serves the whole chunk. */
    protected const CHUNK = 20;

    /** Minutes between queued chunks, to keep concurrent ERP logins down. */
    abstract protected function chunkDelayMinutes(): int;

    /**
     * The parameter sets to sweep, one pass each — transaction history returns
     * one per date, purchase orders a single start/end range.
     *
     * Each set is recorded verbatim as its runs' meta *and* merged into the
     * payload, which is what keeps the two in step: the retry path reads a run's
     * meta back and expects to be able to re-send exactly what n8n was asked for.
     *
     * @return array<int, array<string, string>>
     *
     * @throws \InvalidArgumentException
     */
    abstract protected function passes(): array;

    /**
     * The console line describing this sweep.
     *
     * @param  array<int, array<string, string>>  $passes
     */
    abstract protected function describe(array $passes): string;

    /** The queued job that carries one chunk to n8n. */
    abstract protected function makeJob(string $webhookUrl, array $data, array $syncRunIds): object;

    /** Payload keys beyond the shared ones and the pass parameters. */
    protected function payloadExtras(Workspace $workspace): array
    {
        return [];
    }

    final public function dispatch(Collection $workspaces): int
    {
        $passes = $this->passes();
        $webhookUrl = $this->webhookUrl();
        $batchId = $this->batchId();

        $this->info($this->describe($passes));

        $chunkIndex = 0;
        $opened = 0;

        foreach ($passes as $parameters) {
            foreach ($workspaces as $workspace) {
                foreach ($workspace->inventoryItems->chunk(static::CHUNK) as $chunk) {
                    // A run per item, keyed by item id. Each run's id rides along
                    // in the payload so n8n can echo it back for an exact match;
                    // items that never report back stay pending until the
                    // stale-run sweeper fails them.
                    $runIds = $chunk->mapWithKeys(fn ($item) => [
                        $item->id => GencysSyncRun::start(
                            $workspace->id,
                            $item->id,
                            $this->type(),
                            $parameters,
                            $batchId,
                        )->id,
                    ]);

                    $data = array_merge(
                        $this->basePayload($workspace),
                        $parameters,
                        $this->payloadExtras($workspace),
                        ['items' => $chunk->map(fn ($item) => [
                            'id' => $item->id,
                            'keyword' => $item->sku,
                            'sync_run_id' => $runIds[$item->id],
                        ])->values()->toArray()],
                    );

                    $this->send(
                        $this->makeJob($webhookUrl, $data, $runIds->values()->all()),
                        $chunkIndex * $this->chunkDelayMinutes() * 60,
                    );

                    $chunkIndex++;
                    $opened += $chunk->count();
                }
            }
        }

        return $opened;
    }
}
