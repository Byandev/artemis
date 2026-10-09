<?php

namespace Modules\Pancake\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\Workspace;
use App\Support\TeamVisibility;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Modules\Pancake\Models\AddressAutofill;

/**
 * Pancake → Address Auto-fill: how the AI order address auto-fill is doing —
 * outcomes, fill rate, what it costs, and the orders behind the numbers.
 */
class AddressAutofillController extends Controller
{
    use AuthorizesRequests;

    /** Outcomes the chart stacks, in the order it stacks them. */
    public const CHART_OUTCOMES = ['filled', 'needs_review', 'no_address', 'failed'];

    public function index(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $this->authorize(Permission::ViewOrders->value, $workspace);

        $shops = Shop::where('workspace_id', $workspace->id)
            ->when(
                TeamVisibility::shouldScope($request->user(), $workspace),
                fn ($q) => $q->visibleTo($request->user(), $workspace),
            )
            ->orderBy('name')
            ->get(['id', 'name', 'auto_fill_address']);

        $from = $this->date($request->input('filter.date_from'), now()->subDays(6))->startOfDay();
        $to = $this->date($request->input('filter.date_to'), now())->endOfDay();
        $shopId = $request->input('filter.shop_id');
        $status = $request->input('filter.status');

        $base = fn () => AddressAutofill::query()
            ->whereIn('shop_id', $shopId && $shops->contains('id', (int) $shopId) ? [(int) $shopId] : $shops->pluck('id'))
            ->whereBetween('created_at', [$from, $to]);

        $counts = $base()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($n) => (int) $n);

        $usage = $base()->selectRaw('SUM(ai_cost_usd) as cost, COUNT(ai_cost_usd) as ai_checks, SUM(input_tokens) as input_tokens, SUM(output_tokens) as output_tokens')->first();

        $rows = $base()
            ->with('shop:id,name')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate((int) $request->input('per_page', 25))
            ->withQueryString()
            ->through(fn (AddressAutofill $a) => [
                'id' => $a->id,
                'shop' => $a->shop?->name,
                'pancake_order_id' => $a->pancake_order_id,
                'status' => $a->status,
                'reason' => $a->reason,
                'attempts' => $a->attempts,
                'address_text' => data_get($a->result, 'address_text'),
                'formatted_address' => data_get($a->result, 'formatted_address'),
                'match_status' => data_get($a->result, 'status'),
                'picked_by_ai' => data_get($a->result, 'picked_by_ai', []),
                'ai_cost_usd' => $a->ai_cost_usd,
                'created_at' => $a->created_at?->toIso8601String(),
            ]);

