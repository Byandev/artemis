<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Models\WorkspaceApiKey;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceApiKeyController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace): Response
    {
        $this->authorize('Manage API Keys', $workspace);

        $keys = $workspace->apiKeys()
            ->latest()
            ->get(['id', 'name', 'key_prefix', 'last_used_at', 'created_at']);

        return Inertia::render('workspaces/api-keys', [
            'workspace' => $workspace,
            'apiKeys' => $keys,
        ]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('Manage API Keys', $workspace);

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

    public function reveal(Request $request, Workspace $workspace, WorkspaceApiKey $apiKey): JsonResponse
    {
        $this->authorize('Manage API Keys', $workspace);

        abort_if($apiKey->workspace_id !== $workspace->id, 404);

        if (! $apiKey->key_encrypted) {
            return response()->json([
                'error' => 'This key was created before reveal support was added. Please revoke it and create a new one.',
            ], 422);
        }

        return response()->json(['key' => $apiKey->reveal()]);
    }

    public function destroy(Request $request, Workspace $workspace, WorkspaceApiKey $apiKey): RedirectResponse
    {
        $this->authorize('Manage API Keys', $workspace);

        abort_if($apiKey->workspace_id !== $workspace->id, 404);

        $apiKey->delete();

        return back()->with('success', 'API key revoked.');
    }
}
