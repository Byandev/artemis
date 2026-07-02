<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $users = QueryBuilder::for(User::class)
            ->select('id', 'name', 'email', 'is_super_admin', 'email_verified_at', 'created_at')
            ->with(['workspaces:id,name,slug', 'ownedWorkspaces:id,name,slug,owner_id'])
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%")
                            ->orWhere('email', 'like', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts(['id', 'name', 'email', 'created_at'])
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString()
            ->through(function (User $user) {
                $workspaces = $user->ownedWorkspaces
                    ->merge($user->workspaces)
                    ->unique('id')
                    ->map(fn ($workspace) => [
                        'id' => $workspace->id,
                        'name' => $workspace->name,
                        'slug' => $workspace->slug,
                        'is_owner' => (int) $workspace->owner_id === (int) $user->id,
                    ])
                    ->values();

                unset($user->ownedWorkspaces, $user->workspaces);
                $user->setAttribute('workspaces', $workspaces);

                return $user;
            });

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => $request->only(['search']),
            'query' => $request->only(['sort', 'per_page', 'page']),
        ]);
    }

    public function generatePasswordReset(Request $request, User $user)
    {
        $token = Password::createToken($user);
        $url = route('password.reset', ['token' => $token]).'?'.http_build_query(['email' => $user->email]);

        return response()->json(['url' => $url]);
    }
}
