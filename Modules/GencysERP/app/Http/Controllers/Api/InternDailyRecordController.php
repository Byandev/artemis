<?php

namespace Modules\GencysERP\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\GencysERP\Models\GencysInternDailyRecord;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\GencysERP\Models\Intern;

class InternDailyRecordController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // 1. Authenticate Request using the pre-normalized keys from n8n
        $apiKey = WorkspaceApiKey::findByRawKey($request->input('api_key'));
        $workspaceId = (int) $request->input('workspace_id');

        if (!$apiKey || (int) $apiKey->workspace_id !== $workspaceId) {
            return response()->json(['message' => 'Invalid api_key for workspace.'], 401);
        }

        $apiKey->update(['last_used_at' => now()]);
        $workspace = $apiKey->workspace;

        // 2. Verify Intern Identity
        $parentInternId = $request->input('intern_id');
        $intern = Intern::where('workspace_id', $workspace->id)
            ->where('intern_id', $parentInternId)
            ->first();

        if (!$intern) {
            return response()->json(['message' => "Intern {$parentInternId} not found."], 422);
        }

        // 3. Process records directly (Data arrays are pre-scrubbed by your n8n workflow)
        $records = $request->input('rows', []);
        $rowsPerRun = [];

        foreach ($records as $record) {
            GencysInternDailyRecord::updateOrCreate(
                [
                    'workspace_id'     => $workspace->id,
                    'gencys_intern_id' => $intern->id,
                    'record_date'      => $record['date'],
                ],
                $record
            );

        }

        // 4. Update sync run success counts
        foreach ($rowsPerRun as $runId => $count) {
            GencysSyncRun::succeedById($workspace->id, $runId, $count, $count);
        }

        return response()->json([
            'saved'            => count($records),
            'skipped'          => 0,
            'intern_id'        => $parentInternId,
            'gencys_intern_id' => $intern->id,
        ]);
    }
}
