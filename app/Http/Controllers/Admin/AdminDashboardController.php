<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class AdminDashboardController extends Controller
{
    public function index()
    {
        // Deferred so the page shell renders immediately and the cards show a
        // skeleton while the aggregates load.
        return Inertia::render('admin/dashboard/index', [
            'stats' => Inertia::defer(fn () => $this->stats()),
        ]);
    }

    /**
     * A client is a workspace. It counts as active only on a paid 'active'
     * subscription whose period hasn't ended; everything else (trialing,
     * lapsed, or no subscription) is inactive.
     */
    private function stats(): array
    {
        $now = Carbon::now();

        $totalClients = Workspace::count();

        $activeClients = Workspace::whereHas('subscription', function (Builder $query) use ($now) {
            $query->where('status', Subscription::STATUS_ACTIVE)
                ->where(fn (Builder $q) => $q->whereNull('current_period_end')->orWhere('current_period_end', '>=', $now));
        })->count();

        $paidInvoices = Invoice::where('status', Invoice::STATUS_PAID);

        return [
            'active_clients' => $activeClients,
            'inactive_clients' => $totalClients - $activeClients,
            'paid_invoices_count' => (clone $paidInvoices)->count(),
            'paid_invoices_total' => (float) (clone $paidInvoices)->sum('total'),
            'monthly_revenue' => (float) (clone $paidInvoices)
                ->whereBetween('paid_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
                ->sum('total'),
        ];
    }
}
