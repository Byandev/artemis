<?php

namespace App\Listeners;

use App\Enums\Logging\LogCategory;
use App\Enums\Logging\LogStatus;
use App\Facades\Activity;
use App\Providers\ActivityLogServiceProvider;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Events\Dispatcher;

/**
 * Subscribes to Laravel's authentication events and records them under the
 * `auth` / `security` categories. Registered in
 * {@see ActivityLogServiceProvider}.
 */
class LogAuthenticationActivity
{
    public function handleLogin(Login $event): void
    {
        Activity::build()->asUser()
            ->category(LogCategory::Auth)->action('login')
            ->status(LogStatus::Success)
            ->user($event->user->getAuthIdentifier())
            ->message('User logged in')
            ->save();
    }

    public function handleLogout(Logout $event): void
    {
        Activity::build()->asUser()
            ->category(LogCategory::Auth)->action('logout')
            ->status(LogStatus::Info)
            ->user($event->user?->getAuthIdentifier())
            ->message('User logged out')
            ->save();
    }

    public function handleFailed(Failed $event): void
    {
        Activity::build()->asUser()
            ->category(LogCategory::Auth)->action('login.failed')
            ->status(LogStatus::Warning)
            ->message('Failed login attempt')
            ->metadata(['email' => $event->credentials['email'] ?? null])
            ->save();
    }

    public function handleLockout(Lockout $event): void
    {
        Activity::build()->asUser()
            ->category(LogCategory::Security)->action('login.lockout')
            ->status(LogStatus::Warning)
            ->message('Too many login attempts — throttled')
            ->metadata(['email' => $event->request->input('email')])
            ->save();
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        Activity::build()->asUser()
            ->category(LogCategory::Security)->action('password.reset')
            ->status(LogStatus::Success)
            ->user($event->user->getAuthIdentifier())
            ->message('Password was reset')
            ->save();
    }

    public function handleRegistered(Registered $event): void
    {
        Activity::build()->asUser()
            ->category(LogCategory::Auth)->action('user.registered')
            ->status(LogStatus::Success)
            ->user($event->user->getAuthIdentifier())
            ->message('New user registered')
            ->save();
    }

    public function handleVerified(Verified $event): void
    {
        Activity::build()->asUser()
            ->category(LogCategory::Auth)->action('email.verified')
            ->status(LogStatus::Success)
            ->user($event->user->getAuthIdentifier())
            ->message('Email address verified')
            ->save();
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
            Failed::class => 'handleFailed',
            Lockout::class => 'handleLockout',
            PasswordReset::class => 'handlePasswordReset',
            Registered::class => 'handleRegistered',
            Verified::class => 'handleVerified',
        ];
    }
}
