<?php

namespace Modules\Finance\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Product;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Finance\Http\Requests\FundRequestRequest;
use Modules\Finance\Models\FundRequest;
use Modules\Finance\Models\TransactionType;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class FundRequestController extends Controller
{
    use AuthorizesRequests;

    protected function guard(Request $request, Workspace $workspace): void
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }
    }

    protected function ensureOwns(Workspace $workspace, FundRequest $requestFund): void
    {
        if ($requestFund->workspace_id !== $workspace->id) {
            abort(404);
        }
    }

    public function index(Request $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ViewFinanceRequestFunds->value, $workspace);

        $requestFunds = QueryBuilder::for(
            FundRequest::where('workspace_id', $workspace->id)
                ->with([
                    'requester:id,name',
                    'chargeToUsers:users.id,users.name',
                    'approver:id,name',
                    'productShares',
                    'transactionType:id,name',
                    'department:id,name',
                ])
        )
            ->allowedFilters([
                AllowedFilter::callback('search', fn ($q, $v) => $q->where(function ($sub) use ($v) {
                    $sub->where('reference_no', 'like', "%{$v}%");
                })),
                AllowedFilter::exact('status'),
                // charge_to is a pivot now, so the filter matches any request
                // the user bears a share of.
                AllowedFilter::callback('charge_to', fn ($q, $v) => $q->whereHas(
                    'chargeToUsers', fn ($sub) => $sub->where('users.id', $v)
                )),
                AllowedFilter::exact('requested_by'),
            ])
            ->allowedSorts([
                'reference_no',
                'request_date',
                'amount_requested',
                'status',
                'created_at',
                AllowedSort::field('id', 'id'),
            ])
            ->defaultSort('-request_date')
            ->paginate($request->input('per_page', 15))
            ->withQueryString();

        return Inertia::render('workspaces/finance/request-funds/index', [
            'workspace' => $workspace,
            'requestFunds' => $requestFunds,
            'users' => $workspace->users()->get(['users.id', 'users.name']),
            'statuses' => FundRequest::STATUSES,
            'products' => $this->productOptions($request, $workspace),
            'transactionTypes' => TransactionType::where('workspace_id', $workspace->id)
                ->orderBy('name')->get(['id', 'name']),
            'departments' => Department::ofWorkspace($workspace)
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'canApproveStatus' => $request->user()->can(Permission::ApproveFinanceRequestFunds->value, $workspace),
            'query' => [
                ...$request->only(['sort', 'per_page', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function store(FundRequestRequest $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::CreateFinanceRequestFunds->value, $workspace);

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $request, $workspace) {
            // New requests always start pending; the reference number is generated
            // here (never supplied by the client) and approval happens via
            // updateStatus. The request date is the creation date and the
            // requester is the signed-in user — neither is entered on the form.
            $fundRequest = FundRequest::create([
                ...$this->attributesFor($validated, $workspace),
                'workspace_id' => $workspace->id,
                'reference_no' => $this->nextReferenceNo($workspace),
                'request_date' => now()->toDateString(),
                'requested_by' => $request->user()->id,
                'status' => 'pending',
                'approved_by' => null,
            ]);

            $this->syncShares($fundRequest, $request, $workspace);
        });

        return redirect()->route('workspaces.finance.request-funds.index', $workspace->slug)
            ->with('success', 'Fund request created.');
    }

    public function update(FundRequestRequest $request, Workspace $workspace, FundRequest $requestFund)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::EditFinanceRequestFunds->value, $workspace);
        $this->ensureOwns($workspace, $requestFund);

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $request, $workspace, $requestFund) {
            // reference_no is intentionally omitted from validated data, so it stays put.
            $requestFund->update($this->attributesFor($validated, $workspace));

            $this->syncShares($requestFund, $request, $workspace);
        });

        return redirect()->route('workspaces.finance.request-funds.index', $workspace->slug)
            ->with('success', 'Fund request updated.');
    }

    public function destroy(Request $request, Workspace $workspace, FundRequest $requestFund)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::DeleteFinanceRequestFunds->value, $workspace);
        $this->ensureOwns($workspace, $requestFund);

        $requestFund->delete();

        return redirect()->route('workspaces.finance.request-funds.index', $workspace->slug)
            ->with('success', 'Fund request deleted.');
    }

    /**
     * Approve / release / cancel a request. Gated by a dedicated permission so
     * that status changes are separated from ordinary edits. The approver is
     * stamped when moving into an approved/released state and cleared otherwise.
     */
    public function updateStatus(Request $request, Workspace $workspace, FundRequest $requestFund)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ApproveFinanceRequestFunds->value, $workspace);
        $this->ensureOwns($workspace, $requestFund);

        $validated = $request->validate([
            'status' => ['required', Rule::in(FundRequest::STATUSES)],
        ]);

        $requestFund->update([
            'status' => $validated['status'],
            'approved_by' => in_array($validated['status'], FundRequest::APPROVED_STATUSES, true)
                ? ($requestFund->approved_by ?? $request->user()->id)
                : null,
        ]);

        return back()->with('success', 'Status updated.');
    }

    /**
     * The next sequential reference number for a workspace, e.g. RF-00007.
     */
    protected function nextReferenceNo(Workspace $workspace): string
    {
        $count = FundRequest::where('workspace_id', $workspace->id)->count();

        return 'RF-'.str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    }

    /**
     * The column values for a request. charge_to / products are pivots, not
     * columns, so they are stripped here and synced after the row exists.
     */
    protected function attributesFor(array $validated, Workspace $workspace): array
    {
        return collect($validated)->except(['charge_to', 'products'])->all();
    }

    /**
     * Replace a request's charge-to and product shares with the submitted sets.
     * Both are rewritten rather than diffed — the product rows have no natural
     * key to sync against, and the order on the form is the order that matters.
     */
    protected function syncShares(FundRequest $fundRequest, FundRequestRequest $request, Workspace $workspace): void
    {
        $fundRequest->chargeToUsers()->sync(
            collect($request->chargeToShares())
                ->mapWithKeys(fn ($share) => [$share['user_id'] => ['amount' => $share['amount']]])
                ->all()
        );

        $shares = $request->productShares();

        $fundRequest->productShares()->delete();

        if ($shares === []) {
            return;
        }

        // Name snapshots, so a row still reads after its product is deleted and
        // so a transaction filled in from this request has something to tag.
        $names = Product::where('workspace_id', $workspace->id)
            ->whereIn('id', array_column($shares, 'product_id'))
            ->pluck('name', 'id');

        $fundRequest->productShares()->createMany(
            collect($shares)->map(fn ($share, $index) => [
                'product_id' => $share['product_id'],
                'product_label' => $names[$share['product_id']] ?? '',
                'amount' => $share['amount'],
                'sort_order' => $index,
            ])->all()
        );
    }

    /**
     * Products the user may request against, for the product-share picker.
     */
    protected function productOptions(Request $request, Workspace $workspace)
    {
        return Product::ofWorkspace($workspace)
            ->visibleTo($request->user(), $workspace)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
