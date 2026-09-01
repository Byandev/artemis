<?php

namespace Modules\Finance\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Models\LossCarryover;
use Modules\Finance\Services\ProductIncomeStatementService;
use Modules\Finance\Services\UserIncomeStatementService;
use Modules\Finance\Services\UserProductIncomeStatementService;

/**
 * The per-user, per-product and per-user-per-product slices of a workspace
 * income statement, nested under a saved statement. All computation and
 * persistence lives in the services; these actions just read the saved
 * snapshots.
 */
class UserIncomeStatementController extends Controller
{
    use AuthorizesRequests;

    /** The drill-down path segment standing in for a null user id. */
    private const UNASSIGNED = 'unassigned';

    public function __construct(
        private readonly UserIncomeStatementService $service,
        private readonly ProductIncomeStatementService $productService,
        private readonly UserProductIncomeStatementService $userProductService,
    ) {}

    /** Per-user P&L table for the parent statement's month. */
    public function index(Request $request, Workspace $workspace, IncomeStatement $incomeStatement)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);
        $this->ensureOwns($workspace, $incomeStatement);

        return Inertia::render('workspaces/finance/user-income-statements/index', [
            'workspace' => $workspace,
            'incomeStatement' => $this->statementContext($incomeStatement),
            ...$this->service->payload($incomeStatement),
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
            ...$this->productService->payload($incomeStatement),
        ]);
    }

    /**
     * One seller's products: the cross statement narrowed to a single user, off
     * the per-user list. `$user` is a user id, or "unassigned" for the row of
     * orders credited to nobody.
     */
    public function userShow(Request $request, Workspace $workspace, IncomeStatement $incomeStatement, string $user)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);
        $this->ensureOwns($workspace, $incomeStatement);

        if ($user !== self::UNASSIGNED && ! ctype_digit($user)) {
            abort(404);
        }

        $payload = $this->userProductService->userPayload(
            $incomeStatement,
            $user === self::UNASSIGNED ? null : (int) $user,
        );

        // Only what the statement actually holds can be drilled into.
        abort_if($payload === null, 404);

        return Inertia::render('workspaces/finance/user-income-statements/show', [
            'workspace' => $workspace,
            'incomeStatement' => $this->statementContext($incomeStatement),
            ...$payload,
        ]);
    }

    /**
     * Per-user-per-product P&L table: one row per seller/product pair, for a
     * product several people run and a person running several products.
     */
    public function userProductIndex(Request $request, Workspace $workspace, IncomeStatement $incomeStatement)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);
        $this->ensureOwns($workspace, $incomeStatement);

        return Inertia::render('workspaces/finance/user-product-income-statements/index', [
            'workspace' => $workspace,
            'incomeStatement' => $this->statementContext($incomeStatement),
            ...$this->userProductService->payload($incomeStatement),
        ]);
    }

    /**
     * Record what one seller carried into the month on one product.
     *
     * Entered at the finest grain the statements report at, so it adds up on
     * its own: the seller's own total, the product's across its sellers, and
     * the month's across both. Saving one re-snapshots the slices so those
     * roll-ups are true again straight away.
     *
     * An amount of nought removes the entry rather than storing a zero — the
     * absence of a deficit and a deficit of nothing are the same thing here,
     * and keeping rows of nought would make the month look edited when it is
     * not.
     */
    public function storeLossCarryover(Request $request, Workspace $workspace, IncomeStatement $incomeStatement)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceDashboard->value, $workspace);
        $this->ensureOwns($workspace, $incomeStatement);

        $validated = $request->validate([
            // Null on either side is the unattributed bucket that side reports.
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'product_id' => [
                'nullable', 'integer',
                Rule::exists('products', 'id')->where('workspace_id', $workspace->id),
            ],
            // What is owed, held positive: a carryover is a hole to fill.
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $key = [
            'workspace_id' => $workspace->id,
            'period_month' => $incomeStatement->period_month->copy()->startOfMonth()->toDateString(),
            'user_id' => $validated['user_id'] ?? null,
            'product_id' => $validated['product_id'] ?? null,
        ];

        if ((float) $validated['amount'] <= 0) {
            LossCarryover::where($key)->delete();
        } else {
            LossCarryover::updateOrCreate($key, ['amount' => $validated['amount']]);
        }

        // The slices carry the figure, so they have to be rebuilt for the
        // roll-ups above it to agree again.
        $this->userProductService->snapshot($incomeStatement);
        $this->service->snapshot($incomeStatement);
        $this->productService->snapshot($incomeStatement);

        return redirect()->back()->with('success', 'Loss carried forward saved.');
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
