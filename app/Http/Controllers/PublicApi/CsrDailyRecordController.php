<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Models\PancakeUserDailyReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CsrDailyRecordController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $validated = $request->validate([
            'pancake_user_id'       => ['required', 'integer', 'exists:users,id'],
            'date'         => ['required', 'date_format:Y-m-d'],
            'type'         => ['required', 'string', 'max:100', 'in:erp,pos'],
            'total_orders' => ['required', 'integer', 'min:0'],
            'total_sales'  => ['required', 'numeric', 'min:0'],
            'returning'    => ['required', 'integer', 'min:0'],
            'delivered'    => ['required', 'integer', 'min:0'],
            'rts_rate'     => ['required', 'numeric', 'min:0'],
            'rmo_called'   => ['nullable', 'integer', 'min:0'],
        ]);

        // Ensure the pancake_user_id belongs to this workspace
        $isMember = $workspace->users()->where('users.id', $validated['pancake_user_id'])->exists();

        if (! $isMember) {
            return response()->json(['error' => 'The specified CSR does not belong to this workspace.'], 422);
        }

        $record = PancakeUserDailyReport::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'pancake_user_id'       => $validated['pancake_user_id'],
                'date'         => $validated['date'],
                'type'         => $validated['type'] ?? null,
            ],
            [
                'total_orders' => $validated['total_orders'],
                'total_sales'  => $validated['total_sales'],
                'returning'    => $validated['returning'],
                'delivered'    => $validated['delivered'],
                'rts_rate'     => $validated['rts_rate'],
                'rmo_called'   => $validated['rmo_called'] ?? 0,
            ]
        );

        return response()->json([
            'data'    => $record,
            'created' => $record->wasRecentlyCreated,
        ], $record->wasRecentlyCreated ? 201 : 200);
    }
}
