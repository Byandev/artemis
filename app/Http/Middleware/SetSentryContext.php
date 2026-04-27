<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Sentry\State\Scope;

class SetSentryContext
{
    public function handle(Request $request, Closure $next)
    {
        if (! app()->bound('sentry')) {
            return $next($request);
        }

        \Sentry\configureScope(function (Scope $scope) use ($request) {
            if ($user = $request->user()) {
                $scope->setUser([
                    'id' => $user->getAuthIdentifier(),
                ]);
            }

            $workspace = $request->route('workspace');
            if ($workspace instanceof Workspace) {
                $scope->setTag('workspace_id', (string) $workspace->id);
                $scope->setTag('workspace_slug', (string) $workspace->slug);
            } elseif (is_scalar($workspace)) {
                $scope->setTag('workspace', (string) $workspace);
            }
        });

        return $next($request);
    }
}
