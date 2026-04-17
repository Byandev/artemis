<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Remittance;
use Modules\Finance\Models\Transaction;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        return Inertia::render('workspaces/finance/dashboard', [
            'workspace' => $workspace,
            'accounts' => Account::where('workspace_id', $workspace->id)
                ->with('transactions:id,account_id,type,amount,date')
                ->orderBy('name')->get(),
            'transactions' => Transaction::where('workspace_id', $workspace->id)
                ->get(['id', 'type', 'amount', 'date']),
            'remittances' => Remittance::where('workspace_id', $workspace->id)
                ->get(['id', 'transaction_id']),
        ]);
    }
}
