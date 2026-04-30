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
        'csr' => 'CSR',
    ];

    private array $columns;

    public function __construct(private QueryBuilder $query, array $columns = [])
    {
        $this->columns = ! empty($columns)
            ? array_intersect($columns, array_keys(self::AVAILABLE_COLUMNS))
            : array_keys(self::AVAILABLE_COLUMNS);
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
            'csr' => $row->assignee?->name,
        ];

        return array_map(fn ($key) => $allValues[$key] ?? null, $this->columns);
    }
}
