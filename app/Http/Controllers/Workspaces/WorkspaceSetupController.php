<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkspaceSetupController extends Controller
{
    /**
     * Show the workspace setup form (after registration).
     */
    public function create(Request $request)
    {
        // Check if user already has a workspace
        if ($request->user()->workspaces()->exists()) {
            $workspace = $request->user()->ownedWorkspaces()->first()
                ?? $request->user()->workspaces()->first();

            return redirect()->route('workspace.dashboard', $workspace->slug);
        }

        return Inertia::render('workspaces/setup', [
            'userName' => $request->user()->name,
        ]);
    }

    /**
     * Create the initial workspace for a new user.
     */
    public function store(Request $request)
    {
        // Check if user already has a workspace
        if ($request->user()->workspaces()->exists()) {
            $workspace = $request->user()->ownedWorkspaces()->first()
                ?? $request->user()->workspaces()->first();

            return redirect()->route('workspace.dashboard', $workspace->slug)
                ->with('info', 'You already have a workspace.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'min:3'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        // Create the workspace
        $workspace = Workspace::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'owner_id' => $request->user()->id,
        ]);

        // Add the user as owner in the pivot table
        $workspace->users()->attach($request->user()->id, ['role' => 'owner']);

        // Set as current workspace
        session(['current_workspace_id' => $workspace->id]);

        return redirect()->route('workspace.dashboard', $workspace->slug)
            ->with('success', 'Welcome to your new workspace!');
    }
}
