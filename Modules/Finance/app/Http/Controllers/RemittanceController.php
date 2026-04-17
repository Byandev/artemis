<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Modules\Finance\Http\Requests\RemittanceRequest;
use Modules\Finance\Models\Remittance;
use Modules\Finance\Models\Transaction;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class RemittanceController extends Controller
{
    protected function guard(Request $request, Workspace $workspace): void
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }
    }

    protected function ensureOwns(Workspace $workspace, Remittance $remittance): void
    {
        if ($remittance->workspace_id !== $workspace->id) {
            abort(404);
        }
    }

    protected function validateTransactionFor(Workspace $workspace, ?int $transactionId): void
    {
        if (! $transactionId) {
            return;
        }
        $ok = Transaction::where('id', $transactionId)
            ->where('workspace_id', $workspace->id)->exists();
        if (! $ok) {
            throw ValidationException::withMessages(['transaction_id' => 'Invalid transaction for this workspace.']);
        }
    }

    public function index(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);

        $remittances = QueryBuilder::for(
            Remittance::where('workspace_id', $workspace->id)->with('transaction.account')
        )
            ->allowedFilters([
                AllowedFilter::callback('search', fn ($q, $v) =>
                    $q->where('soa_number', 'like', "%{$v}%")->orWhere('courier', 'like', "%{$v}%")),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('unreconciled', function ($q, $v) {
                    if ((bool) $v) {
                        $q->whereNull('transaction_id');
                    }
                }),
            ])
            ->allowedSorts(['id', 'billing_date_from', 'billing_date_to', 'courier', 'soa_number', 'gross_cod', 'net_amount', 'status', 'created_at'])
            ->defaultSort('-billing_date_to', '-created_at')
            ->paginate(15)
            ->withQueryString();

        $remittances->through(function (Remittance $r) {
            $r->is_reconciled = $r->transaction_id !== null;

            return $r;
        });

        $unreconciledCount = Remittance::where('workspace_id', $workspace->id)
            ->whereNull('transaction_id')->count();

        return Inertia::render('workspaces/finance/remittances/index', [
            'workspace' => $workspace,
            'remittances' => $remittances,
            'unreconciledCount' => $unreconciledCount,
            'transactions' => Transaction::where('workspace_id', $workspace->id)
                ->with('account')
                ->orderByDesc('date')
                ->limit(200)
                ->get(['id', 'account_id', 'date', 'description', 'amount', 'type']),
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function store(RemittanceRequest $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $data = $request->validated();
        $this->validateTransactionFor($workspace, $data['transaction_id'] ?? null);

        Remittance::create([...$data, 'workspace_id' => $workspace->id]);

        return redirect()->route('workspaces.finance.remittances.index', $workspace->slug)
            ->with('success', 'Remittance created.');
    }

    public function show(Request $request, Workspace $workspace, Remittance $remittance)
    {
        $this->guard($request, $workspace);
        $this->ensureOwns($workspace, $remittance);

        $remittance->load('transaction.account');

        return Inertia::render('workspaces/finance/remittances/show', [
            'workspace' => $workspace,
            'remittance' => [
                ...$remittance->toArray(),
                'is_reconciled' => $remittance->transaction_id !== null,
            ],
        ]);
    }

    public function update(RemittanceRequest $request, Workspace $workspace, Remittance $remittance)
    {
        $this->guard($request, $workspace);
        $this->ensureOwns($workspace, $remittance);
        $data = $request->validated();
        $this->validateTransactionFor($workspace, $data['transaction_id'] ?? null);

        $remittance->update($data);

        return redirect()->back()->with('success', 'Remittance updated.');
    }

    public function destroy(Request $request, Workspace $workspace, Remittance $remittance)
    {
        $this->guard($request, $workspace);
        $this->ensureOwns($workspace, $remittance);

        $remittance->delete();

        return redirect()->route('workspaces.finance.remittances.index', $workspace->slug)
            ->with('success', 'Remittance deleted.');
    }
}
