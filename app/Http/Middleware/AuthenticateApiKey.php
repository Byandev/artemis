<?php

namespace App\Http\Middleware;

use App\Models\WorkspaceApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $raw = $this->extractKey($request);

        if (! $raw) {
            return response()->json(['error' => 'Missing API key.'], 401);
        }

        $apiKey = WorkspaceApiKey::findByRawKey($raw);

        if (! $apiKey) {
            return response()->json(['error' => 'Invalid API key.'], 401);
        }

        $apiKey->update(['last_used_at' => now()]);

        $request->attributes->set('workspace', $apiKey->workspace);
        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }

    private function extractKey(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if ($header && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return $request->header('X-API-Key');
    }
}
