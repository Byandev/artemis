<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckWorkspace
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $workspace_id = $request->header('X-Workspace-Id');

        // Fallback to route-bound workspace for URL-scoped routes.
        if (! $workspace_id) {
            $routeWorkspace = $request->route('workspace');

            if ($routeWorkspace instanceof Workspace) {
                $workspace_id = $routeWorkspace->id;
            } elseif (! empty($routeWorkspace)) {
                $workspace_id = $routeWorkspace;
            }
        }

        // Fallback to the user's active workspace in session.
        if (! $workspace_id) {
            $workspace_id = session('current_workspace_id');
        }

        if (! $workspace_id) {
            return response()->json([
                'success' => false,
                'message' => 'You dont have access to this resource.',
            ], Response::HTTP_FORBIDDEN);
        }

        $workspace = Workspace::find($workspace_id);

        if (! $workspace) {
            return response()->json([
                'success' => false,
                'message' => 'You dont have access to this resource.',
            ], Response::HTTP_FORBIDDEN);
        }

        if (! $request->user()->isMemberOf($workspace)) {
            return response()->json([
                'success' => false,
                'message' => 'You dont have access to this resource.',
            ], Response::HTTP_FORBIDDEN);
        }

        $request->merge(['workspace' => $workspace]);

        return $next($request);
    }
}
