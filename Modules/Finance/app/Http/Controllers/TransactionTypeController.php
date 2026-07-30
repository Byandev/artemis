<?php

namespace Modules\Finance\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Finance\Models\TransactionType;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TransactionTypeController extends Controller
{
    use AuthorizesRequests;

    protected function guard(Request $request, Workspace $workspace): void
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }
    }

    protected function ensureOwns(Workspace $workspace, TransactionType $transactionType): void
    {
        if ($transactionType->workspace_id !== $workspace->id) {
            abort(404);
        }
    }

    public function index(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceTransactions->value, $workspace);

        $types = QueryBuilder::for(TransactionType::where('workspace_id', $workspace->id))
            ->allowedFilters([
                AllowedFilter::callback('search', fn ($q, $v) => $q->where('name', 'like', "%{$v}%")),
            ])
            ->allowedSorts(['id', 'name', 'created_at'])
            ->defaultSort('name')
            ->paginate($request->input('per_page', 25))
            ->withQueryString();

        return Inertia::render('workspaces/finance/transaction-types/index', [
            'workspace' => $workspace,
            'types' => $types,
            'query' => [
                ...$request->only(['sort', 'per_page', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    protected function rules(Workspace $workspace, ?TransactionType $transactionType = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('finance_transaction_types', 'name')
                    ->where('workspace_id', $workspace->id)
                    ->ignore($transactionType?->id),
            ],
            'is_gross_profit_deduction' => ['boolean'],
        ];
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::CreateFinanceTransactions->value, $workspace);

        $validated = $request->validate($this->rules($workspace));

        TransactionType::create([
            'workspace_id' => $workspace->id,
            'name' => trim($validated['name']),
            'is_gross_profit_deduction' => $validated['is_gross_profit_deduction'] ?? false,
        ]);

        return redirect()->back()->with('success', 'Transaction type created.');
    }

    public function update(Request $request, Workspace $workspace, TransactionType $transactionType)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::EditFinanceTransactions->value, $workspace);
        $this->ensureOwns($workspace, $transactionType);

        $validated = $request->validate($this->rules($workspace, $transactionType));

        $transactionType->update([
            'name' => trim($validated['name']),
            'is_gross_profit_deduction' => $validated['is_gross_profit_deduction'] ?? false,
        ]);

        return redirect()->back()->with('success', 'Transaction type updated.');
    }

    public function destroy(Request $request, Workspace $workspace, TransactionType $transactionType)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::DeleteFinanceTransactions->value, $workspace);
        $this->ensureOwns($workspace, $transactionType);

        $transactionType->delete();

        return redirect()->back()->with('success', 'Transaction type deleted.');
    }
}
