<?php

namespace App\Exports;

use App\Models\CallLog;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\Pancake\Models\OrderForDelivery;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * One row per call placed against the orders in the RMO list — the bulk version
 * of the per-order call-log modal.
 *
 * The call-log relations on OrderForDelivery match on columns of the parent row
 * (CSR, workspace, delivery date), which makes them usable as correlated
 * subqueries for the on-screen counts but not as eager loads. So the orders are
 * walked in chunks and each chunk's logs are fetched in a single extra query,
 * keeping this to two queries per chunk regardless of how many calls there are.
 */
class RmoCallLogsExport implements FromCollection, WithHeadings
{
    /** Orders per round trip. Each chunk costs one order query + one log query. */
    private const CHUNK_SIZE = 500;

    public function __construct(
        private QueryBuilder $query,
        private int $workspaceId,
        private string $deliveryDate,
    ) {}

    public function headings(): array
    {
        return [
            'Call Date',
            'Call Time',
            'Contact',
            'Phone Number',
            'Type',
            'Duration (s)',
            'CSR',
            'Order Number',
            'Tracking Number',
            'Customer Name',
            'Rider Name',
            'Confirmed By',
            'Updated Status',
            'Parcel Status',
        ];
    }

    public function collection(): Collection
    {
        $rows = collect();

        // chunk() pages with offsets, so it needs a stable order to avoid
        // skipping or repeating rows between pages.
        $this->query
            ->orderBy('pancake_order_for_delivery.id')
            ->chunk(self::CHUNK_SIZE, function (Collection $orders) use ($rows) {
                $logs = $this->logsFor($orders);

                foreach ($orders as $order) {
                    foreach ($this->callsFor($logs, $order, $order->customer_phone) as $log) {
                        $rows->push($this->row($log, $order, 'Customer'));
                    }

                    foreach ($this->callsFor($logs, $order, $order->rider_phone) as $log) {
                        $rows->push($this->row($log, $order, 'Rider'));
                    }
                }
            });

        return $rows;
    }

    /**
     * Every call this chunk of orders could possibly match, keyed by
     * "csr|phone" — the pair that ties a call to an order.
     *
     * @param  Collection<int, OrderForDelivery>  $orders
     * @return Collection<string, Collection<int, CallLog>>
     */
    private function logsFor(Collection $orders): Collection
    {
        $csrIds = $orders->pluck('assignee_id')->filter()->unique()->values();

        $phones = $orders->pluck('customer_phone')
            ->merge($orders->pluck('rider_phone'))
            ->filter()
            ->unique()
            ->values();

        if ($csrIds->isEmpty() || $phones->isEmpty()) {
            return collect();
        }

        return CallLog::query()
            ->where('workspace_id', $this->workspaceId)
            ->whereDate('call_date', $this->deliveryDate)
            ->whereIn('user_id', $csrIds)
            ->whereIn('phone_number', $phones)
            ->orderBy('call_time')
            ->get(['id', 'user_id', 'phone_number', 'type', 'duration', 'call_date', 'call_time'])
            ->groupBy(fn (CallLog $log) => $log->user_id.'|'.$log->phone_number);
    }

    /**
     * @param  Collection<string, Collection<int, CallLog>>  $logs
     * @return Collection<int, CallLog>
     */
    private function callsFor(Collection $logs, $order, ?string $phone): Collection
    {
        if (blank($phone) || blank($order->assignee_id)) {
            return collect();
        }

        return $logs->get($order->assignee_id.'|'.$phone, collect());
    }

    private function row(CallLog $log, $order, string $contact): array
    {
        return [
            $log->call_date?->toDateString(),
            $log->call_time,
            $contact,
            $log->phone_number,
            $log->type,
            (int) $log->duration,
            $order->assignee?->name,
            $order->order?->order_number,
            $order->order?->tracking_code,
            $order->customer_name,
            $order->rider_name,
            $order->conferrer?->name,
            $order->status,
            $order->parcel_status,
        ];
    }
}
