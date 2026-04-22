<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Spatie\QueryBuilder\QueryBuilder;

class RmoManagementExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private QueryBuilder $query) {}

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'Order ID',
            'Tracking Number',
            'J&T Status',
            "Rider's Name",
            "Rider's Number",
            'CX Name',
            'CX Number',
            'Address',
            'SRP',
            '# of Attempts',
            'Confirmed By',
            'CX RTS',
            'Location RTS',
            'Updated Status',
            'CSR',
        ];
    }

    public function map($row): array
    {
        $order = $row->order;
        $address = $order?->shippingAddress;

        return [
            $order?->id,
            $order?->tracking_code,
            $order?->parcel_status,
            $row->rider_name,
            $row->rider_phone,
            $address?->full_name,
            $address?->phone_number,
            $address?->full_address,
            $order?->final_amount,
            $order?->delivery_attempts,
            $row->conferrer?->name,
            $order?->cx_rts_rate,
            $address?->cityOrderSummary?->rts_rate,
            $row->status,
            $row->assignee?->name,
        ];
    }
}