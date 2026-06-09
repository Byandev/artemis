<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->route('workspace');

        if (! $workspace) {
            return $next($request);
        }

        if ($request->user()?->isSuperAdmin()) {
            return $next($request);
        }

        if (! $request->user()?->ownsWorkspace($workspace) && ! $request->user()?->isMemberOf($workspace)) {
            return $next($request);
        }

        $subscription = $workspace->subscription;

        $isExpired = ! $subscription || $subscription->isLapsed();

        if ($isExpired && $request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Your subscription has expired. Please upgrade to continue.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
