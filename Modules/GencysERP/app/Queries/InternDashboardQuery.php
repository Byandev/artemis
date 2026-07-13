<?php

namespace Modules\GencysERP\Queries;

use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\GencysERP\Models\GencysInternDailyRecord;
use Modules\GencysERP\Models\Intern;

/**
 * Builds the "Interns Quick Data View (Sales/ROAS)" table for a single date.
 *
 * Per intern the day's orders / sales / ad_spent are shown, with ROAS
 * (sales ÷ ad_spent) and a change vs the previous day's sales. Interns are
 * ranked globally by sales. Only active interns appear.
 */
class InternDashboardQuery
{
    /** @var array<int, int> */
    private array $internIds;

    public function __construct(
        private readonly Workspace $workspace,
        array $internIds = [],
        private readonly ?string $date = null,
    ) {
        $this->internIds = array_values(array_filter(
            array_map('intval', $internIds),
            fn (int $id): bool => $id > 0,
        ));
    }

    public function get(): array
    {
        $date = $this->resolveDate();

        if (! $date) {
            return [
                'date' => null,
                'date_label' => null,
                'prev_date' => null,
                'prev_date_label' => null,
                'rows' => [],
                'subtotal' => null,
            ];
        }

        $prevDate = $date->copy()->subDay();

        // At most one record per intern per date (unique key), so index by intern.
        // "today" = the selected date, "yesterday" = the day before.
        $today = $this->recordsFor($date->toDateString())->keyBy('gencys_intern_id');
        $yesterday = $this->recordsFor($prevDate->toDateString())->keyBy('gencys_intern_id');

        // Month-to-date sales per intern: summed daily sales from the 1st of the
        // selected date's month through that date. (The ERP's own
        // date_to_month_sales snapshot isn't populated, so we derive it.)
        $monthToDate = $this->monthToDateSales($date);

        $interns = Intern::where('workspace_id', $this->workspace->id)
            ->where('active', true)
            ->whereIn('id', $today->keys()->all())
            ->orderBy('intern_id')
            ->get(['id', 'full_name', 'company_name']);

        $rows = $interns->map(function (Intern $intern) use ($today, $yesterday, $monthToDate) {
            $t = $today->get($intern->id);
            $y = $yesterday->get($intern->id);

            $tSales = (float) ($t->sales ?? 0);
            $ySales = (float) ($y->sales ?? 0);
            $tSpend = (float) ($t->ad_spent ?? 0);
            $ySpend = (float) ($y->ad_spent ?? 0);
            $change = $tSales - $ySales;

            return [
                'id' => $intern->id,
                'name' => $intern->full_name,
                'orders' => (int) ($t->orders ?? 0),
                'sales' => $tSales,
                'yesterday_sales' => $ySales,
                'change' => round($change, 2),
                // Up/down remark: today's sales vs yesterday's.
                'status' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'),
                // Month-to-date sales as of the selected date (running total).
                'month_sales' => (float) ($monthToDate[$intern->id] ?? 0),
                'roas_yesterday' => $ySpend > 0 ? round($ySales / $ySpend, 2) : null,
                'roas_today' => $tSpend > 0 ? round($tSales / $tSpend, 2) : null,
                // Internal — kept for blended subtotal ROAS, stripped below.
                '_t_spend' => $tSpend,
                '_y_spend' => $ySpend,
            ];
        });

        // Rank by month-to-date sales (1 = highest) and return rows in that order.
        $ranked = $rows->sortByDesc('month_sales')->values();

        return [
            'date' => $date->toDateString(),
            'date_label' => $date->format('M j'),
            'prev_date' => $prevDate->toDateString(),
            'prev_date_label' => $prevDate->format('M j'),
            'rows' => $ranked
                ->map(function (array $row, int $i) {
                    $row['rank'] = $i + 1;
                    unset($row['_t_spend'], $row['_y_spend']);

                    return $row;
                })
                ->values()
                ->all(),
            'subtotal' => $this->subtotal($rows),
        ];
    }

    /** Daily records for the given date, scoped to workspace + selected interns. */
    private function recordsFor(string $date): Collection
    {
        return GencysInternDailyRecord::query()
            ->where('workspace_id', $this->workspace->id)
            ->where('record_date', $date)
            ->when($this->internIds, fn ($q) => $q->whereIn('gencys_intern_id', $this->internIds))
            ->get(['gencys_intern_id', 'orders', 'sales', 'ad_spent']);
    }

    /**
     * intern id => summed daily sales from the 1st of $date's month through
     * $date (month-to-date), scoped to workspace + selected interns.
     */
    private function monthToDateSales(Carbon $date): Collection
    {
        return GencysInternDailyRecord::query()
            ->where('workspace_id', $this->workspace->id)
            ->whereBetween('record_date', [$date->copy()->startOfMonth()->toDateString(), $date->toDateString()])
            ->when($this->internIds, fn ($q) => $q->whereIn('gencys_intern_id', $this->internIds))
            ->groupBy('gencys_intern_id')
            ->selectRaw('gencys_intern_id, SUM(COALESCE(sales, 0)) as mtd_sales')
            ->pluck('mtd_sales', 'gencys_intern_id');
    }

    /** The report date; defaults to the latest record date in the workspace. */
    private function resolveDate(): ?Carbon
    {
        if ($this->date) {
            return Carbon::parse($this->date);
        }

        $latest = GencysInternDailyRecord::where('workspace_id', $this->workspace->id)
            ->when($this->internIds, fn ($q) => $q->whereIn('gencys_intern_id', $this->internIds))
            ->max('record_date');

        return $latest ? Carbon::parse($latest) : null;
    }

    private function subtotal(Collection $rows): array
    {
        $tSales = (float) $rows->sum('sales');
        $ySales = (float) $rows->sum('yesterday_sales');
        $tSpend = (float) $rows->sum('_t_spend');
        $ySpend = (float) $rows->sum('_y_spend');
        $change = $tSales - $ySales;

        return [
            'orders' => (int) $rows->sum('orders'),
            'sales' => $tSales,
            'yesterday_sales' => $ySales,
            'change' => round($change, 2),
            'status' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'),
            'month_sales' => (float) $rows->sum('month_sales'),
            'roas_yesterday' => $ySpend > 0 ? round($ySales / $ySpend, 2) : null,
            'roas_today' => $tSpend > 0 ? round($tSales / $tSpend, 2) : null,
        ];
    }
}
