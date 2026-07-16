<?php

namespace Modules\Finance\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Product;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Finance\Http\Requests\FundRequestRequest;
use Modules\Finance\Models\FundRequest;
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
                    'chargeToUser:id,name',
                    'approver:id,name',
                    'items',
                ])
        )
            ->allowedFilters([
                AllowedFilter::callback('search', fn ($q, $v) => $q->where(function ($sub) use ($v) {
                    $sub->where('reference_no', 'like', "%{$v}%")
                        ->orWhere('purpose', 'like', "%{$v}%");
                })),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('template'),
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
            'statuses' => FundRequest::STATUSES,
            'templates' => FundRequest::TEMPLATES,
            'products' => $this->productOptions($request, $workspace),
            'myPages' => $this->assignedPageOptions($request, $workspace),
            'myGotymeNumber' => $request->user()->gotyme_number,
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

        DB::transaction(function () use ($validated, $workspace) {
            // New requests always start pending; the reference number is generated
            // here (never supplied by the client) and approval happens via updateStatus.
            $fundRequest = FundRequest::create([
                ...$this->attributesFor($validated, $workspace),
                'workspace_id' => $workspace->id,
                'reference_no' => $this->nextReferenceNo($workspace),
                'status' => 'pending',
                'approved_by' => null,
            ]);

            $this->syncItems($fundRequest, $validated, $workspace);
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

        DB::transaction(function () use ($validated, $workspace, $requestFund) {
            // reference_no is intentionally omitted from validated data, so it stays put.
            $requestFund->update($this->attributesFor($validated, $workspace));

            $this->syncItems($requestFund, $validated, $workspace);
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
     * The column values for a request, minus the line items (which live in their
     * own table). An Ad Spent request derives its amount from those items rather
     * than trusting the figure the client sent.
     */
    protected function attributesFor(array $validated, Workspace $workspace): array
    {
        $attributes = collect($validated)->except('items')->all();

        if (($validated['template'] ?? null) === FundRequest::TEMPLATE_AD_SPENT) {
            $attributes['amount_requested'] = collect($validated['items'] ?? [])
                ->sum(fn (array $item) => $this->lineTotal($item));
        }

        return $attributes;
    }

    /**
     * Replace a request's line items with the submitted set. Items are rewritten
     * rather than diffed: nothing references them, and the order on the form is
     * the order that matters.
     */
    protected function syncItems(FundRequest $fundRequest, array $validated, Workspace $workspace): void
    {
        $fundRequest->items()->delete();

        // Line items belong to the Ad Spent template only, so switching a request
        // back to blank leaves it with none.
        if (($validated['template'] ?? null) !== FundRequest::TEMPLATE_AD_SPENT) {
            return;
        }

        $items = array_values($validated['items'] ?? []);

        $productNames = Product::where('workspace_id', $workspace->id)
            ->whereIn('id', collect($items)->pluck('product_id')->filter()->unique())
            ->pluck('name', 'id');

        foreach ($items as $index => $item) {
            $fundRequest->items()->create([
                'product_id' => $item['product_id'],
                'page_id' => $item['page_id'] ?? null,
                'item_label' => $productNames[$item['product_id']] ?? '',
                'creatives_running' => $item['creatives_running'],
                'budget_per_day' => $item['budget_per_day'],
                'days' => $item['days'],
                'total' => $this->lineTotal($item),
                'sort_order' => $index,
            ]);
        }
    }

    protected function lineTotal(array $item): float
    {
        return round((float) $item['budget_per_day'] * (float) $item['days'], 2);
    }

    /**
     * Products the user may request against, for the Ad Spent item picker.
     */
    protected function productOptions(Request $request, Workspace $workspace)
    {
        return Product::ofWorkspace($workspace)
            ->visibleTo($request->user(), $workspace)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * The signed-in user's own pages, each carrying its most recent daily budget
     * so the form can auto-fill "budget per day" once a page is picked. A page
     * reaches its product through its shop (shops.product_id), which is what lets
     * the picker narrow pages down to the chosen product.
     */
    protected function assignedPageOptions(Request $request, Workspace $workspace)
    {
        return Page::ofWorkspace($workspace)
            ->where('owner_id', $request->user()->id)
            // latestBudget is a latestOfMany relation: it self-joins, so naming
            // columns here makes `page_id` ambiguous. Load the whole row.
            ->with(['shop:id,product_id', 'latestBudget'])
            ->orderBy('name')
            ->get(['id', 'name', 'shop_id'])
            ->map(fn (Page $page) => [
                'id' => $page->id,
                'name' => $page->name,
                'product_id' => $page->shop?->product_id,
                'budget_per_day' => $page->latestBudget?->budget,
                'budget_date' => $page->latestBudget?->date?->toDateString(),
            ])
            ->values();
    }
}
