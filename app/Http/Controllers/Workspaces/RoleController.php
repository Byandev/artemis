<?php

namespace App\Http\Controllers\Workspaces;

use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use App\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoleController extends Controller
{
    public function index(Workspace $workspace)
    {
        $users = User::select('id', 'name', 'email', 'role')->get();

        $availableRoles = Role::withTrashed()
            ->where('workspace_id', $workspace->id)
            ->get();

        return Inertia::render('roles/index', [
            'users' => $users,
            'workspace' => $workspace,
            'roles' => $availableRoles,
            'auth' => ['user' => auth()->user()],
        ]);
    }
    public function create(Workspace $workspace)
    {
        return Inertia::render('roles/create', [
            'workspace' => $workspace,
            'auth' => ['user' => auth()->user()],
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'role' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        $workspace->users()->syncWithoutDetaching([
            $user->id => ['role' => $validated['role']]
        ]);

        return redirect()->route('roles.index', $workspace->slug)
            ->with('success', "{$user->name} assigned the {$validated['role']} role.");
    }



    public function storeRole(Request $request, Workspace $workspace)
    {
        $validated = $request->validate([
            'display_name' => 'required|string|max:255',
            'role' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'role')->where('workspace_id', $workspace->id),
            ],
            'description' => 'nullable|string',
        ]);

        $workspace->roles()->create($validated);
        return back()->with('success', 'Role created successfully!');
    }

    public function add(Request $request, Workspace $workspace)
    {
        return Inertia::render('roles/add', [
            'workspace' => $workspace,
            'auth' => ['user' => $request->user()],
        ]);
    }

    // Edit Role Definition
    public function edit(Workspace $workspace, Role $role)
    {
        return Inertia::render('roles/edit', [
            'workspace' => $workspace,
            'role' => $role
        ]);
    }

    public function update(Request $request, Workspace $workspace, Role $role)
    {

        if (!auth()->user()->isSuperAdmin()) {
            abort(403, 'Your access reach is not high enough to modify role definitions.');
        }

        $validated = $request->validate([
            'display_name' => 'required|string|max:255',
            'role' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $role->update([
            'display_name' => $validated['display_name'],
            'name' => $validated['role'],
            'description' => $validated['description'],
        ]);

        return redirect()->route('roles.index', $workspace->slug);
    }

    public function editUserRole(Workspace $workspace, User $user)
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isAdminOf($workspace)) {
            abort(403, 'You do not have the reach to manage roles here.');
        }

        return Inertia::render('roles/edit', [
            'workspace' => $workspace,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ?? '',
            ],
            'availableRoles' => Role::all(),
        ]);
    }

    public function updateUserRole(Request $request, Workspace $workspace, User $user)
    {
        $validated = $request->validate([
            'role' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $user->role = strtolower($validated['role']);
        $user->save();

        Role::where('role', $validated['role'])->update([
            'description' => $validated['description']
        ]);

        $workspace->users()->updateExistingPivot($user->id, [
            'role' => (strtolower($validated['role']) === 'superadmin') ? 'admin' : 'member'
        ]);

        return redirect()->back()->with('success', 'User role and description updated successfully');
    }

    public function archive(Workspace $workspace, Role $role)
    {
        $role->delete();

        return redirect()->route('roles.index', [
            'workspace' => $workspace->slug,
            'archived' => 'true'
        ])->with('success', 'Role archived successfully!');
    }

    public function restore(Workspace $workspace, $role)
    {
        $role = Role::withTrashed()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($role);

        $role->restore();

        return redirect()->route('roles.index', [
            'workspace' => $workspace->slug
        ])->with('success', 'Role restored successfully!');
    }

}