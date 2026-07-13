<?php

namespace Modules\GencysERP\Queries;

use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\GencysERP\Models\GencysInternDailyRecord;
use Modules\GencysERP\Models\Intern;

/**
 * Builds the "Interns Quick Data View (Sales/ROAS)" table.
 *
 * Everything hangs off a single report date — the latest record date in the
 * workspace. Latest-day figures come from that date, "previous day" from the
 * day before, and the two ROAS columns from the two days before the report
 * date (the ROAS for the latest day only finalises the next day). All records
 * are pulled in one query and grouped in memory — no N+1.
 */
class InternDashboardQuery
{
    /** @var array<int, int> */
    private array $internIds;

    public function __construct(
        private readonly Workspace $workspace,
        array $internIds = [],
        private readonly ?string $anchorDate = null,
    ) {
        $this->internIds = array_values(array_filter(
            array_map('intval', $internIds),
            fn (int $id): bool => $id > 0,
        ));
    }

    public function get(): array
    {
        $reportDate = $this->reportDate();

        if (! $reportDate) {
            return [
                'report_date' => null,
                'report_label' => null,
                'roas_a_label' => null,
                'roas_b_label' => null,
                'rows' => [],
                'subtotal' => null,
            ];
        }

        $dLatest = $reportDate->toDateString();
        $dPrev = $reportDate->copy()->subDay()->toDateString();   // previous day / ROAS B
        $dRoasA = $reportDate->copy()->subDays(2)->toDateString(); // ROAS A

        // One query for every cell in the table.
        $records = GencysInternDailyRecord::query()
            ->where('workspace_id', $this->workspace->id)
            ->whereIn('record_date', [$dRoasA, $dPrev, $dLatest])
            ->when($this->internIds, fn ($q) => $q->whereIn('gencys_intern_id', $this->internIds))
            ->get(['gencys_intern_id', 'record_date', 'orders', 'sales', 'ad_spent', 'roas', 'date_to_month_sales']);

        // Index by [intern_id][Y-m-d] for O(1) lookups while building rows.
        $byIntern = [];
        foreach ($records as $r) {
            $byIntern[$r->gencys_intern_id][$r->record_date->toDateString()] = $r;
        }

        $interns = Intern::where('workspace_id', $this->workspace->id)
            ->whereIn('id', array_keys($byIntern))
            ->orderBy('intern_id')
            ->get(['id', 'full_name', 'company_name']);

        // Build one flat row per intern first so ranking is global across groups.
        $rows = $interns->map(function (Intern $intern) use ($byIntern, $dLatest, $dPrev, $dRoasA) {
            $days = $byIntern[$intern->id] ?? [];
            $latest = $days[$dLatest] ?? null;
            $prev = $days[$dPrev] ?? null;
            $roasA = $days[$dRoasA] ?? null;
            $roasB = $prev; // ROAS B is the previous day

            $latestSales = (float) ($latest->sales ?? 0);
            $previousSales = (float) ($prev->sales ?? 0);

            return [
                'id' => $intern->id,
                'name' => $intern->full_name,
                'company' => $intern->company_name ?: 'UNGROUPED',
                'orders' => $latest ? (int) $latest->orders : null,
                'latest_sales' => $latest ? $latestSales : null,
                'previous_sales' => $prev ? $previousSales : null,
                'change' => $latest || $prev ? $latestSales - $previousSales : null,
                'total_to_date' => $latest ? (float) $latest->date_to_month_sales : null,
                'roas_a' => $roasA ? (float) $roasA->roas : null,
                'roas_b' => $roasB ? (float) $roasB->roas : null,
                // Retained for subtotal ROAS (blended = ΣSales ÷ ΣAdSpend).
                '_sales_a' => (float) ($roasA->sales ?? 0),
                '_spend_a' => (float) ($roasA->ad_spent ?? 0),
                '_sales_b' => (float) ($roasB->sales ?? 0),
                '_spend_b' => (float) ($roasB->ad_spent ?? 0),
            ];
        });

        // Global ranking by total sales to-date (1 = highest).
        $ranked = $rows->sortByDesc('total_to_date')->values();
        $rankById = [];
        foreach ($ranked as $i => $row) {
            $rankById[$row['id']] = $i + 1;
        }

        return [
            'report_date' => $reportDate->toDateString(),
            'report_label' => $reportDate->format('F j'),
            'roas_a_label' => $reportDate->copy()->subDays(2)->format('F j'),
            'roas_b_label' => $reportDate->copy()->subDay()->format('F j'),
            'rows' => $rows
                ->map(fn (array $row) => $this->publicRow($row, $rankById[$row['id']]))
                ->values()
                ->all(),
            'subtotal' => $this->subtotal($rows),
        ];
    }

    /** The anchor date drives every column; defaults to the latest record date. */
    private function reportDate(): ?Carbon
    {
        if ($this->anchorDate) {
            return Carbon::parse($this->anchorDate);
        }

        $max = GencysInternDailyRecord::where('workspace_id', $this->workspace->id)
            ->when($this->internIds, fn ($q) => $q->whereIn('gencys_intern_id', $this->internIds))
            ->max('record_date');

        return $max ? Carbon::parse($max) : null;
    }

    /** Strip the internal ΣSales/ΣSpend fields and attach the rank/status. */
    private function publicRow(array $row, int $rank): array
    {
        $change = $row['change'];

        return [
            'id' => $row['id'],
            'name' => $row['name'],
            'orders' => $row['orders'],
            'status' => $change === null ? 'flat' : ($change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat')),
            'latest_sales' => $row['latest_sales'],
            'previous_sales' => $row['previous_sales'],
            'change' => $change,
            'total_to_date' => $row['total_to_date'],
            'rank' => $rank,
            'roas_a' => $row['roas_a'],
            'roas_b' => $row['roas_b'],
        ];
    }

    private function subtotal(Collection $rows): array
    {
        $salesA = $rows->sum('_sales_a');
        $spendA = $rows->sum('_spend_a');
        $salesB = $rows->sum('_sales_b');
        $spendB = $rows->sum('_spend_b');

        return [
            'orders' => (int) $rows->sum('orders'),
            'latest_sales' => (float) $rows->sum('latest_sales'),
            'previous_sales' => (float) $rows->sum('previous_sales'),
            'change' => (float) $rows->sum('change'),
            'total_to_date' => (float) $rows->sum('total_to_date'),
            'roas_a' => $spendA > 0 ? round($salesA / $spendA, 2) : 0.0,
            'roas_b' => $spendB > 0 ? round($salesB / $spendB, 2) : 0.0,
        ];
    }
}
