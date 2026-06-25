<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Jobs\SyncErpTransactionHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionHistoryController extends Controller
{
    /**
     * Receive ERP transaction history synced back by n8n.
     *
     * The payload can be large (the full ERP history for a workspace), so the rows are
     * handed to a queued job and the request returns immediately — matching each row's
     * "Items" product name to an inventory item happens off the request.
     *
     * Expected payload (raw ERP columns, one object per transaction):
     * {
     *   "transactions": [
     *     {
     *       "No": "278",
     *       "Transaction Date": "June 24, 2026",
     *       "Items": "Anti-Stroke Cooling Patch",
     *       "Type": "OUT",
     *       "Remaining Qty": "2259",
     *       "qty_in": "0",
     *       "qty_out": "15",
     *       "rts_goods_in": "0",
     *       "rts_goods_out": "11",
     *       "In - Damage (RTS)": "0"
     *     }
     *   ]
     * }
     */
    public function sync(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $request->validate([
            'transactions' => ['present', 'array'],
            'transactions.*.No' => ['required'],
            'transactions.*.Items' => ['required', 'string'],
        ]);

        $rows = $request->input('transactions', []);

        // Process in-request so the data is saved instantly and the response reflects
        // the real result (no queue worker required).
        SyncErpTransactionHistory::dispatchSync($workspace->id, $rows);

        return response()->json([
            'data' => [
                'transactions_received' => count($rows),
                'status' => 'synced',
            ],
        ]);
    }
}
