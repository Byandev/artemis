<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
    }
}
