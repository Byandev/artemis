<?php

namespace Modules\Pancake\Imports;

use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithColumnLimit;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\HeadingRowImport;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Reads a courier billing export and writes its shipping cost onto the pancake
 * orders it names, matching the sheet's waybill number to the order's tracking
 * code.
 *
 * Only two columns matter, and they are found by heading rather than by
 * position — couriers ship several export layouts with the same two labels.
 * Everything else in the sheet is ignored.
 */
class OrderShippingFeesImport implements ToCollection, WithChunkReading, WithColumnLimit, WithHeadingRow
{
    /** Accepted (slugged) headings for the waybill, in order of preference. */
    private const WAYBILL_HEADINGS = [
        'waybill_number', 'waybill_no', 'waybill', 'tracking_code', 'tracking_number',
    ];

    /** Accepted (slugged) headings for the fee. */
    private const FEE_HEADINGS = [
        'total_shipping_cost', 'shipping_cost', 'shipping_fee', 'total_shipping_fee',
    ];

    public int $rowsRead = 0;

    /** Rows whose cost was blank or non-numeric — nothing to write. */
    public int $skipped = 0;

    /** Orders carrying one of the sheet's waybills. */
    public int $matchedOrders = 0;

    /** Orders whose fee actually changed. */
    public int $updated = 0;

    /** Waybills that matched no order in this workspace. */
    public int $unmatched = 0;

    /** @var list<string> a sample of those waybills, for the result message */
    public array $unmatchedSample = [];

    private function __construct(
        private Workspace $workspace,
        private string $waybillHeading,
        private string $feeHeading,
        private string $endColumn,
    ) {}

    /**
     * Read the heading row up front so a sheet missing either column fails
     * before the expensive read, and so everything to the right of the two
     * columns we need can be skipped.
     */
    public static function for(Workspace $workspace, UploadedFile|string $file): self
    {
        $headings = (array) ((new HeadingRowImport)->toArray($file)[0][0] ?? []);

        $waybill = self::position($headings, self::WAYBILL_HEADINGS);
        $fee = self::position($headings, self::FEE_HEADINGS);

        if ($waybill === null || $fee === null) {
            throw new \RuntimeException('The sheet needs a "Waybill Number" column and a "Total Shipping Cost" column in its first row.');
        }

        return new self(
            $workspace,
            $headings[$waybill],
            $headings[$fee],
            Coordinate::stringFromColumnIndex(max($waybill, $fee) + 1),
        );
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function endColumn(): string
    {
        return $this->endColumn;
    }

    public function collection(Collection $rows): void
    {
        $fees = [];

        foreach ($rows as $row) {
            $waybill = $this->str($row->get($this->waybillHeading));

            if ($waybill === null) {
                continue;
            }

            $this->rowsRead++;
            $fee = $this->dec($row->get($this->feeHeading));

            // A row with no cost has nothing to write, and blanking the order's
            // fee would be worse than leaving what is already there.
            if ($fee === null) {
                $this->skipped++;

                continue;
            }

            // Last row wins when the sheet repeats a waybill.
            $fees[$waybill] = $fee;
        }

        if ($fees) {
            $this->apply($fees);
        }
    }

    /**
     * Write one chunk of waybill => fee onto the orders carrying those tracking
     * codes.
     *
     * @param  array<string, float>  $fees
     */
    private function apply(array $fees): void
    {
        $codes = DB::table('pancake_orders')
            ->where('workspace_id', $this->workspace->id)
            ->whereIn('tracking_code', array_keys($fees))
            ->distinct()
            ->pluck('tracking_code')
            ->all();

        foreach (array_diff(array_keys($fees), $codes) as $waybill) {
            $this->unmatched++;

            if (count($this->unmatchedSample) < 20) {
                $this->unmatchedSample[] = $waybill;
            }
        }

        if (! $codes) {
            return;
        }

        $this->matchedOrders += DB::table('pancake_orders')
            ->where('workspace_id', $this->workspace->id)
            ->whereIn('tracking_code', $codes)
            ->count();

        // One UPDATE per chunk: the fee travels in a CASE over the tracking code.
        $cases = '';
        $bindings = [];

        foreach ($codes as $code) {
            $cases .= ' when ? then ?';
            $bindings[] = $code;
            $bindings[] = $fees[$code];
        }

        $placeholders = implode(', ', array_fill(0, count($codes), '?'));

        $this->updated += DB::update(
            "update `pancake_orders` set `shipping_fee` = case `tracking_code`{$cases} end, `updated_at` = ?
             where `workspace_id` = ? and `tracking_code` in ({$placeholders})",
            [...$bindings, now(), $this->workspace->id, ...$codes],
        );
    }

    /**
     * Where the first of the accepted headings sits in the heading row.
     *
     * @param  list<string>  $headings
     * @param  list<string>  $accepted
     */
    private static function position(array $headings, array $accepted): ?int
    {
        foreach ($accepted as $heading) {
            $at = array_search($heading, $headings, true);

            if ($at !== false) {
                return (int) $at;
            }
        }

        return null;
    }

    private function str(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }

        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    private function dec(mixed $v): ?float
    {
        if ($v === null || $v === '' || ! is_numeric($v)) {
            return null;
        }

        return (float) $v;
    }
}
