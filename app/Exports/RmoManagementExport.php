<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Spatie\QueryBuilder\QueryBuilder;

class RmoManagementExport implements FromQuery, WithHeadings, WithMapping
{
    public const AVAILABLE_COLUMNS = [
        'order_id' => 'Order ID',
        'tracking_number' => 'Tracking Number',
        'jnt_status' => 'J&T Status',
        'rider_name' => "Rider's Name",
        'rider_number' => "Rider's Number",
        'cx_name' => 'CX Name',
        'cx_number' => 'CX Number',
        'address' => 'Address',
        'srp' => 'SRP',
        'attempts' => '# of Attempts',
        'confirmed_by' => 'Confirmed By',
        'cx_rts' => 'CX RTS',
        'location_rts' => 'Location RTS',
        'updated_status' => 'Updated Status',
        'cx_status' => 'CX Status',
        'rider_status' => 'Rider Status',
        'csr' => 'CSR',
        'upsell_date' => 'Upsell Date',
        'upsell_price' => 'Upsell',
        'order_details' => 'Order Details',
    ];

    /**
     * Left out unless asked for by name: only the public RMO page picks these,
     * and the other exports shouldn't grow two blank columns.
     */
    private const OPT_IN_COLUMNS = ['cx_status', 'rider_status'];

    private array $columns;

    public function __construct(
        private QueryBuilder $query,
        array $columns = [],
        private bool $preferPancakeAssignee = false
    ) {
        $this->columns = ! empty($columns)
            ? array_intersect($columns, array_keys(self::AVAILABLE_COLUMNS))
            : array_values(array_diff(array_keys(self::AVAILABLE_COLUMNS), self::OPT_IN_COLUMNS));
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return array_map(fn ($key) => self::AVAILABLE_COLUMNS[$key], $this->columns);
    }

    public function map($row): array
    {
        $order = $row->order;
        $address = $order?->shippingAddress;

        $allValues = [
            'order_id' => $order?->id,
            'tracking_number' => $order?->tracking_code,
            'jnt_status' => $order?->parcel_status,
            'rider_name' => $row->rider_name,
            'rider_number' => $row->rider_phone,
            'cx_name' => $address?->full_name,
            'cx_number' => $row->customer_phone ?: $address?->phone_number,
            'address' => $address?->full_address,
            'srp' => $order?->final_amount,
            'attempts' => $order?->delivery_attempts,
            'confirmed_by' => $row->conferrer?->name,
            'cx_rts' => $order?->cx_rts_rate,
            'location_rts' => $address?->cityOrderSummary?->rts_rate,
            'updated_status' => $row->status,
            'cx_status' => in_array('cx_status', $this->columns, true) ? $row->cxStatus?->name : null,
            'rider_status' => in_array('rider_status', $this->columns, true) ? $row->riderStatus?->name : null,
            'csr' => $this->preferPancakeAssignee
                ? ($row->pancakeAssignee?->name ?? $row->assignee?->name)
                : ($row->assignee?->name ?? $row->pancakeAssignee?->name),
            'upsell_date' => $row->upsell_date,
            'upsell_price' => $row->upsell_price,
            'order_details' => $row->order_details,
        ];

        return array_map(fn ($key) => $allValues[$key] ?? null, $this->columns);
    }
}
