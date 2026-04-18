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

        $accounts = Account::where('workspace_id', $workspace->id)
            ->orderBy('name')->get();

        // Get the last transaction per account (by date desc, id desc) for running_balance
        $lastTxnPerAccount = Transaction::where('workspace_id', $workspace->id)
            ->whereIn('id', function ($q) use ($workspace) {
                $q->selectRaw('(SELECT t2.id FROM finance_transactions t2 WHERE t2.account_id = finance_transactions.account_id AND t2.workspace_id = ? ORDER BY t2.date DESC, t2.id DESC LIMIT 1)', [$workspace->id])
                    ->from('finance_transactions')
                    ->where('workspace_id', $workspace->id)
                    ->groupBy('account_id');
            })
            ->get()
            ->keyBy('account_id');

        $accountsData = $accounts->map(function ($account) use ($lastTxnPerAccount) {
            $lastTxn = $lastTxnPerAccount->get($account->id);

            return [
                'id' => $account->id,
                'name' => $account->name,
                'currency' => $account->currency,
                'opening_balance' => $account->opening_balance,
                'is_active' => $account->is_active,
                'balance' => $lastTxn ? (float) $lastTxn->running_balance : (float) $account->opening_balance,
            ];
        });

        // Compute totals via aggregate
        $totals = Transaction::where('workspace_id', $workspace->id)
            ->selectRaw("
                SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END) as total_in,
                SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END) as total_out
            ")
            ->first();

        return Inertia::render('workspaces/finance/dashboard', [
            'workspace' => $workspace,
            'accounts' => $accountsData,
            'totalIn' => round((float) ($totals->total_in ?? 0), 2),
            'totalOut' => round((float) ($totals->total_out ?? 0), 2),
            'unreconciledCount' => Remittance::where('workspace_id', $workspace->id)
                ->whereNull('transaction_id')->count(),
        ]);
    }
}
