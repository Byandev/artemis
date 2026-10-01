<?php

namespace Modules\Pancake\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

/** Search across order number, tracking code, and the shipping address. */
class OrderSearchFilter implements Filter
{
    use JoinsArrayValues;

    public function __invoke(Builder $query, mixed $value, string $property): Builder
    {
        $term = $this->whole($value);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('pancake_orders.order_number', 'like', "%{$term}%")
                ->orWhere('pancake_orders.tracking_code', 'like', "%{$term}%")
                ->orWhereHas('shippingAddress', fn ($sa) => $sa
                    ->where('full_name', 'like', "%{$term}%")
                    ->orWhere('phone_number', 'like', "%{$term}%")
                    ->orWhere('full_address', 'like', "%{$term}%"));
        });
    }
}
