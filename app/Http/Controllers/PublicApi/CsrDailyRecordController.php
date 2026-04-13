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
            'csr_id'       => ['required', ],
            'date'         => ['required',],
            'total_orders' => ['required', 'integer', 'min:0'],
            'total_sales'  => ['required', 'numeric', 'min:0'],
            'returning'    => ['required', 'integer', 'min:0'],
            'delivered'    => ['required', 'integer', 'min:0'],
            'rts_rate'     => ['required', 'numeric', 'min:0'],
            'rmo_called'   => ['nullable', 'integer', 'min:0'],
        ]);


        $record = PancakeUserDailyReport::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'pancake_user_id'  => $validated['csr_id'],
                'date'         => $validated['date'],
                'type'         => 'ERP',
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
