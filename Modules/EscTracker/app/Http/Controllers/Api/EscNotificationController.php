<?php

namespace Modules\EscTracker\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\EscTracker\Models\EscNotification;

class EscNotificationController extends Controller
{
    /**
     * Read the authenticated employee's ESC reminder settings.
     *
     * Auth is the same Sanctum bearer token as the rest of the WellSync API, so
     * the settings always belong to the token owner. A user who has never opened
     * the settings screen has no row yet, so one is materialised with the schema
     * defaults — that way the reminder scheduler sees every user, not only those
     * who happened to save once.
     */
    public function show(Request $request): JsonResponse
    {
        $settings = EscNotification::firstOrCreate(['user_id' => $request->user()->id]);

        return response()->json(['data' => $this->present($settings)]);
    }

    /**
     * Save the authenticated employee's ESC reminder settings.
     *
     * Every field is optional so the client can PATCH a single toggle without
     * resending the whole object; omitted fields keep their current value.
     * `last_reminded_at` is deliberately not writable — the scheduler owns it.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'reminder_enabled' => ['sometimes', 'boolean'],
            // Accept a time picker's "20:00" as well as a full "20:00:00".
            'reminder_time' => ['sometimes', 'date_format:H:i,H:i:s'],
            // Must be a real IANA identifier (e.g. Asia/Manila) — the reminder
            // time is wall-clock in this zone, so a bogus one would misfire.
            'reminder_timezone' => ['sometimes', 'timezone'],
            'reminder_style' => ['sometimes', 'string', 'max:16'],
        ]);

        $settings = EscNotification::firstOrCreate(['user_id' => $user->id]);

        if (array_key_exists('reminder_time', $validated)) {
            // Normalise to H:i:s so the stored value is consistent regardless of
            // which of the two accepted formats the client sent.
            $validated['reminder_time'] = $this->normaliseTime($validated['reminder_time']);
        }

        $settings->fill($validated)->save();

        return response()->json(['data' => $this->present($settings)]);
    }

    /**
     * Shape the settings for the API. `reminder_time` goes out as `HH:MM` — the
     * form a time picker expects — while the column keeps full second precision.
     */
    private function present(EscNotification $settings): array
    {
        return [
            'reminder_enabled' => (bool) $settings->reminder_enabled,
            'reminder_time' => $this->normaliseTime($settings->reminder_time, 'H:i'),
            'reminder_timezone' => $settings->reminder_timezone,
            'reminder_style' => $settings->reminder_style,
            'last_reminded_at' => optional($settings->last_reminded_at)?->toIso8601String(),
        ];
    }

    /**
     * Reformat a stored or submitted time, accepting either `H:i` or `H:i:s`.
     */
    private function normaliseTime(string $time, string $format = 'H:i:s'): string
    {
        return Carbon::createFromFormat(
            strlen($time) === 5 ? 'H:i' : 'H:i:s',
            $time,
        )->format($format);
    }
}
