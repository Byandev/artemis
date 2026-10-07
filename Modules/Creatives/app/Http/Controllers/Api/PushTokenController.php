<?php

namespace Modules\Creatives\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Modules\Creatives\Models\PushToken;

/**
 * Mobile app: registers the phone's Expo push token so the signed-in user gets
 * pushes for new creatives to review. The app re-sends it on every launch, so
 * storing is idempotent.
 */
class PushTokenController extends Controller
{
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255', 'starts_with:ExponentPushToken[,ExpoPushToken['],
            'platform' => ['required', Rule::in(['ios', 'android'])],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        // Keyed on the token alone: a phone that logs in as someone else moves
        // to that user, so it only ever gets one person's notifications.
        PushToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $validated['platform'],
                'device_name' => $validated['device_name'] ?? null,
                'last_used_at' => now(),
            ],
        );

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ]);

        PushToken::where('token', $validated['token'])
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->noContent();
    }
}
