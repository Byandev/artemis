<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Models\WorkspaceApiKey;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkspaceApiKeyController extends Controller
{
    public function index(Request $request, Workspace $workspace): \Inertia\Response
    {
        if (! $request->user()->isAdminOf($workspace)) {
            abort(403);
        }

        $keys = $workspace->apiKeys()
            ->latest()
            ->get(['id', 'name', 'key_prefix', 'last_used_at', 'created_at']);

        return Inertia::render('workspaces/api-keys', [
            'workspace' => $workspace,
            'apiKeys' => $keys,
        ]);
    }

    public function store(Request $request, Workspace $workspace): \Illuminate\Http\RedirectResponse
    {
        if (! $request->user()->isAdminOf($workspace)) {
            abort(403);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $generated = WorkspaceApiKey::generate();

        $workspace->apiKeys()->create([
            'name' => $request->name,
            'key' => $generated['key'],
            'key_encrypted' => $generated['key_encrypted'],
            'key_prefix' => $generated['prefix'],
        ]);

        return back()->with('newApiKey', $generated['raw']);
    }

    public function reveal(Request $request, Workspace $workspace, WorkspaceApiKey $apiKey): \Illuminate\Http\JsonResponse
    {
        if (! $request->user()->isAdminOf($workspace)) {
            abort(403);
        }

        abort_if($apiKey->workspace_id !== $workspace->id, 404);

        if (! $apiKey->key_encrypted) {
            return response()->json(['error' => 'This key was created before reveal support was added. Please revoke it and create a new one.'], 422);
        }

        return response()->json(['key' => $apiKey->reveal()]);
    }

    public function destroy(Request $request, Workspace $workspace, WorkspaceApiKey $apiKey): \Illuminate\Http\RedirectResponse
    {
        if (! $request->user()->isAdminOf($workspace)) {
            abort(403);
        }

        abort_if($apiKey->workspace_id !== $workspace->id, 404);

        $apiKey->delete();

        return back()->with('success', 'API key revoked.');
    }
}
