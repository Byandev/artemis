<?php

namespace Modules\GencysERP\Http\Controllers\Web;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AdvertiserPerformanceDailyRecord;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GencysERP\Models\Intern;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class InternDailyRecordController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace): Response
    {
        $this->authorize(Permission::ViewGencysInternDailyRecords->value, $workspace);

        // Reads from the unified advertiser_performance_daily_records table
        // (source=gencys). Join the intern once so we can display/search/sort its
        // name in the same query — no N+1, columns aliased for the frontend
        // (date → record_date, advertiser_id → the intern).
        $table = 'advertiser_performance_daily_records';

        $base = AdvertiserPerformanceDailyRecord::query()
            ->where("{$table}.workspace_id", $workspace->id)
            ->where("{$table}.source", AdvertiserPerformanceDailyRecord::SOURCE_GENCYS)
            ->where("{$table}.advertiser_model", 'intern')
            ->join('gencys_interns', 'gencys_interns.id', '=', "{$table}.advertiser_id")
            ->select([
                "{$table}.*",
                "{$table}.date as record_date",
                'gencys_interns.intern_id as intern_id',
                'gencys_interns.full_name as intern_name',
                'gencys_interns.company_name as intern_company',
                'gencys_interns.username as intern_username',
            ]);

        $records = QueryBuilder::for($base)
            ->allowedFilters([
                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $query->where(function (Builder $q) use ($value) {
                        foreach (['full_name', 'company_name', 'username'] as $column) {
                            $q->orWhere("gencys_interns.{$column}", 'like', "%{$value}%");
                        }
                    });
                }),
                AllowedFilter::callback('gencys_intern_id', function (Builder $q, $value) use ($table) {
                    $ids = array_filter((array) $value, fn ($id) => $id !== '' && $id !== null);
                    if (! empty($ids)) {
                        $q->whereIn("{$table}.advertiser_id", $ids);
                    }
                }),
                AllowedFilter::callback('date_start', fn (Builder $q, $value) => $q->whereDate("{$table}.date", '>=', $value)),
                AllowedFilter::callback('date_end', fn (Builder $q, $value) => $q->whereDate("{$table}.date", '<=', $value)),
            ])
            ->allowedSorts([
                AllowedSort::field('record_date', "{$table}.date"),
                AllowedSort::field('sales', "{$table}.sales"),
                AllowedSort::field('roas', "{$table}.roas"),
                AllowedSort::field('ad_spent', "{$table}.ad_spent"),
                AllowedSort::field('rts_rate', "{$table}.rts_rate"),
                AllowedSort::field('delivered', "{$table}.delivered"),
                AllowedSort::field('delivered_amount', "{$table}.delivered_amount"),
                AllowedSort::field('returned', "{$table}.returned"),
                AllowedSort::field('returned_amount', "{$table}.returned_amount"),
                AllowedSort::field('intern_name', 'gencys_interns.full_name'),
            ])
            ->defaultSort('-record_date')
            ->orderBy("{$table}.id", 'desc')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        // Interns that actually have records, for the intern filter dropdown.
        $interns = Intern::where('gencys_interns.workspace_id', $workspace->id)
            ->whereIn('id', AdvertiserPerformanceDailyRecord::query()
                ->where('workspace_id', $workspace->id)
                ->where('source', AdvertiserPerformanceDailyRecord::SOURCE_GENCYS)
                ->where('advertiser_model', 'intern')
                ->select('advertiser_id'))
            ->orderBy('full_name')
            ->get(['id', 'full_name']);

        return Inertia::render('workspaces/gencys/intern-daily-records/index', [
            'workspace' => $workspace,
            'records' => $records,
            'interns' => $interns,
            'query' => [
                'sort' => $request->input('sort', '-record_date'),
                'perPage' => $request->input('per_page', $request->input('perPage')),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }
}
