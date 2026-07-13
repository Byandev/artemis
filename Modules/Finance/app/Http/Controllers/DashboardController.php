<?php

namespace Modules\Finance\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Transaction;

class DashboardController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);

        $accounts = Account::where('workspace_id', $workspace->id)
            ->orderBy('name')->get();

        // Get the last transaction per account (by date desc, id desc) for running_balance
        $lastTxnPerAccount = Transaction::where('workspace_id', $workspace->id)
            ->whereIn('id', function ($q) use ($workspace) {
                $q->selectRaw('(SELECT t2.id FROM finance_transactions t2 WHERE t2.account_id = finance_transactions.account_id AND t2.workspace_id = ? ORDER BY t2.date DESC, t2.position DESC LIMIT 1)', [$workspace->id])
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

        // KPI totals (cash in/out, unreconciled) now load lazily via the
        // per-statistic endpoints in FinanceDashboardStatsController; this shell
        // only provides the accounts list the page renders directly.
        return Inertia::render('workspaces/finance/dashboard', [
            'workspace' => $workspace,
            'accounts' => $accountsData,
        ]);
    }
}
