<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Every figure the CSR comparison panel can plot.
 *
 * One catalogue, read by three places that would otherwise drift apart: the
 * comparison endpoint builds the scan for the metric it was asked for out of
 * it, the analytics page validates the `?comparison=` key against it, and the
 * dropdown on the panel is populated from the labels it ships.
 *
 * Every column of `pancake_user_pos_daily_reports` and
 * `pancake_user_daily_call_reports` that carries a figure is here, plus the two
 * rates that cannot be summed — `rts_rate` and RMO called % are stored (or
 * derived) per day, and a mean of daily rates is not the period's rate, so both
 * are recomputed from the amounts underneath them.
 *
 * Keys are the column names. The four the panel used to tab between kept their
 * old keys as aliases, so links and bookmarks made before the dropdown still
 * open on the metric they named.
 */
class CsrComparisonMetrics
{
    /** The metric the panel opens on. */
    public const DEFAULT_KEY = 'total_sales';

    /** The tables behind the two groups, keyed by the `source` of a metric. */
    private const TABLES = [
        'pos' => 'pancake_user_pos_daily_reports',
        'call' => 'pancake_user_daily_call_reports',
    ];

    /** The four tab keys the panel shipped before every column was selectable. */
    private const ALIASES = [
        'sales' => 'total_sales',
        'rts' => 'rts_rate',
        'rmo_called' => 'rmo_called_rate',
        'call_time' => 'total_rmo_call_time',
    ];

