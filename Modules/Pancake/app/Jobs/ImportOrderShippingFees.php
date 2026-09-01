<?php

namespace Modules\Pancake\Jobs;

use App\Models\Workspace;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Pancake\Imports\OrderShippingFeesImport;
use Modules\Pancake\Support\ShippingFeeImportStatus as Status;
use Throwable;

/**
 * Runs a courier billing export through OrderShippingFeesImport off the
 * request: reading the sheet is slow enough (~12s for 14k rows) that the
 * browser should not be waiting on it.
 *
 * Progress and the result land in the cache for the orders page to poll.
 */
class ImportOrderShippingFees implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public int $workspaceId,
        public string $path,
        public string $fileName,
    ) {}

    public function handle(): void
    {
        Status::put($this->workspaceId, [
            'status' => Status::PROCESSING,
            'started_at' => now()->toIso8601String(),
        ]);

        $workspace = Workspace::findOrFail($this->workspaceId);
        $file = Storage::disk('local')->path($this->path);

        try {
            $import = OrderShippingFeesImport::for($workspace, $file);

            Excel::import($import, $file);
        } finally {
            Storage::disk('local')->delete($this->path);
        }

        Status::put($this->workspaceId, [
            'status' => Status::FINISHED,
            'finished_at' => now()->toIso8601String(),
            'rows_read' => $import->rowsRead,
            'skipped' => $import->skipped,
            'matched_orders' => $import->matchedOrders,
            'updated' => $import->updated,
            'unmatched' => $import->unmatched,
            'unmatched_sample' => $import->unmatchedSample,
        ]);
    }

    public function failed(?Throwable $e): void
    {
        Storage::disk('local')->delete($this->path);

        Log::error('Shipping fee import failed', [
            'workspace_id' => $this->workspaceId,
            'file' => $this->fileName,
            'error' => $e?->getMessage(),
        ]);

        Status::put($this->workspaceId, [
            'status' => Status::FAILED,
            'finished_at' => now()->toIso8601String(),
            'message' => $e?->getMessage() ?? 'The import failed.',
        ]);
    }
}
