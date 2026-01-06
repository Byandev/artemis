<?php

namespace App\Http\Sorts\AdAccount;

use Illuminate\Database\Eloquent\Builder;

class FacebookAccountsSort implements \Spatie\QueryBuilder\Sorts\Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): Builder
    {
        $direction = $descending ? 'DESC' : 'ASC';

        return $query
            // join pivot
            ->leftJoin('facebook_account_ad_account as faaa', 'faaa.ad_account_id', '=', 'ad_accounts.id')
            // join related table
            ->leftJoin('facebook_accounts as fa', 'fa.id', '=', 'faaa.facebook_account_id')
            // avoid duplicated columns / ensure we still return AdAccount models
            ->select('ad_accounts.*')
            // because belongsToMany can create duplicates
            ->groupBy('ad_accounts.id')
            // pick a deterministic single value per ad_account
            ->orderByRaw("MIN(fa.name) {$direction}");

    }
}
