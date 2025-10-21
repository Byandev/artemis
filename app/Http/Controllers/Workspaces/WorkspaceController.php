<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkspaceController extends Controller
{
    /**
     * Display a listing of user's workspaces.
     */
    public function index(Request $request)
    {
        $workspaces = $request->user()
            ->workspaces()
            ->with('owner')
            ->withCount('users')
            ->latest()
            ->get();

        return Inertia::render('workspaces/index', [
            'workspaces' => $workspaces,
        ]);
    }

    /**
     * Show the form for creating a new workspace.
     */
    public function create()
    {
        return Inertia::render('workspaces/create');
    }

    /**
     * Store a newly created workspace in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $workspace = Workspace::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'owner_id' => $request->user()->id,
        ]);

        // Add the user as owner in the pivot table
        $workspace->users()->attach($request->user()->id, ['role' => 'owner']);

        return redirect()->route('workspaces.show', $workspace->slug)
            ->with('success', 'Workspace created successfully.');
    }

    /**
     * Display the specified workspace.
     */
    public function show(Request $request, Workspace $workspace)
    {
        // Check if user has access to this workspace
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $workspace->load([
            'owner',
            'users' => function ($query) {
                $query->withPivot('role')->latest();
            },
        ]);

        $userRole = $workspace->users()
            ->where('user_id', $request->user()->id)
            ->first()
            ->pivot
            ->role;

        return Inertia::render('workspaces/show', [
            'workspace' => $workspace,
            'userRole' => $userRole,
        ]);
    }

    /**
     * Show the form for editing the specified workspace.
     */
    public function edit(Request $request, Workspace $workspace)
    {
        // Only owner and admins can edit workspace
        if (! $request->user()->isAdminOf($workspace)) {
            abort(403, 'You do not have permission to edit this workspace.');
        }

        return Inertia::render('workspaces/edit', [
            'workspace' => $workspace,
        ]);
    }

    /**
     * Update the specified workspace in storage.
     */
    public function update(Request $request, Workspace $workspace)
    {
        // Only owner and admins can update workspace
        if (! $request->user()->isAdminOf($workspace)) {
            abort(403, 'You do not have permission to update this workspace.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $workspace->update($validated);

        return redirect()->route('workspaces.show', $workspace->slug)
            ->with('success', 'Workspace updated successfully.');
    }

    /**
     * Remove the specified workspace from storage.
     */
    public function destroy(Request $request, Workspace $workspace)
    {
        // Only the owner can delete the workspace
        if (! $request->user()->ownsWorkspace($workspace)) {
            abort(403, 'Only the workspace owner can delete it.');
        }

        $workspace->delete();

        return redirect()->route('workspaces.index')
            ->with('success', 'Workspace deleted successfully.');
    }

    /**
     * Switch to a different workspace.
     */
    public function switch(Request $request, Workspace $workspace)
    {
        // Check if user has access to this workspace
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        // Store current workspace ID in session
        session(['current_workspace_id' => $workspace->id]);

        return redirect()->route('workspace.dashboard', $workspace->slug)
            ->with('success', "Switched to {$workspace->name}.");
    }

    /**
     * Display the workspace dashboard.
     */
    public function dashboard(Request $request, Workspace $workspace)
    {
        // Check if user has access to this workspace
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        // Set as current workspace
        session(['current_workspace_id' => $workspace->id]);

        $workspace->load('owner');

        $userRole = $workspace->users()
            ->where('user_id', $request->user()->id)
            ->first()
            ->pivot
            ->role;

        $stats = [
            'total_members' => $workspace->users()->count(),
            'pending_invitations' => $workspace->pendingInvitations()->count(),
        ];

        return Inertia::render('workspaces/dashboard', [
            'workspace' => $workspace,
            'userRole' => $userRole,
            'stats' => $stats,
        ]);
    }
}
