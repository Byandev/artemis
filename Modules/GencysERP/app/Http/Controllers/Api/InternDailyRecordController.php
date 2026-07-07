<?php

namespace Modules\GencysERP\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\GencysERP\Models\GencysIntern;
use Modules\GencysERP\Models\GencysInternDailyRecord;
use Modules\GencysERP\Models\GencysSyncRun;

/**
 * Callback for the n8n intern daily-records sync. n8n posts
 * { data: { workspace_id, api_key, records: [ { intern_id, sync_run_id, date, sales, ... } ] } };
 * the workspace is resolved from the api_key we sent, each record is matched to a
 * local intern (by Gencys intern id) and upserted per intern per day, and each
 * record's sync_run_id resolves its pending run.
 */
class InternDailyRecordController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $workspaceId = $request->input('data.workspace_id');
        $rawKey = $request->input('data.api_key');
        $records = $request->input('data.records', []);

        $apiKey = $rawKey ? WorkspaceApiKey::findByRawKey($rawKey) : null;

        if (! $apiKey || ($workspaceId && (int) $apiKey->workspace_id !== (int) $workspaceId)) {
            return response()->json(['message' => 'Invalid api_key for workspace.'], 401);
        }

        $apiKey->update(['last_used_at' => now()]);
        $workspace = $apiKey->workspace;

        // Map every referenced Gencys intern id to its local row id, once.
        $internIdMap = GencysIntern::where('workspace_id', $workspace->id)
            ->whereNotNull('intern_id')
            ->pluck('id', 'intern_id');

        $saved = 0;
        $skipped = 0;
        $rowsPerRun = [];

        foreach ($records as $record) {
            $internId = $this->intOrNull($record['intern_id'] ?? $record['internId'] ?? $record['id'] ?? null);
            $gencysInternId = $internId ? $internIdMap->get($internId) : null;

            if (! $gencysInternId) {
                $skipped++;

                continue;
            }

            GencysInternDailyRecord::updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'gencys_intern_id' => $gencysInternId,
                    'record_date' => $this->date($record['date'] ?? $record['record_date'] ?? null),
                ],
                [
                    'sales' => $this->decimalOrNull($record['sales'] ?? null),
                    'roas' => $this->decimalOrNull($record['roas'] ?? null),
                    'ad_spent' => $this->decimalOrNull($record['ad_spent'] ?? $record['adSpent'] ?? null),
                    'rts_rate' => $this->decimalOrNull($record['rts_rate'] ?? $record['rtsRate'] ?? null),
                    'rts_amount' => $this->decimalOrNull($record['rts_amount'] ?? $record['rtsAmount'] ?? null),
                ],
            );
            $saved++;

            if ($runId = $this->intOrNull($record['sync_run_id'] ?? $record['syncRunId'] ?? null)) {
                $rowsPerRun[$runId] = ($rowsPerRun[$runId] ?? 0) + 1;
            }
        }

        foreach ($rowsPerRun as $runId => $count) {
            GencysSyncRun::succeedById($workspace->id, $runId, $count, $count);
        }

        return response()->json([
            'saved' => $saved,
            'skipped' => $skipped,
        ]);
    }

    private function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        foreach (['m/d/Y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->toDateString();
            } catch (\Throwable) {
                // Try the next known format.
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Parse "1,888.00" → 1888.00, "" → null (strips thousands separators). */
    private function decimalOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = is_string($value) ? preg_replace('/[^0-9.\-]/', '', $value) : $value;

        return ($clean === '' || $clean === null) ? null : (float) $clean;
    }

    private function intOrNull(mixed $value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }
}
