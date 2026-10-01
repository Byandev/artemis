<?php

namespace Modules\GencysERP\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdvertiserPerformanceDailyRecord;
use App\Models\WorkspaceApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\GencysERP\Models\Intern;
use Modules\GencysERP\Support\BatchRunner;
use Modules\GencysERP\Support\SyncCallbackFields;

/**
 * Receives one intern's daily figures back from the n8n flow.
 *
 * InternDailyRecordFlow opens a run per (intern, date) and sends its id as
 * `sync_run_id`; n8n echoes it back once for the whole call, since one call is
 * one intern's one date. This callback is the only word that run gets — the flow
 * sends no finish webhook — so a call that doesn't close it leaves it pending
 * until it times out and the whole intern/date is fetched again.
 */
class InternDailyRecordController extends Controller
{
    public function store(Request $request, BatchRunner $runner): JsonResponse
    {
        // 1. Authenticate Request using the pre-normalized keys from n8n
        $apiKey = WorkspaceApiKey::findByRawKey($request->input('api_key'));
        $workspaceId = (int) $request->input('workspace_id');

        if (! $apiKey || (int) $apiKey->workspace_id !== $workspaceId) {
            return response()->json(['message' => 'Invalid api_key for workspace.'], 401);
        }

        $apiKey->update(['last_used_at' => now()]);
        $workspace = $apiKey->workspace;

        // 2. Verify Intern Identity
        $parentInternId = $request->input('intern_id');
        $intern = Intern::where('workspace_id', $workspace->id)
            ->where('intern_id', $parentInternId)
            ->first();

        if (! $intern) {
            return response()->json(['message' => "Intern {$parentInternId} not found."], 422);
        }

        // 3. Process records directly (Data arrays are pre-scrubbed by your n8n workflow)
        $records = $request->input('rows', []);

        // The one run this whole call answers.
        $runId = SyncCallbackFields::runId($request);

        $saved = 0;
        $skipped = 0;

        foreach ($records as $record) {
            if (! is_array($record)) {
                $skipped++;

                continue;
            }

            // An undated row has nothing to key the upsert on, so it is dropped
            // on the floor there — counted as skipped rather than as saved.
            $written = ! empty($record['date']);

            if ($written) {
                // Write straight to the unified advertiser performance table.
                AdvertiserPerformanceDailyRecord::upsertGencysDaily(
                    $workspace->id, $intern->id, $intern->full_name, $record
                );
                $saved++;
            } else {
                $skipped++;
            }
        }

        // 4. Close the run this call answered. Rows are still counted when there
        // are none — an intern with nothing that day closes its run rather than
        // sitting pending.
        if ($runId) {
            GencysSyncRun::succeedById(
                $workspace->id, $runId, count($records), $saved, SyncCallbackFields::executionId($request)
            );

            // succeedById deliberately leaves the batch alone, so nudge it here:
            // whatever batch this run belonged to may now be free to send its
            // next group.
            $runner->tick();
        } else {
            // Silent when it happens, expensive afterwards: the run times out and
            // the same intern/date gets fetched all over again.
            Log::warning('Gencys intern daily records arrived with no sync_run_id', [
                'workspace_id' => $workspace->id,
                'intern_id' => $parentInternId,
                'rows' => count($records),
                'hint' => 'The n8n flow must echo sync_run_id back on the callback.',
            ]);
        }

        return response()->json([
            'saved' => $saved,
            'skipped' => $skipped,
            'intern_id' => $parentInternId,
            'gencys_intern_id' => $intern->id,
            'sync_run_id' => $runId,
        ]);
    }
}
