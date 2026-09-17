<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The CSR breakdown table as a spreadsheet — one row per CSR, for the range and
 * filters the page was showing, with the paging taken off.
 *
 * The keys are the table's column ids, which are also the sort keys the
 * controller takes, so the picker on the page can name the columns it wants
 * without a second vocabulary in between. Money is written as a plain number
 * and call time as whole seconds, both so the sheet can be summed; the screen's
 * peso and h:mm:ss formatting stays on the screen, and the seconds columns say
 * so in their heading.
 */
class CsrAnalyticsExport implements FromQuery, WithHeadings, WithMapping
{
    /** Every exportable column, in the order the table lays them out. */
    public const AVAILABLE_COLUMNS = [
        'name' => 'CSR',

        'total_orders' => 'Orders',
        'total_sales' => 'Sales',
        'total_delivered' => 'Delivered',
        'total_delivered_count' => 'Delivered Parcels',
        'total_returning' => 'Returning',
        'total_returning_count' => 'Returning Parcels',
        'rts_rate' => 'RTS Rate (%)',

        'total_confirmed' => 'RMO Confirmed',
        'total_called' => 'RMO Assigned',
        'total_rmo_call_attempts' => 'RMO Called',
        'total_rmo_orders' => 'RMO Orders Called',
        'rmo_percentage' => 'RMO %',
        'total_call_time' => 'RMO Call Time (s)',
        'total_rmo_connected_called' => 'RMO Answered',
        'total_rmo_real_called' => 'RMO Real Conversations',
        'longest_rmo_call_time' => 'Longest RMO Call (s)',
        'total_rmo_customer_called' => 'RMO Customer Called',
        'total_rmo_customer_call_time' => 'RMO Customer Call Time (s)',
        'total_rmo_rider_called' => 'RMO Rider Called',
        'total_rmo_rider_call_time' => 'RMO Rider Call Time (s)',
        'total_verification_called' => 'Verification Called',
        'total_verification_call_time' => 'Verification Call Time (s)',
        'total_verification_real_called' => 'Verification Real Conversations',
        'total_verified_orders' => 'Total Verified Orders',
        'total_all_called' => 'Total Called',
        'total_all_call_time' => 'Total Call Time (s)',
    ];

    /** The columns written as decimals rather than counts. */
    private const DECIMAL_COLUMNS = [
        'total_sales',
        'total_delivered',
        'total_returning',
        'rts_rate',
        'rmo_percentage',
    ];

    /** @var list<string> */
    private array $columns;

    /**
     * @param  array<int, string>  $columns  Which columns to write, by table column id.
     *                                       Anything unknown is dropped; nothing usable
     *                                       left means the whole table.
     */
    public function __construct(private QueryBuilder $query, array $columns = [])
    {
        $picked = array_values(array_intersect(
            array_keys(self::AVAILABLE_COLUMNS),
            $columns,
        ));

        // The CSR's name is what every other figure is attributed to, so it is
        // written whether or not it was asked for — a sheet of unlabelled
        // numbers is not a report.
        if ($picked !== [] && ! in_array('name', $picked, true)) {
            array_unshift($picked, 'name');
        }

        $this->columns = $picked !== [] ? $picked : array_keys(self::AVAILABLE_COLUMNS);
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
        return array_map(function (string $key) use ($row) {
            if ($key === 'name') {
                // A CSR whose name has not synced across still has figures
                // against them; the pancake user id is at least something to
                // match the row back to.
                return $row->name ?: $row->id;
            }

            return in_array($key, self::DECIMAL_COLUMNS, true)
                ? (float) $row->{$key}
                : (int) $row->{$key};
        }, $this->columns);
    }
}
