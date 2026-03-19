<?php

namespace App\Http\Controllers\API\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Pancake\Models\User;

class CsrPerformanceController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $validated = $this->validatedFilters($request);

        return response()->json([
            'data' => $this->buildLeaderboard($workspace, $validated),
        ]);
    }

    public function publicIndex(Request $request, Workspace $workspace)
    {
        $validated = $this->validatedFilters($request);

        return response()->json([
            'data' => $this->buildLeaderboard($workspace, $validated),
        ]);
    }

    public function publicCsrIndex(Workspace $workspace)
    {
        $data = User::query()
            ->selectRaw('id as csr_id, name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $data,
        ]);
    }

    private function buildLeaderboard(Workspace $workspace, array $validated): Collection
    {
        $startDate = $validated['start_date'];
        $endDate = $validated['end_date'];

        $users = User::query()
            ->select(['id', 'name'])
            ->withCount([
                'orders as total_orders' => function ($query) use ($workspace, $startDate, $endDate) {
                    $query->where('workspace_id', $workspace->id)
                        ->whereDate('confirmed_at', '>=', $startDate)
                        ->whereDate('confirmed_at', '<=', $endDate);
                },
            ])
            ->withSum([
                'orders as total_sales' => function ($query) use ($workspace, $startDate, $endDate) {
                    $query->where('workspace_id', $workspace->id)
                        ->whereDate('confirmed_at', '>=', $startDate)
                        ->whereDate('confirmed_at', '<=', $endDate);
                },
            ], 'final_amount')
            ->orderByDesc('total_sales')
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
        return $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);
    }
}