    /**
     * A metric is either a column — aggregated over the period with `aggregate`
     * — or a `rate`, a percentage of one column sum over the sum of others.
     *
     * `column` and `aggregate` are interpolated into SQL, so they stay literals
     * here and are never taken from the request.
     */
    private const METRICS = [
        // pancake_user_pos_daily_reports
        [
            'key' => 'total_sales',
            'label' => 'Total sales',
            'group' => 'Orders',
            'source' => 'pos',
            'column' => 'total_sales',
            'aggregate' => 'SUM',
            'format' => 'currency',
            'higher_is_better' => true,
        ],
        [
            'key' => 'total_orders',
            'label' => 'Total orders',
            'group' => 'Orders',
            'source' => 'pos',
            'column' => 'total_orders',
            'aggregate' => 'SUM',
            'format' => 'number',
            'higher_is_better' => true,
        ],
        [
            'key' => 'delivered',
            'label' => 'Delivered amount',
            'group' => 'Orders',
            'source' => 'pos',
            'column' => 'delivered',
            'aggregate' => 'SUM',
            'format' => 'currency',
            'higher_is_better' => true,
        ],
        [
            'key' => 'delivered_count',
            'label' => 'Delivered parcels',
            'group' => 'Orders',
            'source' => 'pos',
            'column' => 'delivered_count',
            'aggregate' => 'SUM',
            'format' => 'number',
            'higher_is_better' => true,
        ],
        [
            'key' => 'returning',
            'label' => 'Returned amount',
            'group' => 'Orders',
            'source' => 'pos',
            'column' => 'returning',
            'aggregate' => 'SUM',
            'format' => 'currency',
            'higher_is_better' => false,
        ],
        [
            'key' => 'returning_count',
            'label' => 'Returned parcels',
            'group' => 'Orders',
            'source' => 'pos',
            'column' => 'returning_count',
            'aggregate' => 'SUM',
            'format' => 'number',
            'higher_is_better' => false,
        ],
        [
            // Money back over money settled, as the RTS card and leader read it
            // — not a mean of the stored daily `rts_rate`.
            'key' => 'rts_rate',
            'label' => 'RTS rate',
            'group' => 'Orders',
            'source' => 'pos',
            'rate' => ['part' => 'returning', 'whole' => ['returning', 'delivered']],
            'format' => 'percent',
            'higher_is_better' => false,
        ],

        // pancake_user_daily_call_reports
        [
            'key' => 'total_called',
            'label' => 'Calls placed',
            'group' => 'Calls',
            'source' => 'call',
            'column' => 'total_called',
            'aggregate' => 'SUM',
            'format' => 'number',
            'higher_is_better' => true,
        ],
        [
            'key' => 'total_call_time',
            'label' => 'Call time',
            'group' => 'Calls',
            'source' => 'call',
            'column' => 'total_call_time',
            'aggregate' => 'SUM',
            'format' => 'duration',
            'higher_is_better' => true,
        ],
        [
            'key' => 'total_rmo_called',
            'label' => 'RMO calls placed',
            'group' => 'Calls',
            'source' => 'call',
            'column' => 'total_rmo_called',
            'aggregate' => 'SUM',
            'format' => 'number',
            'higher_is_better' => true,
        ],
        [
            // The same work as "RMO calls placed" counted by delivery rather
            // than by call: a parcel rung three times is three there and one
            // here.
            'key' => 'total_rmo_orders',
            'label' => 'RMO orders called',
            'group' => 'Calls',
            'source' => 'call',
            'column' => 'total_rmo_orders',
            'aggregate' => 'SUM',
            'format' => 'number',
            'higher_is_better' => true,
        ],
        [
            'key' => 'total_rmo_call_time',
            'label' => 'RMO call time',
            'group' => 'Calls',
            'source' => 'call',
            'column' => 'total_rmo_call_time',
            'aggregate' => 'SUM',
            'format' => 'duration',
            'higher_is_better' => true,
        ],
        [
            'key' => 'total_rmo_connected_called',
            'label' => 'RMO calls connected',
            'group' => 'Calls',
            'source' => 'call',
            'column' => 'total_rmo_connected_called',
            'aggregate' => 'SUM',
            'format' => 'number',
            'higher_is_better' => true,
        ],
        [
            'key' => 'total_rmo_real_called',
            'label' => 'RMO real calls',
            'group' => 'Calls',
            'source' => 'call',
            'column' => 'total_rmo_real_called',
            'aggregate' => 'SUM',
            'format' => 'number',
            'higher_is_better' => true,
        ],
        [
            // A max, so a range takes the max of the daily maxes rather than
            // adding several CSRs' longest calls together.
            'key' => 'longest_rmo_call_time',
            'label' => 'Longest RMO call',
            'group' => 'Calls',
            'source' => 'call',
            'column' => 'longest_rmo_call_time',
            'aggregate' => 'MAX',
            'format' => 'duration',
            'higher_is_better' => true,
        ],
        [
            'key' => 'total_rmo_customer_called',
            'label' => 'RMO customer calls',
            'group' => 'Calls',
            'source' => 'call',
            'column' => 'total_rmo_customer_called',
            'aggregate' => 'SUM',
            'format' => 'number',
            'higher_is_better' => true,
        ],
        [
            'key' => 'total_rmo_customer_call_time',
            'label' => 'RMO customer call time',
            'group' => 'Calls',
            'source' => 'call',
            'column' => 'total_rmo_customer_call_time',
            'aggregate' => 'SUM',
            'format' => 'duration',
            'higher_is_better' => true,
        ],
        [
            'key' => 'total_rmo_rider_called',
            'label' => 'RMO rider calls',
            'group' => 'Calls',
            'source' => 'call',
            'column' => 'total_rmo_rider_called',
            'aggregate' => 'SUM',
            'format' => 'number',
            'higher_is_better' => true,
        ],
        [
            'key' => 'total_rmo_rider_call_time',
            'label' => 'RMO rider call time',
            'group' => 'Calls',
            'source' => 'call',
            'column' => 'total_rmo_rider_call_time',
            'aggregate' => 'SUM',
            'format' => 'duration',
            'higher_is_better' => true,
        ],
        [
            'key' => 'total_rmo_assigned_count',
            'label' => 'RMO assigned',
            'group' => 'Calls',
            'source' => 'call',
            'column' => 'total_rmo_assigned_count',
            'aggregate' => 'SUM',
            'format' => 'number',
            'higher_is_better' => true,
        ],
        [
            'key' => 'total_rmo_confirmed_count',
            'label' => 'RMO confirmed',
            'group' => 'Calls',
            'source' => 'call',
            'column' => 'total_rmo_confirmed_count',
            'aggregate' => 'SUM',
            'format' => 'number',
            'higher_is_better' => true,
        ],
        [
            // RMO assigned over RMO confirmed, the same arithmetic as the CSR
            // table's RMO % column and the leader card above the panel.
            'key' => 'rmo_called_rate',
            'label' => 'RMO called %',
            'group' => 'Calls',
            'source' => 'call',
            'rate' => ['part' => 'total_rmo_assigned_count', 'whole' => ['total_rmo_confirmed_count']],
            'format' => 'percent',
            'higher_is_better' => true,
        ],
        [
            'key' => 'total_verification_called',
            'label' => 'Verification calls',
            'group' => 'Calls',
            'source' => 'call',
            'column' => 'total_verification_called',
            'aggregate' => 'SUM',
            'format' => 'number',
            'higher_is_better' => true,
        ],
        [
            'key' => 'total_verification_call_time',
            'label' => 'Verification call time',
            'group' => 'Calls',
            'source' => 'call',
            'column' => 'total_verification_call_time',
            'aggregate' => 'SUM',
            'format' => 'duration',
            'higher_is_better' => true,
        ],
        [
            'key' => 'total_verification_real_called',
            'label' => 'Verification real calls',
            'group' => 'Calls',
            'source' => 'call',
            'column' => 'total_verification_real_called',
            'aggregate' => 'SUM',
            'format' => 'number',
            'higher_is_better' => true,
        ],
        [
            // The same work as "Verification calls" counted by order rather
            // than by call: an order rung three times is three there and one
            // here.
            'key' => 'total_verified_orders',
            'label' => 'Total verified orders',
            'group' => 'Calls',
            'source' => 'call',
            'column' => 'total_verified_orders',
            'aggregate' => 'SUM',
            'format' => 'number',
            'higher_is_better' => true,
        ],
    ];

    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        return array_map(fn (array $metric) => [
            ...$metric,
            // Percentage points for the metrics that are already rates, as on
            // the stat cards above the panel.
            'delta_unit' => $metric['format'] === 'percent' ? ' pts' : '%',
        ], self::METRICS);
    }

    /**
     * What the dropdown lists: the key, its label and the group it sits under.
     * Shipped with the page so the selector is populated before the figures
     * land, rather than filling in once the request comes back.
     *
     * @return array<int, array<string, string>>
     */
    public static function options(): array
    {
        return array_map(fn (array $metric) => [
            'key' => $metric['key'],
            'label' => $metric['label'],
            'group' => $metric['group'],
        ], self::METRICS);
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_column(self::METRICS, 'key');
    }

    public static function table(string $source): string
    {
        return self::TABLES[$source];
    }

    /**
     * One metric by key. Callers resolve the key first, so an unknown one here
     * is a programming error rather than a bad request.
     *
     * @return array<string, mixed>
     */
    public static function find(string $key): array
    {
        foreach (self::all() as $metric) {
            if ($metric['key'] === $key) {
                return $metric;
            }
        }

        throw new InvalidArgumentException("Unknown CSR comparison metric [{$key}].");
    }

    /**
     * The columns a metric's scan has to aggregate, as `column => aggregate` —
     * the one column it plots, or the ones its rate divides. Only the metric
     * being drawn is scanned, so the dropdown's length costs nothing.
     *
     * @param  array<string, mixed>  $metric
     * @return array<string, string>
     */
    public static function columnsFor(array $metric): array
    {
        if (isset($metric['column'])) {
            return [$metric['column'] => $metric['aggregate']];
        }

        $columns = [];

        foreach ([$metric['rate']['part'], ...$metric['rate']['whole']] as $column) {
            $columns[$column] ??= 'SUM';
        }

        return $columns;
    }

    /**
     * The metric a request asked for: the key itself, what an old tab key now
     * points at, or the default when it names nothing.
     */
    public static function resolveKey(?string $key): string
    {
        $key = self::ALIASES[$key] ?? $key;

        return in_array($key, self::keys(), true) ? $key : self::DEFAULT_KEY;
    }
}
