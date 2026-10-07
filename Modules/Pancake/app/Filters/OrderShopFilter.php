<?php

namespace Modules\Pancake\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * One Pancake shop, or several. Spatie has already split a comma-separated
 * list by the time it arrives here, and shop ids never contain a comma.
 */
class OrderShopFilter implements Filter
{
    public function __invoke(Builder $query, mixed $value, string $property): Builder
    {
        return $query->whereIn('pancake_orders.shop_id', (array) $value);
    }
}
