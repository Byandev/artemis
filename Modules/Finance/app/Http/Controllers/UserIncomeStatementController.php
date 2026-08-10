<?php

namespace Modules\Finance\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Finance\Models\CommissionRate;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Services\UserIncomeStatementService;

/**
 * Per-user slices of a workspace income statement (gencys-partner workspaces),
 * nested under a saved statement. All computation + persistence lives in
 * {@see UserIncomeStatementService}; these actions just read the saved snapshot.
 */
class UserIncomeStatementController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly UserIncomeStatementService $service) {}

    /** Per-user P&L table for the parent statement's month. */
    public function index(Request $request, Workspace $workspace, IncomeStatement $incomeStatement)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);
        $this->ensureOwns($workspace, $incomeStatement);

        return Inertia::render('workspaces/finance/user-income-statements/index', [
            'workspace' => $workspace,
            'incomeStatement' => $this->statementContext($incomeStatement),
            ...$this->service->listPayload($incomeStatement),
            'missingUnitCodes' => $this->service->missingUnitCodes($incomeStatement),
        ]);
    }

    /** Workspace-wide per-product P&L table for the parent statement's month. */
    public function productIndex(Request $request, Workspace $workspace, IncomeStatement $incomeStatement)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);
        $this->ensureOwns($workspace, $incomeStatement);

        return Inertia::render('workspaces/finance/product-income-statements/index', [
            'workspace' => $workspace,
            'incomeStatement' => $this->statementContext($incomeStatement),
            ...$this->service->productListPayload($incomeStatement),
            'missingUnitCodes' => $this->service->missingUnitCodes($incomeStatement),
        ]);
    }

    /**
     * A single user's statement for the parent month, rendered through the same
     * page as the overall statement (read-only, scoped).
     */
    public function show(Request $request, Workspace $workspace, IncomeStatement $incomeStatement, User $user)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);
        $this->ensureOwns($workspace, $incomeStatement);

        return Inertia::render('workspaces/finance/income-statements/show', [
            'workspace' => $workspace,
            'mode' => 'saved',
            'readonly' => true,
            'base' => "/workspaces/{$workspace->slug}/finance/income-statements/{$incomeStatement->id}/users",
            'scope' => ['label' => $user->name],
            // Where the product breakdown saves a per-product commission rate.
            'commissionUrl' => "/workspaces/{$workspace->slug}/finance/income-statements/{$incomeStatement->id}/users/{$user->id}/commission-rate",
            'statement' => $this->service->userPayload($incomeStatement, $user),
        ]);
    }

    /** Set (or clear) this user's commission rate for a single product. */
    public function setCommissionRate(Request $request, Workspace $workspace, IncomeStatement $incomeStatement, User $user)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);
        $this->ensureOwns($workspace, $incomeStatement);

        $validated = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('workspace_id', $workspace->id)],
            'rate' => ['required', 'numeric', 'min:0', 'max:1'],
        ]);

        CommissionRate::updateOrCreate(
            ['workspace_id' => $workspace->id, 'user_id' => $user->id, 'product_id' => $validated['product_id']],
            ['rate' => $validated['rate']],
        );

        return redirect()->back()->with('success', 'Commission rate saved.');
    }

    /** @return array{id:int, period_month:string, month:string, label:string} */
    private function statementContext(IncomeStatement $incomeStatement): array
    {
        return [
            'id' => $incomeStatement->id,
            'period_month' => $incomeStatement->period_month->toDateString(),
            'month' => $incomeStatement->period_month->format('Y-m'),
            'label' => $incomeStatement->period_month->format('F Y'),
        ];
    }

    private function guard(Request $request, Workspace $workspace): void
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }
    }

    private function ensureOwns(Workspace $workspace, IncomeStatement $incomeStatement): void
    {
        if ($incomeStatement->workspace_id !== $workspace->id) {
            abort(404);
        }
    }
}
