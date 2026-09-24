<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shuts the public (no-auth) workspace pages once the workspace's subscription
 * status is expired, canceled or past due (or it has no subscription at all). Unlike CheckSubscription, which only nudges members with a modal,
 * this is a hard stop: anyone holding the public link loses access, including
 * the page's XHR, export and write endpoints.
 */
class EnsurePublicWorkspaceSubscribed
{
    private const LOCKED_STATUSES = [
        Subscription::STATUS_EXPIRED,
        Subscription::STATUS_CANCELED,
        Subscription::STATUS_PAST_DUE,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->route('workspace');

        // No local or super-admin escape hatch: the public link is closed for everyone.
        if (! $workspace instanceof Workspace) {
            return $next($request);
        }

        // Goes by status alone, not isLapsed(): a period end that has slipped by
        // doesn't close the page until the status itself is flipped.
        $status = $workspace->subscription?->status;

        if ($status && ! in_array($status, self::LOCKED_STATUSES, true)) {
            return $next($request);
        }

        if ($request->isMethod('GET') && ! $request->wantsJson()) {
            return Inertia::render('workspaces/public/subscription-expired', [
                'workspace' => $workspace->only('id', 'name', 'slug'),
            ])->toResponse($request);
        }

        abort(Response::HTTP_FORBIDDEN, 'This workspace\'s subscription has expired.');
    }
}
