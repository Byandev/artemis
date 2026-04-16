<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\PancakeUserDailyReport;
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
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return Inertia::render('workspaces/csr/index', [
            'workspace' => $workspace,
            'employees' => $employees,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
            'systemUsers' => User::whereHas('workspaces', fn ($query) => $query->where('workspace_id', $workspace->id))->get()
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

        $type = $request->input('type');

        $query = PancakeUserDailyReport::query()
            ->join('pancake_users as pu', 'pu.id', '=', 'pancake_user_daily_reports.pancake_user_id')
            ->where('pancake_user_daily_reports.workspace_id', $workspace->id)
            ->whereBetween('pancake_user_daily_reports.date', [$from, $to])
            ->when($type, fn ($q) => $q->where('pancake_user_daily_reports.type', $type))
            ->groupBy('pancake_user_daily_reports.pancake_user_id', 'pu.name')
            ->selectRaw('
                pancake_user_daily_reports.pancake_user_id,
                pu.name as csr_name,
                SUM(pancake_user_daily_reports.total_orders) as total_orders,
                SUM(pancake_user_daily_reports.total_sales)  as total_sales,
                SUM(pancake_user_daily_reports.delivered)    as delivered,
                SUM(pancake_user_daily_reports.`returning`)  as returning_count,
                SUM(pancake_user_daily_reports.rmo_called)   as rmo_called,
                CASE
                    WHEN SUM(pancake_user_daily_reports.delivered) + SUM(pancake_user_daily_reports.`returning`) > 0
                    THEN ROUND((SUM(pancake_user_daily_reports.`returning`) / (SUM(pancake_user_daily_reports.delivered) + SUM(pancake_user_daily_reports.`returning`))) * 100, 2)
                    ELSE 0
                END as rts_rate
            ');

        $records = QueryBuilder::for($query)
            ->allowedSorts([
                AllowedSort::field('csr_name'),
                AllowedSort::field('total_orders'),
                AllowedSort::field('total_sales'),
                AllowedSort::field('delivered'),
                AllowedSort::field('returning_count'),
                AllowedSort::field('rmo_called'),
                AllowedSort::field('rts_rate'),
            ])
            ->defaultSort('-total_sales')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return Inertia::render('workspaces/csr/analytics', [
            'workspace' => $workspace,
            'records'   => $records,
            'query'     => $request->only(['sort', 'from', 'to', 'page', 'type']),
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
