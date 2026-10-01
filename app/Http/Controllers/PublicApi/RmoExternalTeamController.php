<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Support\RmoExternalTeamSheet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Callback for the external RMO team's sheet. `rmo:trigger-external-team-sync`
 * pings n8n, n8n reads the day's tab and posts the rows back here with the
 * workspace API key (stored as an n8n credential) in the header.
 */
class RmoExternalTeamController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'rows' => ['present', 'array'],
            'rows.*.tracking_number' => ['nullable', 'string'],
            'rows.*.status' => ['nullable', 'string'],
        ]);

        $workspace = $request->attributes->get('workspace');

        // Settings → RMO management decides which workspaces take the sheet.
        if (! $workspace->rmoExternalTeamSyncEnabled()) {
            return response()->json([
                'message' => 'External team sync is not enabled for this workspace.',
            ], 403);
        }

        $result = RmoExternalTeamSheet::apply(
            $workspace,
            $validated['date'] ?? now()->toDateString(),
            $validated['rows'],
        );

        return response()->json(['data' => $result]);
    }
}
