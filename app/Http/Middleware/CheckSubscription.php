<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
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

        $subscription = $workspace->subscription;

        $isExpired = ! $subscription
            || $subscription->status === Subscription::STATUS_EXPIRED
            || $subscription->status === Subscription::STATUS_CANCELED
            || ($subscription->status === Subscription::STATUS_TRIALING && $subscription->trial_ends_at && $subscription->trial_ends_at->isPast())
            || ($subscription->status === Subscription::STATUS_ACTIVE && $subscription->current_period_end && $subscription->current_period_end->isPast());

        if ($isExpired && $request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Your subscription has expired. Please upgrade to continue.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
