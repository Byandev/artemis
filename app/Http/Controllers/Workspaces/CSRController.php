<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\PancakeUserErpDailyReport;
use App\Models\PancakeUserPosDailyReport;
use App\Models\PancakeUserRmoDailyReport;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Pancake\Models\User as PancakeUser;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class CSRController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $employees = QueryBuilder::for(PancakeUser::class)
            ->with('systemUser')
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('pancake_users.name', 'like', "%{$value}%")
                            ->orWhere('pancake_users.email', 'like', "%{$value}%")
                            ->orWhere('pancake_users.phone_number', 'like', "%{$value}%")
                            ->orWhere('pancake_users.status', 'like', "%{$value}%");
                    })->orWhereHas('systemUser', function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts(['name', 'email', 'phone_number', 'created_at', 'status', 'user_name'])
            ->defaultSort('pancake_users.name')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('workspaces/csr/index', [
            'workspace' => $workspace,
            'employees' => $employees,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
            'systemUsers' => User::whereHas('workspaces', fn ($query) => $query->where('workspace_id', $workspace->id))->get(),
        ]);
    }

    public function analytics(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $from = $request->input('from')
            ? CarbonImmutable::parse($request->input('from'))->toDateString()
            : CarbonImmutable::now()->subDays(6)->toDateString();

        $to = $request->input('to')
            ? CarbonImmutable::parse($request->input('to'))->toDateString()
            : CarbonImmutable::now()->toDateString();

        $type = strtolower((string) $request->input('type', 'pos'));
        $drClass = $type === 'erp' ? PancakeUserErpDailyReport::class : PancakeUserPosDailyReport::class;

        $drSub = fn () => $drClass::query()
            ->forWorkspaceRange($workspace->id, $from, $to)
            ->whereColumn('pancake_user_id', 'pancake_users.id');

        $rmoSub = fn () => PancakeUserRmoDailyReport::query()
            ->forWorkspaceRange($workspace->id, $from, $to)
            ->whereColumn('pancake_user_id', 'pancake_users.id');

        $query = PancakeUser::query()
            ->select([
                'pancake_users.id as pancake_user_id',
                'pancake_users.name as csr_name',
            ])
            ->selectSub($drSub()->selectRaw('COALESCE(SUM(total_orders), 0)'), 'total_orders')
            ->selectSub($drSub()->selectRaw('COALESCE(SUM(total_sales), 0)'), 'total_sales')
            ->selectSub($drSub()->selectRaw('COALESCE(SUM(delivered), 0)'), 'delivered')
            ->selectSub($drSub()->selectRaw('COALESCE(SUM(`returning`), 0)'), 'returning_count')
            ->selectSub($rmoSub()->selectRaw('COALESCE(SUM(total_called), 0)'), 'total_called')
            ->selectSub($rmoSub()->selectRaw('COALESCE(SUM(total_call_time), 0)'), 'total_call_time')
            ->selectSub(
                $drSub()->selectRaw('
                    CASE
                        WHEN COALESCE(SUM(delivered), 0) + COALESCE(SUM(`returning`), 0) > 0
                        THEN ROUND((COALESCE(SUM(`returning`), 0) / (COALESCE(SUM(delivered), 0) + COALESCE(SUM(`returning`), 0))) * 100, 2)
                        ELSE 0
                    END
                '),
                'rts_rate'
            );

        $records = QueryBuilder::for($query)
            ->allowedSorts([
                AllowedSort::field('csr_name'),
                AllowedSort::field('total_orders'),
                AllowedSort::field('total_sales'),
                AllowedSort::field('delivered'),
                AllowedSort::field('returning_count'),
                AllowedSort::field('rts_rate'),
                AllowedSort::field('total_called'),
                AllowedSort::field('total_call_time'),
            ])
            ->defaultSort('-total_sales')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('workspaces/csr/analytics', [
            'workspace' => $workspace,
            'records' => $records,
            'query' => $request->only(['sort', 'from', 'to', 'page', 'type']),
        ]);
    }

    public function update(Request $request, Workspace $workspace, PancakeUser $employee)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:ACTIVE,INACTIVE',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $employee->update($validated);

        return redirect()->back()->with('success', 'Employee updated successfully');
    }
}
