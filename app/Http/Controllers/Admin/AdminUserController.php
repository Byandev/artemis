<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
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

    /**
     * Grant or revoke global Super Admin.
     *
     * The route already sits behind the `admin` middleware, so only a Super
     * Admin gets this far. The two guards below are about not stranding the
     * install: nobody may demote themselves, and the last Super Admin standing
     * may not be demoted at all — either would leave /admin unreachable with no
     * way back in short of a database edit.
     */
    public function updateSuperAdmin(Request $request, User $user)
    {
        $validated = $request->validate([
            'is_super_admin' => ['required', 'boolean'],
        ]);

        $granting = $validated['is_super_admin'];

        // Refused as validation errors rather than flashes so the page can tell
        // a refusal from a success — an Inertia redirect looks identical either
        // way from the caller's side.
        if (! $granting && $request->user()->is($user)) {
            throw ValidationException::withMessages([
                'is_super_admin' => 'You cannot remove your own Super Admin access.',
            ]);
        }

        if (! $granting && User::where('is_super_admin', true)->count() <= 1) {
            throw ValidationException::withMessages([
                'is_super_admin' => 'This is the only Super Admin left — promote someone else first.',
            ]);
        }

        $user->update(['is_super_admin' => $granting]);

        return back()->with('success', $granting
            ? "{$user->name} is now a Super Admin."
            : "Super Admin access removed from {$user->name}.");
    }

    public function generatePasswordReset(Request $request, User $user)
    {
        $token = Password::createToken($user);
        $url = route('password.reset', ['token' => $token]).'?'.http_build_query(['email' => $user->email]);

        return response()->json(['url' => $url]);
    }
}
