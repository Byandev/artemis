<?php

namespace Modules\Finance\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Finance\Http\Requests\RequestFundRequest;
use Modules\Finance\Models\RequestFund;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class RequestFundController extends Controller
{
    use AuthorizesRequests;

    protected function guard(Request $request, Workspace $workspace): void
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }
    }

    protected function ensureOwns(Workspace $workspace, RequestFund $requestFund): void
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
            RequestFund::where('workspace_id', $workspace->id)
                ->with([
                    'requester:id,name',
                    'chargeToUser:id,name',
                    'approver:id,name',
                ])
        )
            ->allowedFilters([
                AllowedFilter::callback('search', fn ($q, $v) => $q->where(function ($sub) use ($v) {
                    $sub->where('reference_no', 'like', "%{$v}%")
                        ->orWhere('purpose', 'like', "%{$v}%");
                })),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('charge_to'),
                AllowedFilter::exact('requested_by'),
            ])
            ->allowedSorts([
                'reference_no',
                'request_date',
                'amount_requested',
                'date_needed',
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
            'statuses' => RequestFund::STATUSES,
            'canApproveStatus' => $request->user()->can(Permission::ApproveFinanceRequestFunds->value, $workspace),
            'query' => [
                ...$request->only(['sort', 'per_page', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function store(RequestFundRequest $request, Workspace $workspace)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::CreateFinanceRequestFunds->value, $workspace);

        // New requests always start pending; the reference number is generated
        // here (never supplied by the client) and approval happens via updateStatus.
        RequestFund::create([
            ...$request->validated(),
            'workspace_id' => $workspace->id,
            'reference_no' => $this->nextReferenceNo($workspace),
            'status' => 'pending',
            'approved_by' => null,
        ]);

        return redirect()->route('workspaces.finance.request-funds.index', $workspace->slug)
            ->with('success', 'Fund request created.');
    }

    public function update(RequestFundRequest $request, Workspace $workspace, RequestFund $requestFund)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::EditFinanceRequestFunds->value, $workspace);
        $this->ensureOwns($workspace, $requestFund);

        // reference_no is intentionally omitted from validated data, so it stays put.
        $requestFund->update($request->validated());

        return redirect()->route('workspaces.finance.request-funds.index', $workspace->slug)
            ->with('success', 'Fund request updated.');
    }

    public function destroy(Request $request, Workspace $workspace, RequestFund $requestFund)
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
    public function updateStatus(Request $request, Workspace $workspace, RequestFund $requestFund)
    {
        $this->guard($request, $workspace);
        $this->authorize(Permission::ApproveFinanceRequestFunds->value, $workspace);
        $this->ensureOwns($workspace, $requestFund);

        $validated = $request->validate([
            'status' => ['required', Rule::in(RequestFund::STATUSES)],
        ]);

        $requestFund->update([
            'status' => $validated['status'],
            'approved_by' => in_array($validated['status'], RequestFund::APPROVED_STATUSES, true)
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
        $count = RequestFund::where('workspace_id', $workspace->id)->count();

        return 'RF-'.str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    }
}
