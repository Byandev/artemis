<?php

namespace Modules\EscTracker\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Modules\EscTracker\Models\DailyEscRecord;
use Modules\EscTracker\Services\EscStreakCalculator;

class DailyEscRecordController extends Controller
{
    /**
     * List the authenticated employee's ESC records for a date range.
     *
     * Auth is the same Sanctum bearer token as the write endpoint, so the
     * records returned always belong to the token owner. The client typically
     * computes the Monday–Sunday of the current week and passes it as
     * `from`/`to`; when omitted, the current week is used.
     *
     * `record_date` is returned as a plain `YYYY-MM-DD` string (not a full ISO
     * datetime like the store response) so the client can key records by day
     * directly.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : Carbon::now()->startOfWeek(Carbon::MONDAY);

        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : Carbon::now()->endOfWeek(Carbon::SUNDAY);

        $records = DailyEscRecord::where('user_id', $user->id)
            ->whereBetween('record_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('record_date')
            ->get()
            ->map(fn (DailyEscRecord $record) => [
                'id' => $record->id,
                'record_date' => $record->record_date->toDateString(),
                'learning_text' => $record->learning_text,
                'movement_text' => $record->movement_text,
                'movement_image_url' => $record->movement_image_url,
                'meditation_completed' => (bool) $record->meditation_completed,
                'meditation_url' => $record->meditation_url,
                'submitted_at' => optional($record->submitted_at)?->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'data' => $records,
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ]);
    }

    /**
     * Store (or update) the authenticated employee's daily Extreme Self-Care
     * record.
     *
     * Auth is a Sanctum bearer token from /auth/login, so the employee is the
     * token owner — a caller can only ever write their own record. One record
     * per employee per day, so a same-day resubmit updates the existing row.
     *
     * The movement image may be sent as a multipart file upload
     * (`movement_image`, saved to the public disk) or as an already-hosted URL
     * (`movement_image_url`).
     */
    public function store(Request $request, EscStreakCalculator $streaks): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            // Users log today or catch up on a missed day — never the future.
            'record_date' => ['nullable', 'date', 'before_or_equal:today'],
            'learning_text' => ['nullable', 'string', 'max:5000'],
            'movement_text' => ['nullable', 'string', 'max:5000'],
            'movement_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'movement_image_url' => ['nullable', 'url', 'max:2048'],
            'meditation_completed' => ['required', 'boolean'],
            // Proof of meditation: upload a screenshot (`meditation_image`, saved
            // to the public disk) or pass an already-hosted link
            // (`meditation_url`, e.g. a Calm/Headspace session). Either way the
            // resolved URL is stored in `meditation_url`.
            'meditation_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'meditation_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $recordDate = $validated['record_date'] ?? now()->toDateString();

        $existing = DailyEscRecord::where('user_id', $user->id)
            ->where('record_date', $recordDate)
            ->first();

        $movementImageUrl = $existing?->movement_image_url;

        if ($request->hasFile('movement_image')) {
            // Stored on the `public` disk (symlinked at public/storage) so the
            // value we hand back is a URL WellSync can render directly, rather
            // than a private relative path.
            $previousPath = $this->uploadedPathFrom($movementImageUrl);

            if ($previousPath) {
                Storage::disk('public')->delete($previousPath);
            }

            $path = $request->file('movement_image')
                ->store("esc/movement/{$user->id}", 'public');

            $movementImageUrl = Storage::disk('public')->url($path);
        } elseif (filled($validated['movement_image_url'] ?? null)) {
            $movementImageUrl = $validated['movement_image_url'];
        }

        $meditationUrl = $existing?->meditation_url;

        if ($request->hasFile('meditation_image')) {
            $previousPath = $this->uploadedPathFrom($meditationUrl);

            if ($previousPath) {
                Storage::disk('public')->delete($previousPath);
            }

            $path = $request->file('meditation_image')
                ->store("esc/meditation/{$user->id}", 'public');

            $meditationUrl = Storage::disk('public')->url($path);
        } elseif (filled($validated['meditation_url'] ?? null)) {
            $meditationUrl = $validated['meditation_url'];
        }

        $meditationCompleted = (bool) $validated['meditation_completed'];

        $record = DailyEscRecord::updateOrCreate(
            [
                'user_id' => $user->id,
                'record_date' => $recordDate,
            ],
            [
                'learning_text' => $validated['learning_text'] ?? null,
                'movement_text' => $validated['movement_text'] ?? null,
                'movement_image_url' => $movementImageUrl,
                'meditation_completed' => $meditationCompleted,
                'meditation_url' => $meditationUrl,
                'submitted_at' => now(),
            ],
        );

        // Saving a record can extend, repair, or (via backfill) lengthen the
        // user's streaks, so recompute them from the full history now.
        $streaks->recalculateFor($user);

        return response()->json([
            'data' => $record,
            'created' => $record->wasRecentlyCreated,
            'streak' => [
                'current' => $user->current_streak,
                'longest' => $user->longest_streak,
            ],
        ], $record->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Map a previously stored `movement_image_url` back to its path on the
     * public disk, so a replaced upload can be cleaned up.
     *
     * Returns null when the value came from `movement_image_url` (an image
     * hosted elsewhere) — that isn't ours to delete.
     */
    private function uploadedPathFrom(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $base = rtrim(Storage::disk('public')->url(''), '/').'/';

        return str_starts_with($url, $base)
            ? substr($url, strlen($base))
            : null;
    }
}
