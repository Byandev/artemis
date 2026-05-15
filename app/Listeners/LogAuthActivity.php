<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Events\Dispatcher;

class LogAuthActivity
{
    public function onLogin(Login $event): void
    {
        $user = $event->user;

        activity()
            ->causedBy($user)
            ->withProperties(['ip' => request()->ip() ?? null])
            ->log('login');
    }

    public function onLogout(Logout $event): void
    {
        $user = $event->user;

        activity()
            ->causedBy($user)
            ->withProperties(['ip' => request()->ip() ?? null])
            ->log('logout');
    }

    public function onFailed(Failed $event): void
    {
        $user = is_object($event->user) ? $event->user : null;

        activity()
            ->performedOn($user)
            ->withProperties(['attempt' => $event->credentials ?? null, 'ip' => request()->ip() ?? null])
            ->log('login_failed');
    }

    public function onPasswordReset(PasswordReset $event): void
    {
        $user = $event->user;

        activity()
            ->causedBy($user)
            ->withProperties(['ip' => request()->ip() ?? null])
            ->log('password_reset');
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Login::class, [self::class, 'onLogin']);
        $events->listen(Logout::class, [self::class, 'onLogout']);
        $events->listen(Failed::class, [self::class, 'onFailed']);
        $events->listen(PasswordReset::class, [self::class, 'onPasswordReset']);
    }
}
