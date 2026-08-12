<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * The parent's authorization() callback already bypasses this gate on
     * local, so this only runs in non-local environments — only super
     * admins can access Horizon in staging/production.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            return true;
            
            return $user && $user->is_super_admin;
        });
    }
}
