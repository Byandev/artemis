<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {

        if ($request->user() && $request->user()->is_super_admin) {
            return $next($request);
        }

        return redirect()->route('dashboard')->with('error', 'Unauthorized access.');
    }
}
