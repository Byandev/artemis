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

        $base = Invoice::query()
            ->where('workspace_id', $workspace->id)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(fn ($q) => $q
                    ->where('number', 'like', "%{$search}%")
                    ->orWhere('bill_to_name', 'like', "%{$search}%"));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')));

        $invoices = QueryBuilder::for($base)
            ->allowedSorts(['number', 'bill_to_name', 'total', 'issue_date', 'due_date', 'status'])
            // Tie-broken on id so rows can't shuffle between pages when several
            // invoices share an issue date.
            ->defaultSort('-issue_date', '-id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return Inertia::render('workspaces/billing/invoices', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
            'invoices' => $invoices,
            'filters' => $request->only(['search', 'status', 'sort']),
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
