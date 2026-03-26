<?php

namespace App\Http\Controllers\API\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Carbon\Carbon;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Pancake\Models\User;

class CsrPerformanceController extends Controller
{
    public function publicIndex(Request $request, Workspace $workspace)
    {
        $validated = $this->validatedFilters($request);
        $validated = $this->normalizePublicFilters($validated);

        return response()->json([
            'data' => $this->buildLeaderboard($workspace, $validated),
        ]);
    }

    private function buildLeaderboard(Workspace $workspace, array $validated): Collection
    {
        $startDate = $validated['start_date'];
        $endDate = $validated['end_date'];
        $startAt = Carbon::parse($startDate)->startOfDay();
        $endAt = Carbon::parse($endDate)->endOfDay();

        $ordersAgg = DB::table('pancake_orders')
            ->selectRaw('confirmed_by, COUNT(*) as total_orders, COALESCE(SUM(final_amount), 0) as total_sales')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('confirmed_at', [$startAt, $endAt])
            ->groupBy('confirmed_by');

        $users = User::query()
            ->select([
                'pancake_users.id',
                'pancake_users.name',
                DB::raw('agg.total_orders'),
                DB::raw('agg.total_sales'),
            ])
            ->joinSub($ordersAgg, 'agg', function ($join) {
                $join->on('agg.confirmed_by', '=', 'pancake_users.id');
            })
            ->orderByDesc('agg.total_sales')
            ->orderByDesc('agg.total_orders')
            ->orderBy('pancake_users.name')
            ->get();

        $rank = 1;
        foreach ($users as $user) {
            $user->csr_id = (string) $user->id;
            $user->rank = $rank++;
            $user->total_orders = (int) ($user->total_orders ?? 0);
            $user->total_sales = (float) ($user->total_sales ?? 0);
            $user->period_start = $startDate;
            $user->period = 'custom';
        }

        return $users->values();
    }

    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);
        if ($start->diffInDays($end) > 31) {
            throw ValidationException::withMessages([
                'end_date' => ['Date range cannot exceed 31 days.'],
            ]);
        }

        return $validated;
    }
}
