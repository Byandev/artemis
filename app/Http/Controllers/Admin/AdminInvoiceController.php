<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Workspace;
use App\Support\InvoiceMailer;
use App\Support\InvoicePdf;
use App\Support\SubscriptionInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AdminInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = Invoice::query()
            // `media` is eager loaded so the proof column doesn't fire a query
            // per row on a page of 15 invoices.
            ->with(['workspace:id,name,slug', 'media'])
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
            ->withQueryString()
            ->through(function (Invoice $invoice) {
                $proof = $invoice->proofOfPayment();

                $invoice->setAttribute('proof', $proof ? [
                    'file_name' => $proof->file_name,
                    'mime_type' => $proof->mime_type,
                    'size' => $proof->size,
                    'uploaded_at' => $proof->created_at?->toIso8601String(),
                ] : null);

                // The raw media rows carry disk paths and custom properties the
                // page has no use for, so they don't get shipped to the browser.
                $invoice->unsetRelation('media');

                return $invoice;
            });

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

        // A draft stays put; anything issued goes out with its PDF attached.
        $sentTo = InvoiceMailer::send($invoice);

        return redirect()->route('admin.invoices.index')
            ->with('success', "Invoice {$invoice->number} created.".
                ($sentTo ? " Emailed to {$sentTo}." : ''));
    }

    public function download(Invoice $invoice)
    {
        return response()->streamDownload(
            fn () => print (InvoicePdf::render($invoice)),
            InvoicePdf::filename($invoice),
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function updateStatus(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:draft,sent,paid'],

            // Marking an invoice paid is the moment the receipt is in hand, so
            // it can be filed in the same request rather than a second step
            // that is easy to forget.
            'proof' => ['nullable', ...$this->proofRules()],
        ]);

        // Moving a draft to "sent" is the act of sending it, so that is when
        // the email goes. Marking one paid is bookkeeping — nothing goes out.
        $isBeingSent = $validated['status'] === Invoice::STATUS_SENT
            && $invoice->status === Invoice::STATUS_DRAFT;

        $invoice->status = $validated['status'];
        $invoice->paid_at = $validated['status'] === Invoice::STATUS_PAID
            ? ($invoice->paid_at ?? Carbon::now())
            : null;
        $invoice->save();

        $message = "Invoice {$invoice->number} marked as {$validated['status']}.";

        if ($isBeingSent) {
            $sentTo = InvoiceMailer::send($invoice);
            $message .= $sentTo
                ? " Emailed to {$sentTo}."
                : ' Emailing it failed — check the logs.';
        }

        // Paying a renewal is what moves the subscription on to its next
        // period — and restores access, if the old one had already run out.
        if ($validated['status'] === Invoice::STATUS_PAID
            && $subscription = SubscriptionInvoice::settle($invoice)) {
            $message .= sprintf(
                ' %s now runs to %s.',
                $invoice->workspace?->name ?? 'The subscription',
                $subscription->current_period_end->format('M j, Y')
            );
        }


        if ($request->hasFile('proof')) {
            $this->attachProof($invoice);
            $message .= ' Proof of payment attached.';
        }

        return back()->with('success', $message);
    }

    /**
     * Attach (or replace) the receipt evidencing this invoice was settled.
     */
    public function storeProof(Request $request, Invoice $invoice)
    {
        $request->validate([
            'proof' => ['required', ...$this->proofRules()],
        ]);

        $replacing = (bool) $invoice->proofOfPayment();

        $this->attachProof($invoice);

        return back()->with('success', $replacing
            ? "Proof of payment for {$invoice->number} replaced."
            : "Proof of payment attached to {$invoice->number}.");
    }

    /**
     * Serve the receipt. The bucket is private, so this either hands out a
     * short-lived signed URL or streams the bytes when the disk cannot sign
     * one (a local disk in development) — the file is never publicly readable.
     */
    public function showProof(Invoice $invoice)
    {
        $media = $invoice->proofOfPayment();

        abort_unless($media, 404, 'No proof of payment on file for this invoice.');

        $disk = Storage::disk($media->disk);

        if ($disk->providesTemporaryUrls()) {
            return redirect()->away($disk->temporaryUrl(
                $media->getPathRelativeToRoot(),
                Carbon::now()->addMinutes(5),
            ));
        }

        return $disk->download($media->getPathRelativeToRoot(), $media->file_name);
    }

    public function destroyProof(Invoice $invoice)
    {
        abort_unless($invoice->proofOfPayment(), 404, 'No proof of payment on file for this invoice.');

        $invoice->clearMediaCollection(Invoice::PROOF_COLLECTION);

        return back()->with('success', "Proof of payment for {$invoice->number} removed.");
    }

    /**
     * Shared between the standalone upload and the mark-as-paid shortcut, so
     * the two can't drift on what they will accept.
     *
     * @return array<int, string>
     */
    private function proofRules(): array
    {
        // `heif` alongside `heic` because iOS photos are routinely detected as
        // the former. No minimum size: `mimes` already rejects a truncated
        // upload, which sniffs as application/x-empty rather than an image.
        return ['file', 'mimes:jpg,jpeg,png,webp,heic,heif,pdf', 'max:10240'];
    }

    private function attachProof(Invoice $invoice): void
    {
        // The collection is singleFile(), so this replaces any existing receipt
        // and deletes the old object from the bucket.
        $invoice->addMediaFromRequest('proof')
            ->toMediaCollection(Invoice::PROOF_COLLECTION);
    }

    public function destroy(Invoice $invoice)
    {
        $number = $invoice->number;
        $invoice->delete();

        return back()->with('success', "Invoice {$number} deleted.");
    }
}
