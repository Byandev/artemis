<?php

namespace Modules\Pancake\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * Limit to orders delivered by a given rider. Mirrors RtsRiderQuery: the rider
 * is the rider_name on the latest "On Delivery" parcel journey for the order,
 * so this matches exactly the set counted in the RTS "By Rider" breakdown.
 */
class OrderRiderFilter implements Filter
{
    use JoinsArrayValues;

    public function __invoke(Builder $query, mixed $value, string $property): Builder
    {
        $rider = $this->whole($value);

        return $query->whereHas('parcelJourneys', fn ($q) => $q->where('rider_name', $rider));
    }
}
