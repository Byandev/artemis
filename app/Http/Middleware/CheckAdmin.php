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
        // Check if user is logged in AND has the right role
        if ($request->user() && in_array($request->user()->role, ['superadmin', 'admin'])) {
            return $next($request);
        }

        // If not, send them back to the dashboard with a message
        return redirect()->route('dashboard')->with('error', 'Unauthorized access.');
    }
}
