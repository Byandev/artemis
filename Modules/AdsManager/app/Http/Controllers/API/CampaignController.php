<?php

namespace Modules\AdsManager\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Modules\AdsManager\Models\Campaign;
use Spatie\QueryBuilder\QueryBuilder;

class CampaignController extends Controller
{
    public function index()
    {
        return QueryBuilder::for(Campaign::class)
            ->allowedIncludes(['adAccount.facebookAccounts'])
            ->paginate();
    }
}
