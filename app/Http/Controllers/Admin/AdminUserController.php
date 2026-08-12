<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Logging\LogCategory;
use App\Enums\Logging\LogStatus;
use App\Facades\Activity;
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

    /**
     * Grant or revoke platform-wide super admin access.
     */
    public function updateSuperAdmin(Request $request, User $user)
    {
        $validated = $request->validate([
            'is_super_admin' => ['required', 'boolean'],
        ]);

        // Revoking your own access locks you out of this page immediately, and
        // only another super admin could put it back.
        if ($user->is($request->user()) && ! $validated['is_super_admin']) {
            return back()->withErrors([
                'is_super_admin' => 'You cannot revoke your own super admin access.',
            ]);
        }

        $user->update(['is_super_admin' => $validated['is_super_admin']]);

        // Granting super admin moves this user's landing page to the admin
        // panel, which sits behind the `verified` middleware — so an unverified
        // account would be bounced to the verification notice on its next login
        // and never reach the access it was just given. The granting super
        // admin vouching for the account is what verifies it.
        // Set through the model, not the update above: `email_verified_at` is
        // not mass-assignable and would be dropped silently.
        if ($validated['is_super_admin'] && ! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        // Platform-wide privilege changes are the most security-relevant thing
        // this panel can do, so each one gets its own audit line.
        Activity::build()->asUser()
            ->category(LogCategory::Security)
            ->action($validated['is_super_admin'] ? 'user.super_admin.granted' : 'user.super_admin.revoked')
            ->status(LogStatus::Success)
            ->message(($validated['is_super_admin'] ? 'Super admin granted to ' : 'Super admin revoked from ').$user->email)
            ->metadata(['target_user_id' => $user->id, 'target_email' => $user->email])
            ->save();

        return back()->with('success', $validated['is_super_admin']
            ? "{$user->name} is now a super admin."
            : "Super admin access revoked from {$user->name}.");
    }
}
