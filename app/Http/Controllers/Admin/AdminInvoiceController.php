<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Workspace;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class AdminInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = Invoice::query()
            ->with('workspace:id,name,slug')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('number', 'like', "%{$search}%")
                        ->orWhere('bill_to_name', 'like', "%{$search}%")
                        ->orWhereHas('workspace', fn ($w) => $w->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 15))
            ->withQueryString();

        return Inertia::render('admin/invoices/index', [
            'invoices' => $invoices,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(Request $request)
    {
        $workspaces = Workspace::query()
            ->with(['owner:id,name,email', 'subscription.plan'])
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'owner_id']);

        return Inertia::render('admin/invoices/create', [
            'workspaces' => $workspaces->map(fn (Workspace $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'owner_name' => $w->owner?->name,
                'owner_email' => $w->owner?->email,
                'plan' => $w->subscription?->plan ? [
                    'id' => $w->subscription->plan->id,
                    'name' => $w->subscription->plan->name,
                    'price_php' => $w->subscription->plan->price_php,
                ] : null,
            ]),
            'defaults' => [
                'due_days' => config('invoice.due_days'),
                'tax_rate' => config('invoice.tax_rate'),
                'currency' => config('invoice.currency'),
                'today' => Carbon::now()->toDateString(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'workspace_id' => ['required', 'exists:workspaces,id'],
            'subscription_plan_id' => ['nullable', 'exists:subscription_plans,id'],
            'bill_to_name' => ['required', 'string', 'max:255'],
            'bill_to_email' => ['nullable', 'email', 'max:255'],
            'bill_to_address' => ['nullable', 'string', 'max:2000'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'currency' => ['required', 'string', 'size:3'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.description' => ['required', 'string', 'max:500'],
            'line_items.*.quantity' => ['required', 'numeric', 'min:0'],
            'line_items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,sent,paid'],
        ]);

        $invoice = new Invoice($validated);
        $invoice->number = Invoice::nextNumber((int) Carbon::parse($validated['issue_date'])->format('Y'));
        $invoice->line_items = $validated['line_items'];
        $invoice->recalculate();

        if ($validated['status'] === Invoice::STATUS_PAID) {
            $invoice->paid_at = Carbon::now();
        }

        $invoice->save();

        return redirect()->route('admin.invoices.index')
            ->with('success', "Invoice {$invoice->number} created.");
    }

    public function download(Invoice $invoice)
    {
        $invoice->load('workspace:id,name,slug');

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'seller' => config('invoice.seller'),
            'symbol' => config('invoice.currency_symbol'),
            'paymentInstructions' => config('invoice.payment_instructions'),
        ])->setPaper('a4');

        return $pdf->download("{$invoice->number}.pdf");
    }

    public function updateStatus(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:draft,sent,paid'],
        ]);

        $invoice->status = $validated['status'];
        $invoice->paid_at = $validated['status'] === Invoice::STATUS_PAID
            ? ($invoice->paid_at ?? Carbon::now())
            : null;
        $invoice->save();

        return back()->with('success', "Invoice {$invoice->number} marked as {$validated['status']}.");
    }

    public function destroy(Invoice $invoice)
    {
        $number = $invoice->number;
        $invoice->delete();

        return back()->with('success', "Invoice {$number} deleted.");
    }
}
