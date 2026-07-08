<?php

namespace Modules\GencysERP\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\GencysERP\Models\GencysIntern;

/**
 * Callback for the n8n interns sync. n8n posts { data: { workspace_id, api_key,
 * interns: [...] } } — the workspace is resolved from the api_key we sent in the
 * webhook, and its interns are upserted (keyed on Gencys' intern id).
 */
class InternController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $workspaceId = $request->input('data.workspace_id');
        $rawKey = $request->input('data.api_key');
        $interns = $request->input('data.interns', []);

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

            $record = GencysIntern::updateOrCreate(
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

        return response()->json([
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
