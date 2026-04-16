<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $paginated = QueryBuilder::for(User::query())
            ->whereHas('workspaces', function ($query) use ($workspace) {
                $query->where('workspace_id', $workspace->id);
            })
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('users.name', 'like', "%{$value}%")
                            ->orWhere('users.email', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::callback('has_pancake_account', function ($query, $value) {
                    if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                        $query->has('pancake_accounts');
                    }
                }),
            ])
            ->allowedIncludes(['pancakeAccounts'])
            ->allowedSorts(['name', 'email', 'joined_at'])
            ->defaultSort('name')
            ->paginate(min((int) $request->input('per_page', 10), 100));

        return response()->json($paginated);
    }
}
