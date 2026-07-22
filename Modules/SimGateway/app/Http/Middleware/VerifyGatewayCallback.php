<?php

namespace Modules\SimGateway\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validate that an incoming callback really came from our configured YX GP device.
 *
 * The device has no native HMAC support; the best it can do is include a shared
 * token in the callback. Different firmwares surface it differently: some keep
 * it as a `?token=` query param, others relocate it into the JSON body (`token`
 * field) when POSTing a status-report. We accept it from either place, plus an
 * `X-Gateway-Token` header, and compare in constant time.
 *
 * Callback URL shape: https://our-host/gateway/callback/sms?token=XXX
 */
class VerifyGatewayCallback
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('simgateway.callback.token', '');

        if ($expected === '') {
            return response()->json([
                'error' => 'Gateway callback token not configured on this server.',
            ], 503);
        }

        $received = (string) (
            $request->query('token', '')
            ?: $request->header('X-Gateway-Token', '')
            ?: $request->input('token', '')
        );

        if (! hash_equals($expected, $received)) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        return $next($request);
    }
}
