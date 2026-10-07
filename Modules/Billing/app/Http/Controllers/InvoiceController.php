<?php

namespace Modules\Billing\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Workspace;
use App\Support\InvoicePdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * A workspace's own invoices. The admin area lists every invoice across the
 * platform; this is the same data narrowed to one workspace, for the people
 * who are actually billed for it.
 */
class InvoiceController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace): Response
    {
        $this->ensureAvailable($request, $workspace);
        $this->authorize(Permission::ViewInvoices->value, $workspace);

        // Both ends are inclusive and either can be left open, so "everything
        // since March" or "everything up to June" work without the other bound.
        $request->validate([
            'filter.date_from' => ['nullable', 'date_format:Y-m-d'],
            'filter.date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:filter.date_from'],
        ]);

        $invoices = QueryBuilder::for(Invoice::query()->where('workspace_id', $workspace->id))
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(fn ($q) => $q
                        ->where('number', 'like', "%{$value}%")
                        ->orWhere('bill_to_name', 'like', "%{$value}%"));
                }),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('date_from', fn ($query, $value) => $query->whereDate('issue_date', '>=', $value)),
                AllowedFilter::callback('date_to', fn ($query, $value) => $query->whereDate('issue_date', '<=', $value)),
            ])
            ->allowedSorts(['number', 'bill_to_name', 'total', 'issue_date', 'due_date', 'status'])
            // Tie-broken on id so rows can't shuffle between pages when several
            // invoices share an issue date.
            ->defaultSort('-issue_date', '-id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return Inertia::render('workspaces/billing/invoices', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
            'invoices' => $invoices,
            'filters' => [
                'search' => $request->input('filter.search'),
                'status' => $request->input('filter.status'),
                'date_from' => $request->input('filter.date_from'),
                'date_to' => $request->input('filter.date_to'),
                'sort' => $request->input('sort'),
            ],
        ]);
    }

    public function download(Request $request, Workspace $workspace, Invoice $invoice)
    {
        $this->ensureAvailable($request, $workspace);
        $this->authorize(Permission::ViewInvoices->value, $workspace);

        // Route-model binding resolves the invoice by id alone, so without this
        // any member could pull another workspace's invoice by guessing an id.
        abort_unless($invoice->workspace_id === $workspace->id, 404);

        return InvoicePdf::make($invoice)->download(InvoicePdf::filename($invoice));
    }

    /**
     * Invoices are only visible to members of a workspace that has the Billing
     * module switched on. Owners bypass the permission check (they hold '*'),
     * so the module flag has to be enforced here.
     */
    private function ensureAvailable(Request $request, Workspace $workspace): void
    {
        abort_unless($workspace->billing_module_enabled, 404);

        abort_unless(
            $workspace->users()->whereKey($request->user()->getKey())->exists()
                || $workspace->owner_id === $request->user()->getKey(),
            403,
        );
    }
}
