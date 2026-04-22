<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {

        if ($request->user() && $request->user()->is_super_admin) {
            return $next($request);
        }

        return redirect()->route('dashboard')->with('error', 'Unauthorized access.');
    }
}
