<?php

namespace App\Http\Controllers\API\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\Workspace;
use App\Support\TeamScope;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ShopController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $allowedOwners = TeamScope::allowedOwnerIds($request->user(), $workspace);

        return QueryBuilder::for(Shop::class)
            ->where('workspace_id', $workspace->id)
            ->when($allowedOwners !== null, fn ($q) => $q->whereIn('id', function ($sub) use ($workspace, $allowedOwners) {
                $sub->from('pages')
                    ->select('shop_id')
                    ->where('workspace_id', $workspace->id)
                    ->whereNotNull('shop_id')
                    ->whereIn('owner_id', $allowedOwners);
            }))
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
            ])
            ->allowedSorts(['name', 'id'])
            ->paginate();
    }
}
