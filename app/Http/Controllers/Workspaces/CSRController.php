<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\PancakeUserDailyReport;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $type = $request->input('type');

        $engagementSub = DB::table('pancake_user_daily_engagements')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('date', [$from, $to])
            ->groupBy('pancake_user_id')
            ->selectRaw('
                pancake_user_id,
                SUM(total_engagement) as s_total_eng,
                SUM(customer_engagement_new_inbox) as s_new_eng,
                SUM(order_count) as s_eng_orders,
                SUM(old_order_count) as s_old_orders
            ');

        $rmoCallSub = DB::table('pancake_order_for_delivery')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('delivery_date', [$from, $to])
            ->groupBy('conferrer_id')
            ->selectRaw('
                conferrer_id,
                SUM(customer_call_attempts) as s_customer_attempts,
                SUM(customer_call_duration) as s_customer_duration,
                SUM(rider_call_attempts) as s_rider_attempts,
                SUM(rider_call_duration) as s_rider_duration
            ');

        $query = PancakeUserDailyReport::query()
            ->join('pancake_users as pu', 'pu.id', '=', 'pancake_user_daily_reports.pancake_user_id')
            ->leftJoinSub($engagementSub, 'eng', 'eng.pancake_user_id', '=', 'pu.id')
            ->leftJoinSub($rmoCallSub, 'ofd_sum', 'ofd_sum.conferrer_id', '=', 'pu.id')
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
                COALESCE(MAX(eng.s_total_eng), 0) as overall_engagement,
                COALESCE(MAX(eng.s_new_eng), 0) as new_cx_engagement,
                COALESCE(MAX(eng.s_total_eng), 0) - COALESCE(MAX(eng.s_new_eng), 0) as old_cx_engagement,
                COALESCE(MAX(eng.s_eng_orders), 0) as overall_orders,
                COALESCE(MAX(eng.s_old_orders), 0) as old_cx_orders,
                COALESCE(MAX(eng.s_eng_orders), 0) - COALESCE(MAX(eng.s_old_orders), 0) as new_cx_orders,
                CASE
                    WHEN COALESCE(MAX(eng.s_new_eng), 0) > 0
                    THEN ROUND(((COALESCE(MAX(eng.s_eng_orders), 0) - COALESCE(MAX(eng.s_old_orders), 0)) / MAX(eng.s_new_eng)) * 100, 2)
                    ELSE 0
                END as new_cx_conversion_rate,
                CASE
                    WHEN (COALESCE(MAX(eng.s_total_eng), 0) - COALESCE(MAX(eng.s_new_eng), 0)) > 0
                    THEN ROUND((COALESCE(MAX(eng.s_old_orders), 0) / (MAX(eng.s_total_eng) - MAX(eng.s_new_eng))) * 100, 2)
                    ELSE 0
                END as old_cx_conversion_rate,
                CASE
                    WHEN COALESCE(MAX(eng.s_total_eng), 0) > 0
                    THEN ROUND((COALESCE(MAX(eng.s_eng_orders), 0) / MAX(eng.s_total_eng)) * 100, 2)
                    ELSE 0
                END as overall_conversion_rate,
                COALESCE(MAX(ofd_sum.s_customer_attempts), 0) + COALESCE(MAX(ofd_sum.s_rider_attempts), 0) as rmo_total_attempts,
                COALESCE(MAX(ofd_sum.s_customer_duration), 0) + COALESCE(MAX(ofd_sum.s_rider_duration), 0) as total_call_time,
                (
                    SELECT COUNT(*)
                    FROM pancake_order_for_delivery ofd
                    WHERE ofd.conferrer_id = pu.id
                      AND ofd.workspace_id = ?
                      AND ofd.delivery_date BETWEEN ? AND ?
                ) as rmo_total_for_delivery,
                CASE
                    WHEN (
                        SELECT COUNT(*)
                        FROM pancake_order_for_delivery ofd2
                        WHERE ofd2.conferrer_id = pu.id
                          AND ofd2.workspace_id = ?
                          AND ofd2.delivery_date BETWEEN ? AND ?
                    ) > 0
                    THEN ROUND((SUM(pancake_user_daily_reports.rmo_called) / (
                        SELECT COUNT(*)
                        FROM pancake_order_for_delivery ofd3
                        WHERE ofd3.conferrer_id = pu.id
                          AND ofd3.workspace_id = ?
                          AND ofd3.delivery_date BETWEEN ? AND ?
                    )) * 100, 2)
                    ELSE 0
                END as rmo_productivity,
                CASE
                    WHEN SUM(pancake_user_daily_reports.delivered) + SUM(pancake_user_daily_reports.`returning`) > 0
                    THEN ROUND((SUM(pancake_user_daily_reports.`returning`) / (SUM(pancake_user_daily_reports.delivered) + SUM(pancake_user_daily_reports.`returning`))) * 100, 2)
                    ELSE 0
                END as rts_rate
            ', [
                $workspace->id, $from, $to,
                $workspace->id, $from, $to,
                $workspace->id, $from, $to,
            ]);

        $records = QueryBuilder::for($query)
            ->allowedSorts([
                AllowedSort::field('csr_name'),
                AllowedSort::field('total_orders'),
                AllowedSort::field('total_sales'),
                AllowedSort::field('delivered'),
                AllowedSort::field('returning_count'),
                AllowedSort::field('rmo_called'),
                AllowedSort::field('rmo_total_for_delivery'),
                AllowedSort::field('rmo_productivity'),
                AllowedSort::field('rts_rate'),
                AllowedSort::field('overall_engagement'),
                AllowedSort::field('new_cx_engagement'),
                AllowedSort::field('old_cx_engagement'),
                AllowedSort::field('overall_orders'),
                AllowedSort::field('new_cx_orders'),
                AllowedSort::field('old_cx_orders'),
                AllowedSort::field('new_cx_conversion_rate'),
                AllowedSort::field('old_cx_conversion_rate'),
                AllowedSort::field('overall_conversion_rate'),
                AllowedSort::field('rmo_total_attempts'),
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
