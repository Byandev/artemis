<?php

namespace Modules\GencysERP\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\GencysERP\Support\BatchRunner;

/**
 * Lets n8n say "that run is done" as a separate call from the data itself.
 *
 * Most ERP pulls come back in one callback, which closes the run as it saves.
 * The big ones can't — the daily sales tracker posts a thousand rows at a time —
 * and a run must not close on the first chunk, or the batch releases the ERP and
 * moves on while n8n is still posting. Those flows send their chunks to the
 * normal callback (each one extends the run's deadline) and then post here once,
 * at the end.
 *
 *   POST /api/v1/public/gencys/sync-runs/finish
 *   { "api_key": "art_…", "sync_run_id": 42,
 *     "rows_received": 4210, "rows_saved": 4210,   // optional
 *     "status": "success" | "failed", "message": "…" }   // optional
 *
 * Row counts are optional — leave them out and the totals the chunks accumulated
 * stand. Calling it twice is harmless.
 */
class SyncRunController extends Controller
{
    public function finish(Request $request, BatchRunner $runner): JsonResponse
    {
        $rawKey = $request->input('api_key') ?? $request->bearerToken() ?? $request->header('X-API-Key');
        $apiKey = $rawKey ? WorkspaceApiKey::findByRawKey($rawKey) : null;

        if (! $apiKey) {
            return response()->json(['message' => 'Missing or invalid api_key.'], 401);
        }

        $apiKey->update(['last_used_at' => now()]);

        $validated = $request->validate([
            'sync_run_id' => ['required', 'integer'],
            'rows_received' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'rows_saved' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'status' => ['sometimes', 'nullable', 'string', 'in:success,failed'],
            'message' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $run = GencysSyncRun::finishById(
            workspaceId: (int) $apiKey->workspace_id,
            syncRunId: (int) $validated['sync_run_id'],
            rowsReceived: $validated['rows_received'] ?? null,
            rowsSaved: $validated['rows_saved'] ?? null,
            failed: ($validated['status'] ?? 'success') === 'failed',
            message: $validated['message'] ?? null,
        );

        if (! $run) {
            return response()->json([
                'message' => "No sync run {$validated['sync_run_id']} for this workspace.",
            ], 404);
        }

        // The run is closed, so its batch may now be free to send the next group.
        $runner->tick();

        return response()->json([
            'sync_run_id' => $run->id,
            'status' => $run->status,
            'rows_received' => $run->rows_received,
            'rows_saved' => $run->rows_saved,
            'finished_at' => $run->finished_at,
        ]);
    }
}
