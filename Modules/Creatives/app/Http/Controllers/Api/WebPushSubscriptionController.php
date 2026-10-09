<?php

namespace Modules\Creatives\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Modules\Creatives\Models\WebPushSubscription;

/**
 * Web app (PWA): the browser's Web Push subscription, the web counterpart of
 * the phone's Expo push token. Like the token, it is re-sent on every launch,
 * so storing is idempotent.
 */
class WebPushSubscriptionController extends Controller
{
    /** The VAPID public key the browser subscribes with; null until it is configured. */
    public function key(): JsonResponse
    {
        return response()->json(['public_key' => config('services.webpush.public_key') ?: null]);
    }

    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'url:https', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', Rule::in(['aes128gcm', 'aesgcm'])],
        ]);

        // Keyed on the endpoint alone: a browser that logs in as someone else
        // moves to that user, so it only ever gets one person's notifications.
        WebPushSubscription::updateOrCreate(
            ['endpoint_hash' => WebPushSubscription::hashEndpoint($validated['endpoint'])],
            [
                'endpoint' => $validated['endpoint'],
                'user_id' => $request->user()->id,
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $validated['content_encoding'] ?? 'aes128gcm',
                'user_agent' => substr((string) $request->userAgent(), 0, 255) ?: null,
                'last_used_at' => now(),
            ],
        );

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
        ]);

        WebPushSubscription::forEndpoint($validated['endpoint'])
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->noContent();
    }
}
