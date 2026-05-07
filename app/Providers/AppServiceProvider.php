<?php

namespace App\Providers;

use App\Models\Workspace;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use PostHog\PostHog;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! config('posthog.disabled') && config('posthog.api_key')) {
            PostHog::init(config('posthog.api_key'), [
                'host' => config('posthog.host'),
            ]);
        }

        RateLimiter::for('parcel-notification', function ($job) {
            return Limit::perMinute(30);
        });

        RateLimiter::for('csr-public-performance', function (Request $request) {
            $workspace = $request->route('workspace');
            $workspaceKey = is_object($workspace) ? ($workspace->slug ?? $workspace->id ?? 'unknown') : ($workspace ?? 'unknown');

            return [
                Limit::perMinute(20)->by($request->ip().'|'.$workspaceKey),
            ];
        });

        RateLimiter::for('csr-public-csrs', function (Request $request) {
            $workspace = $request->route('workspace');
            $workspaceKey = is_object($workspace) ? ($workspace->slug ?? $workspace->id ?? 'unknown') : ($workspace ?? 'unknown');

            return [
                Limit::perMinute(30)->by($request->ip().'|'.$workspaceKey),
            ];
        });

        Gate::before(function ($user, $ability, $params) {
            // TEMP: bypass role/permission checks in production while RBAC rollout is still on the test server.
            if (app()->environment('production')) {
                return true;
            }

            $workspace = $params[0] ?? null;

            if ($workspace instanceof Workspace) {
                $hasPermission = $user->hasPermission($ability, $workspace);

                return $hasPermission ? true : null;
            }

            return null;
        });
    }
}