        return Inertia::render('workspaces/pancake/address-autofill/index', [
            'workspace' => $workspace,
            'shops' => $shops,
            'summary' => $this->summary($counts, $usage),
            'daily' => $this->daily($base, $from, $to),
            'review' => $this->reviewBreakdown($base),
            'rows' => $rows,
            'filters' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'shop_id' => $shopId ? (string) $shopId : null,
                'status' => $status,
            ],
            'dryRun' => (bool) config('pancake.auto_fill_address.dry_run', true),
        ]);
    }

    private function date(?string $value, Carbon $fallback): Carbon
    {
        try {
            return $value ? Carbon::parse($value) : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function summary($counts, $usage): array
    {
        $filled = ($counts[AddressAutofill::UPDATED] ?? 0) + ($counts[AddressAutofill::DRY_RUN] ?? 0);

        // Orders the AI had a real go at. Skipped (not eligible) and queued
        // (not read yet) say nothing about how well it reads.
        $decided = $filled
            + ($counts[AddressAutofill::NEEDS_REVIEW] ?? 0)
            + ($counts[AddressAutofill::NO_ADDRESS] ?? 0)
            + ($counts[AddressAutofill::FAILED] ?? 0);

        $withAddress = $filled + ($counts[AddressAutofill::NEEDS_REVIEW] ?? 0);
        $cost = (float) ($usage->cost ?? 0);

        return [
            'total' => (int) $counts->sum(),
            'counts' => [
                'updated' => $counts[AddressAutofill::UPDATED] ?? 0,
                'dry_run' => $counts[AddressAutofill::DRY_RUN] ?? 0,
                'needs_review' => $counts[AddressAutofill::NEEDS_REVIEW] ?? 0,
                'no_address' => $counts[AddressAutofill::NO_ADDRESS] ?? 0,
                'failed' => $counts[AddressAutofill::FAILED] ?? 0,
                'skipped' => $counts[AddressAutofill::SKIPPED] ?? 0,
                'queued' => $counts[AddressAutofill::QUEUED] ?? 0,
            ],
            'filled' => $filled,
            // Of the orders read, how many it filled in.
            'fill_rate' => $decided ? $filled / $decided : null,
            // Of the orders where the customer did give an address, how many
            // it could fill — the reading/matching quality on its own.
            'match_rate' => $withAddress ? $filled / $withAddress : null,
            'cost_usd' => $cost,
            'ai_checks' => (int) ($usage->ai_checks ?? 0),
            'avg_cost_usd' => ($usage->ai_checks ?? 0) ? $cost / $usage->ai_checks : null,
            'cost_per_fill_usd' => $filled ? $cost / $filled : null,
            'input_tokens' => (int) ($usage->input_tokens ?? 0),
            'output_tokens' => (int) ($usage->output_tokens ?? 0),
        ];
    }

    /**
     * One entry per day in the range — days with nothing included, so the
     * chart's axis does not skip.
     */
    private function daily(\Closure $base, Carbon $from, Carbon $to): array
    {
        $byDay = $base()
            ->selectRaw('DATE(created_at) as day, status, COUNT(*) as total, SUM(ai_cost_usd) as cost')
            ->groupBy('day', 'status')
            ->get()
            ->groupBy('day');

        return collect(CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()))
            ->map(function (Carbon $day) use ($byDay) {
                $rows = $byDay->get($day->toDateString(), collect());
                $count = fn (string ...$statuses) => (int) $rows->whereIn('status', $statuses)->sum('total');

                return [
                    'date' => $day->toDateString(),
                    'filled' => $count(AddressAutofill::UPDATED, AddressAutofill::DRY_RUN),
                    'needs_review' => $count(AddressAutofill::NEEDS_REVIEW),
                    'no_address' => $count(AddressAutofill::NO_ADDRESS),
                    'failed' => $count(AddressAutofill::FAILED),
                    'skipped' => $count(AddressAutofill::SKIPPED),
                    'cost_usd' => round((float) $rows->sum('cost'), 6),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Why orders land in needs_review, and which level is the one that fails
     * to match — where accuracy work would pay off. Also how often the
     * shortlist check rescued a level.
     */
    private function reviewBreakdown(\Closure $base): array
    {
        $reasons = $base()
            ->where('status', AddressAutofill::NEEDS_REVIEW)
            ->selectRaw('reason, COUNT(*) as total')
            ->groupBy('reason')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(fn ($r) => ['reason' => $r->reason ?? '—', 'total' => (int) $r->total])
            ->all();

        $unmatched = ['province' => 0, 'district' => 0, 'commune' => 0];
        $picked = ['province' => 0, 'district' => 0, 'commune' => 0];
        $rescued = 0;

        foreach ($base()->whereNotNull('result')->select('id', 'status', 'result')->lazyById(500) as $row) {
            foreach ((array) data_get($row->result, 'picked_by_ai', []) as $level) {
                $picked[$level] = ($picked[$level] ?? 0) + 1;
            }

            if (in_array($row->status, [AddressAutofill::UPDATED, AddressAutofill::DRY_RUN], true) && data_get($row->result, 'picked_by_ai')) {
                $rescued++;
            }

            if ($row->status === AddressAutofill::NEEDS_REVIEW && data_get($row->result, 'status') !== 'complete') {
                foreach (array_keys($unmatched) as $level) {
                    if (data_get($row->result, "{$level}.id") === null) {
                        $unmatched[$level]++;
                    }
                }
            }
        }

        return [
            'reasons' => $reasons,
            'unmatched' => $unmatched,
            'picked' => $picked,
            // Filled orders that only got there thanks to the shortlist check.
            'rescued_by_shortlist' => $rescued,
        ];
    }
}
