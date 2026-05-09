<?php

use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\CheckAdmin;
use App\Http\Middleware\CheckSubscription;
use App\Http\Middleware\CheckWorkspace;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetSentryContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->redirectGuestsTo(fn (Request $request) => route('login'));

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SetSentryContext::class,
        ]);

        $middleware->alias([
            'workspace' => CheckWorkspace::class,
            'subscription' => CheckSubscription::class,
            'admin' => CheckAdmin::class,
            'api.key' => AuthenticateApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthorizationException|HttpExceptionInterface $exception, Request $request) {
            $statusCode = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : 403;

            if ($statusCode !== 403 || $request->expectsJson()) {
                return null;
            }

            return Inertia::render('errors/403')
                ->toResponse($request)
                ->setStatusCode(403);
        });

        if (class_exists(Integration::class)) {
            Integration::handles($exceptions);
        }
    })->create();
