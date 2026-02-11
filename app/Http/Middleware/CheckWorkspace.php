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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $workspace_id = $request->header('X-Workspace-Id');

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
