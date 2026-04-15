<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Finance\Http\Requests\RemittanceRequest;
use Modules\Finance\Models\Remittance;
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

    public function index(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);

        $remittances = QueryBuilder::for(
            Remittance::where('workspace_id', $workspace->id)->with('transaction.account')
        )
            ->allowedFilters([
                AllowedFilter::callback('search', fn ($q, $v) =>
                    $q->where('courier', 'like', "%{$v}%")->orWhere('reference_no', 'like', "%{$v}%")),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('unreconciled', function ($q, $v) {
                    if ((bool) $v) {
                        $q->doesntHave('transaction');
                    }
                }),
            ])
            ->allowedSorts(['id', 'date', 'courier', 'gross_amount', 'net_amount', 'status', 'created_at'])
            ->defaultSort('-date', '-created_at')
            ->paginate(15)
            ->withQueryString();

        $remittances->through(function (Remittance $r) {
            $r->is_reconciled = (bool) $r->transaction;

            return $r;
        });

        $unreconciledCount = Remittance::where('workspace_id', $workspace->id)
            ->doesntHave('transaction')->count();

        return Inertia::render('workspaces/finance/remittances/index', [
            'workspace' => $workspace,
            'remittances' => $remittances,
            'unreconciledCount' => $unreconciledCount,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function store(RemittanceRequest $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);

        Remittance::create([...$request->validated(), 'workspace_id' => $workspace->id]);

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
                'is_reconciled' => (bool) $remittance->transaction,
            ],
        ]);
    }

    public function update(RemittanceRequest $request, Workspace $workspace, Remittance $remittance)
    {
        $this->guard($request, $workspace);
        $this->ensureOwns($workspace, $remittance);

        $remittance->update($request->validated());

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
