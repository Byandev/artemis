<?php

namespace App\Providers;

use App\Mail\BrevoTransport;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\MetricSettingPolicy;
use App\Services\Logging\ActivityLogger;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Modules\GencysERP\Models\Intern;
use PostHog\PostHog;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Single shared activity/system logger instance.
        $this->app->singleton(ActivityLogger::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Short, rename-proof keys for the advertiser polymorphic relation
        // (advertiser_performance_daily_records.advertiser_model).
        Relation::morphMap([
            'intern' => Intern::class,
            'user' => User::class,
        ]);

        // Resolved lazily, so a missing key only bites when something sends.
        Mail::extend('brevo', fn () => new BrevoTransport(
            (string) config('services.brevo.key'),
            (string) config('services.brevo.endpoint'),
            (int) config('services.brevo.timeout'),
        ));

        if (! config('posthog.disabled') && config('posthog.api_key')) {
            PostHog::init(config('posthog.api_key'), [
                'host' => config('posthog.host'),
            ]);
        }

        RateLimiter::for('parcel-notification', function ($job) {
            return Limit::perMinute(30);
        });

        // Credential checks are the one public-API route where a valid key still
        // lets the caller guess, so throttle per email+IP as well as per key.
        RateLimiter::for('public-api-login', function (Request $request) {
            $apiKey = $request->attributes->get('api_key');

            return [
                Limit::perMinute(5)->by($request->ip().'|'.strtolower((string) $request->input('email'))),
                Limit::perMinute(30)->by('key:'.($apiKey->id ?? $request->ip())),
            ];
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

        // The RDP Builder's "Suggest 10 names" button. Every press is a paid
        // call to the model provider, so unlike the limiters above this one
        // guards a bill rather than a database. Keyed on the user as well as
        // the workspace: the point is to stop one person leaning on the button,
        // and the endpoint is behind auth so there is always a user.
        RateLimiter::for('product-research-suggestions', function (Request $request) {
            $workspace = $request->route('workspace');
            $workspaceKey = is_object($workspace) ? ($workspace->slug ?? $workspace->id ?? 'unknown') : ($workspace ?? 'unknown');

            return [
                Limit::perMinute(10)->by(($request->user()?->id ?? $request->ip()).'|'.$workspaceKey),
            ];
        });

        Gate::policy(Workspace::class, MetricSettingPolicy::class);

        Gate::before(function ($user, $ability, $params) {
            $workspace = $params[0] ?? null;

            if ($workspace instanceof Workspace) {
                $hasPermission = $user->hasPermission($ability, $workspace);

                return $hasPermission ? true : null;
            }

            return null;
        });
    }
}
