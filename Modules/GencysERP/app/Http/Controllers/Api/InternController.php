<?php

namespace Modules\GencysERP\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\GencysERP\Models\Intern;
use Modules\GencysERP\Support\BatchRunner;
use Modules\GencysERP\Support\BulkCallback;
use Modules\GencysERP\Support\SyncCallbackFields;

/**
 * Callback for the n8n interns sync. n8n posts { data: { workspace_id, api_key,
 * interns: [...] } } — the workspace is resolved from the api_key we sent in the
 * webhook, and its interns are upserted (keyed on Gencys' intern id).
 *
 * The roster is small enough to arrive in one post, so this closes the run it
 * answers rather than heartbeating. `sync_run_id` is echoed back by the workflow
 * — read off `data` as readily as the top level, since n8n wraps the payload —
 * and a call that carries none falls back to the workspace's oldest interns run
 * still in flight, which is unambiguous: a batch only ever has one out at a time.
 * That fallback is what let InternFlow join the queue without the deployed
 * workflow having to learn a new field.
 */
class InternController extends Controller
{
    public function store(Request $request, BatchRunner $runner): JsonResponse
    {
        $workspaceId = $request->input('data.workspace_id');
        $rawKey = $request->input('data.api_key');
        $interns = $request->input('data.interns', []);
        $interns = is_array($interns) ? $interns : [];

        // Authenticate by the api_key we sent, and make sure it owns the workspace.
        $apiKey = $rawKey ? WorkspaceApiKey::findByRawKey($rawKey) : null;

        if (! $apiKey || ($workspaceId && (int) $apiKey->workspace_id !== (int) $workspaceId)) {
            return response()->json(['message' => 'Invalid api_key for workspace.'], 401);
        }

        $apiKey->update(['last_used_at' => now()]);
        $workspace = $apiKey->workspace;

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($interns as $intern) {
            // Gencys' intern id is the upsert key; rows without one are skipped.
            $internId = $this->intOrNull($intern['id'] ?? $intern['internId'] ?? $intern['intern_id'] ?? null);

            if ($internId === null) {
                $skipped++;

                continue;
            }

            $record = Intern::updateOrCreate(
                ['workspace_id' => $workspace->id, 'intern_id' => $internId],
                [
                    'full_name' => $this->str($intern['fullName'] ?? $intern['full_name'] ?? null),
                    'company_name' => $this->str($intern['companyName'] ?? $intern['company_name'] ?? null),
                    'username' => $this->str($intern['username'] ?? null),
                    'contact_number' => $this->str(
                        $intern['contactNumber'] ?? $intern['contact_number'] ?? $intern['phone'] ?? null
                    ),
                    'email' => $this->str($intern['email'] ?? null),
                ],
            );
            $record->wasRecentlyCreated ? $created++ : $updated++;
        }

        // n8n wraps its payload, so the bookkeeping fields are looked for on
        // `data` first and at the top level second.
        $entry = (array) $request->input('data', []);

        $run = BulkCallback::creditRun(
            $request,
            $workspace->id,
            GencysSyncRun::TYPE_INTERNS,
            count($interns),
            $created + $updated,
            SyncCallbackFields::executionId($entry, $request),
            SyncCallbackFields::runId($entry, $request),
        );

        // The run this callback covered is resolved, so whichever batch it
        // belonged to can send its next group.
        $runner->tick();

        return response()->json([
            'sync_run_id' => $run?->id,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }

    private function str(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }

    private function intOrNull(mixed $value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }
}
