<?php

namespace Modules\GencysERP\Http\Controllers\Web;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GencysERP\Models\GencysInternDailyRecord;
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

        // Join the intern once so we can display/search/sort its name in the same
        // query — no N+1, and the join columns are aliased for the frontend.
        $base = GencysInternDailyRecord::query()
            ->where('gencys_intern_daily_records.workspace_id', $workspace->id)
            ->join('gencys_interns', 'gencys_interns.id', '=', 'gencys_intern_daily_records.gencys_intern_id')
            ->select([
                'gencys_intern_daily_records.*',
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
                AllowedFilter::callback('gencys_intern_id', function (Builder $q, $value) {
                    $ids = array_filter((array) $value, fn ($id) => $id !== '' && $id !== null);
                    if (! empty($ids)) {
                        $q->whereIn('gencys_intern_daily_records.gencys_intern_id', $ids);
                    }
                }),
                AllowedFilter::callback('date_start', fn (Builder $q, $value) => $q->whereDate('gencys_intern_daily_records.record_date', '>=', $value)),
                AllowedFilter::callback('date_end', fn (Builder $q, $value) => $q->whereDate('gencys_intern_daily_records.record_date', '<=', $value)),
            ])
            ->allowedSorts([
                AllowedSort::field('record_date', 'gencys_intern_daily_records.record_date'),
                AllowedSort::field('sales', 'gencys_intern_daily_records.sales'),
                AllowedSort::field('roas', 'gencys_intern_daily_records.roas'),
                AllowedSort::field('ad_spent', 'gencys_intern_daily_records.ad_spent'),
                AllowedSort::field('rts_rate', 'gencys_intern_daily_records.rts_rate'),
                AllowedSort::field('rts_amount', 'gencys_intern_daily_records.rts_amount'),
                AllowedSort::field('intern_name', 'gencys_interns.full_name'),
            ])
            ->defaultSort('-record_date')
            ->orderBy('gencys_intern_daily_records.id', 'desc')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        // Interns that actually have records, for the intern filter dropdown.
        $interns = Intern::where('gencys_interns.workspace_id', $workspace->id)
            ->whereIn('id', GencysInternDailyRecord::where('workspace_id', $workspace->id)->select('gencys_intern_id'))
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
