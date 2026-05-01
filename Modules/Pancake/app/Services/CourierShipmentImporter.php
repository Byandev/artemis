<?php

namespace Modules\Pancake\Services;

use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Pancake\Models\CourierShipment;
use Modules\Pancake\Models\Order;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CourierShipmentImporter
{
    private const BATCH = 500;

    /**
     * @return array{rows_read:int, upserted:int, matched:int, unmatched:int}
     */
    public function importJt(Workspace $workspace, UploadedFile $file): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();

        $rowsRead = 0;
        $upserted = 0;
        $waybills = [];
        $batch = [];
        $now = now();

        for ($r = 2; $r <= $highestRow; $r++) {
            $waybill = $this->str($sheet->getCell("J{$r}")->getValue());
            if ($waybill === null) {
                continue;
            }

            $rowsRead++;
            $waybills[] = $waybill;

            $batch[] = [
                'workspace_id' => $workspace->id,
                'courier' => 'jt',
                'waybill_no' => $waybill,
                'creator_code' => $this->str($sheet->getCell("A{$r}")->getValue()),
                'order_status' => $this->str($sheet->getCell("B{$r}")->getValue()),
                'order_number' => $this->str($sheet->getCell("C{$r}")->getValue()),
                'receiver' => $this->str($sheet->getCell("D{$r}")->getValue()),
                'receiver_cellphone' => $this->str($sheet->getCell("E{$r}")->getValue()),
                'cod' => $this->dec($sheet->getCell("F{$r}")->getValue()),
                'pouches_size' => $this->str($sheet->getCell("G{$r}")->getValue()),
                'print_number' => $this->int($sheet->getCell("H{$r}")->getValue()),
                'preferred_pickup_date' => $this->date($sheet->getCell("I{$r}")->getValue()),
                'shipping_customer' => $this->str($sheet->getCell("K{$r}")->getValue()),
                'sender_name' => $this->str($sheet->getCell("L{$r}")->getValue()),
                'sender_cellphone' => $this->str($sheet->getCell("M{$r}")->getValue()),
                'sender_province' => $this->str($sheet->getCell("N{$r}")->getValue()),
                'sender_city' => $this->str($sheet->getCell("O{$r}")->getValue()),
                'sender_address' => $this->str($sheet->getCell("P{$r}")->getValue()),
                'item_name' => $this->str($sheet->getCell("Q{$r}")->getValue()),
                'item_weight' => $this->dec($sheet->getCell("R{$r}")->getValue()),
                'number_of_items' => $this->int($sheet->getCell("S{$r}")->getValue()),
                'cod_fee' => $this->dec($sheet->getCell("U{$r}")->getValue()),
                'receivable_freight' => $this->dec($sheet->getCell("V{$r}")->getValue()),
                'total_shipping_cost' => $this->dec($sheet->getCell("W{$r}")->getValue()),
                'item_value' => $this->dec($sheet->getCell("X{$r}")->getValue()),
                'valuation_fee' => $this->dec($sheet->getCell("Y{$r}")->getValue()),
                'rts_reason' => $this->str($sheet->getCell("Z{$r}")->getValue()),
                'pancake_order_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= self::BATCH) {
                $this->flush($batch);
                $upserted += count($batch);
                $batch = [];
            }
        }

        if ($batch) {
            $this->flush($batch);
            $upserted += count($batch);
        }

        $matched = $this->linkPancakeOrders($workspace, 'jt', array_unique($waybills));
        $unmatched = max(0, count(array_unique($waybills)) - $matched);

        return [
            'rows_read' => $rowsRead,
            'upserted' => $upserted,
            'matched' => $matched,
            'unmatched' => $unmatched,
        ];
    }

    private function flush(array $batch): void
    {
        $columns = array_keys($batch[0]);
        $update = array_values(array_diff($columns, ['workspace_id', 'courier', 'waybill_no', 'created_at', 'pancake_order_id']));

        CourierShipment::upsert($batch, ['workspace_id', 'courier', 'waybill_no'], $update);
    }

    private function linkPancakeOrders(Workspace $workspace, string $courier, array $waybills): int
    {
        if (! $waybills) {
            return 0;
        }

        $totalLinked = 0;

        foreach (array_chunk($waybills, 1000) as $chunk) {
            $map = Order::where('workspace_id', $workspace->id)
                ->whereIn('tracking_code', $chunk)
                ->pluck('id', 'tracking_code')
                ->all();

            foreach ($map as $tc => $orderId) {
                $totalLinked += DB::table('courier_shipments')
                    ->where('workspace_id', $workspace->id)
                    ->where('courier', $courier)
                    ->where('waybill_no', $tc)
                    ->update(['pancake_order_id' => $orderId, 'updated_at' => now()]);
            }
        }

        return $totalLinked;
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
        if ($v === null || $v === '') {
            return null;
        }
        if (! is_numeric($v)) {
            return null;
        }

        return (float) $v;
    }

    private function int(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (! is_numeric($v)) {
            return null;
        }

        return (int) $v;
    }

    private function date(mixed $v): ?string
    {
        $s = $this->str($v);
        if ($s === null) {
            return null;
        }
        try {
            return CarbonImmutable::parse($s)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
