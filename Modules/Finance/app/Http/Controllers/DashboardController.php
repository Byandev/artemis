<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        $accounts = Account::where('workspace_id', $workspace->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'currency' => $a->currency,
                'current_balance' => (float) $a->current_balance,
            ]);

        $totalBalance = $accounts->sum('current_balance');

        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $monthIn = (float) Transaction::where('workspace_id', $workspace->id)
            ->where('type', 'in')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])->sum('amount');

        $monthOut = (float) Transaction::where('workspace_id', $workspace->id)
            ->where('type', 'out')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])->sum('amount');

        $unreconciledCount = Remittance::where('workspace_id', $workspace->id)
            ->doesntHave('transaction')->count();

        return Inertia::render('workspaces/finance/dashboard', [
            'workspace' => $workspace,
            'accounts' => $accounts,
            'totalBalance' => $totalBalance,
            'monthIn' => $monthIn,
            'monthOut' => $monthOut,
            'unreconciledCount' => $unreconciledCount,
        ]);
    }
}
