<?php

namespace Modules\GencysERP\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\GencysERP\Models\GencysInternDailyRecord;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\GencysERP\Models\Intern;

class InternDailyRecordController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->input('data');

        if (! is_array($data)) {
            $payload = $request->all();
            $first = $payload[0] ?? null;
            $data = is_array($first)
                ? ($first['data'] ?? $first)
                : $payload;
        }

        $workspaceId = $data['workspace_id'] ?? null;
        $rawKey = $data['api_key'] ?? null;

        $apiKey = $rawKey ? WorkspaceApiKey::findByRawKey($rawKey) : null;

        if (! $apiKey || ($workspaceId && (int) $apiKey->workspace_id !== (int) $workspaceId)) {
            return response()->json([
                'message' => 'Invalid api_key for workspace.',
            ], 401);
        }

        $apiKey->update([
            'last_used_at' => now(),
        ]);

        $workspace = $apiKey->workspace;

        $parentInternId = $this->intOrNull(
            $data['intern_id']
            ?? $data['internId']
            ?? null
        );

        if (! $parentInternId) {
            return response()->json([
                'message' => 'intern_id is required.',
            ], 422);
        }

        $intern = Intern::where('workspace_id', $workspace->id)
            ->where('intern_id', $parentInternId)
            ->first();

        if (! $intern) {
            return response()->json([
                'message' => "Intern {$parentInternId} not found.",
            ], 422);
        }

        $gencysInternId = $intern->id;
        
        $records = collect(['records', 'rows'])
            ->map(fn ($key) => $data[$key] ?? null)
            ->first(fn ($rows) => is_array($rows) && ! empty($rows))
            ?? [$data];

        $saved = 0;
        $skipped = 0;
        $rowsPerRun = [];

        foreach ($records as $record) {

            GencysInternDailyRecord::updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'gencys_intern_id' => $gencysInternId,
                    'record_date' => $this->date(
                            $record['date']
                            ?? $record['record_date']
                            ?? null
                        ) ?? now()->toDateString(),
                ],
                [
                    'orders' => $this->countOrNull($record['orders'] ?? null),

                    'sales' => $this->decimalOrNull(
                        $record['total_sales']
                        ?? $record['sales']
                        ?? null
                    ),

                    'roas' => $this->decimalOrNull(
                        $record['roas'] ?? null
                    ),

                    'ad_spent' => $this->decimalOrNull(
                        $record['ads_spent']
                        ?? $record['ad_spent']
                        ?? $record['adSpent']
                        ?? null
                    ),

                    'rts_rate' => $this->decimalOrNull(
                        $record['rts_rate']
                        ?? $record['rtsRate']
                        ?? null
                    ),

                    'rts_amount' => $this->decimalOrNull(
                        $record['rts_amount']
                        ?? $record['rtsAmount']
                        ?? null
                    ),

                    ...$this->monthToDate($record),
                ]
            );

            $saved++;

            if ($runId = $this->intOrNull(
                $record['sync_run_id']
                ?? $record['syncRunId']
                ?? null
            )) {
                $rowsPerRun[$runId] = ($rowsPerRun[$runId] ?? 0) + 1;
            }
        }

        foreach ($rowsPerRun as $runId => $count) {
            GencysSyncRun::succeedById(
                $workspace->id,
                $runId,
                $count,
                $count
            );
        }

        return response()->json([
            'saved' => $saved,
            'skipped' => $skipped,
            'intern_id' => $parentInternId,
            'gencys_intern_id' => $gencysInternId,
        ]);
    }
    /**
     * The month-to-date block: four headline totals plus an RTS breakdown per
     * stage. Every field is optional — the ERP omits them on some payloads.
     *
     * @return array<string, float|int|null>
     */
    private function monthToDate(array $record): array
    {
        $values = [
            'date_to_month_sales' => $this->decimalOrNull($record['date_to_month_sales'] ?? null),
            'date_to_month_orders' => $this->countOrNull($record['date_to_month_orders'] ?? null),
            'date_to_month_ad_spent' => $this->decimalOrNull($record['date_to_month_ad_spent'] ?? null),
            'date_to_month_roas' => $this->decimalOrNull($record['date_to_month_roas'] ?? null),
        ];

        foreach (['sales_order', 'parcel_status', 'shipped_out'] as $stage) {
            $prefix = "date_to_month_{$stage}";

            $values["{$prefix}_rts_rate"] = $this->decimalOrNull($record["{$prefix}_rts_rate"] ?? null);
            $values["{$prefix}_delivered"] = $this->countOrNull($record["{$prefix}_delivered"] ?? null);
            $values["{$prefix}_returned"] = $this->countOrNull($record["{$prefix}_returned"] ?? null);
            $values["{$prefix}_for_return"] = $this->countOrNull($record["{$prefix}_for_return"] ?? null);
        }

        return $values;
    }

    private function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        foreach (['m/d/Y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->toDateString();
            } catch (\Throwable) {
                // Try the next known format.
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Parse "1,888.00" → 1888.00, "" → null (strips thousands separators). */
    private function decimalOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = is_string($value) ? preg_replace('/[^0-9.\-]/', '', $value) : $value;

        return ($clean === '' || $clean === null) ? null : (float) $clean;
    }

    private function intOrNull(mixed $value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }

    /**
     * Parse a formatted count: "382.00" → 382, "1,888" → 1888, "" → null. The ERP
     * sends counts as decimal strings, so a plain (int) cast would truncate at the
     * first separator ((int) "1,888" === 1).
     */
    private function countOrNull(mixed $value): ?int
    {
        $parsed = $this->decimalOrNull($value);

        return $parsed === null ? null : (int) $parsed;
    }
}
