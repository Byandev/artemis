<?php

use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\CheckAdmin;
use App\Http\Middleware\CheckSubscription;
use App\Http\Middleware\CheckWorkspace;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogRequestActivity;
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
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Honor X-Forwarded-* headers so redirects/URL generation use the correct
        // scheme + host when the app is served behind a proxy/tunnel (Expose, ngrok,
        // Cloudflare Tunnel). Without this, sharing the site produces redirect loops.
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->redirectGuestsTo(fn (Request $request) => route('login'));

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SetSentryContext::class,
            // Catch-all audit log for every state-changing web request.
            LogRequestActivity::class,
        ]);

        $middleware->alias([
            'workspace' => CheckWorkspace::class,
            'subscription' => CheckSubscription::class,
            'admin' => CheckAdmin::class,
            'api.key' => AuthenticateApiKey::class,
        ]);

        // The YX GP hardware device POSTs delivery receipts and inbound SMS to
        // these routes with no session cookie — authorised by a shared token in
        // VerifyGatewayCallback — so they must be exempt from CSRF.
        $middleware->validateCsrfTokens(except: [
            'gateway/callback/*',
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

        // Mail that fails while someone waits on it — an unauthorised sending
        // IP, a rejected key, an API that won't answer. All of it is ours to
        // fix and none of it is the user's doing, so they get a plain "try
        // again"; the provider's own words stay in the log where they belong.
        $exceptions->render(function (TransportExceptionInterface $exception, Request $request) {
            $message = "We couldn't send that email just now. It's a problem on our side, not with your account — please try again in a few minutes, or email hello@artemis.ph if it keeps happening.";

            // `is('api/*')` as well as the Accept header, since a machine client
            // that forgot to ask for JSON still shouldn't be handed a redirect.
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => $message], 503);
            }

            // Keyed to `email` because that's the field the auth forms already
            // show errors under, and flashed as well for the pages that have no
            // email field to hang it on.
            return back()->withErrors(['email' => $message])->with('error', $message);
        });

        if (class_exists(Integration::class)) {
            Integration::handles($exceptions);
        }
    })->create();
