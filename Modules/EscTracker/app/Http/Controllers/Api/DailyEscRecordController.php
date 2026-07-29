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
            ->map(fn (DailyEscRecord $record) => $this->presentSummary($record))
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
            // Tick the pillar without writing anything. Optional: when omitted
            // the flag is derived from whether text/an image was sent, so a
            // client that only posts notes keeps working unchanged.
            'learning_completed' => ['sometimes', 'boolean'],
            'movement_completed' => ['sometimes', 'boolean'],
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
        $learningText = $validated['learning_text'] ?? null;
        $movementText = $validated['movement_text'] ?? null;

        // An explicit flag from the client wins; otherwise fall back to "did
        // they send anything for this pillar?". That lets a checklist-style UI
        // post booleans alone, while a notes-style UI needs no changes.
        $learningCompleted = array_key_exists('learning_completed', $validated)
            ? (bool) $validated['learning_completed']
            : filled($learningText);

        $movementCompleted = array_key_exists('movement_completed', $validated)
            ? (bool) $validated['movement_completed']
            : filled($movementText) || filled($movementImageUrl);

        $record = DailyEscRecord::updateOrCreate(
            [
                'user_id' => $user->id,
                'record_date' => $recordDate,
            ],
            [
                'learning_text' => $learningText,
                'learning_completed' => $learningCompleted,
                'movement_text' => $movementText,
                'movement_image_url' => $movementImageUrl,
                'movement_completed' => $movementCompleted,
                'meditation_completed' => $meditationCompleted,
                'meditation_url' => $meditationUrl,
                'submitted_at' => now(),
            ],
        );

        // Saving a record can extend, repair, or (via backfill) lengthen the
        // user's streaks, so recompute them from the full history now.
        $streaks->recalculateFor($user);

        return response()->json([
            'data' => $this->present($record),
            'created' => $record->wasRecentlyCreated,
            'streak' => [
                'current' => $user->current_streak,
                'longest' => $user->longest_streak,
            ],
        ], $record->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Today's record for the authenticated employee, for prefilling the "edit
     * today" screen. `data` is null when nothing has been logged yet, so the
     * client can tell "not started" from "logged and empty".
     */
    public function today(Request $request): JsonResponse
    {
        $user = $request->user();

        $record = DailyEscRecord::where('user_id', $user->id)
            ->where('record_date', now()->toDateString())
            ->first();

        return response()->json([
            'data' => $record ? $this->present($record) : null,
            'streak' => [
                'current' => (int) $user->current_streak,
                'longest' => (int) $user->longest_streak,
            ],
        ]);
    }

    /**
     * Partially update today's record.
     *
     * Unlike `store`, which writes the whole row, this only touches the fields
     * actually present in the request — so an edit screen can save one toggle
     * without blanking the notes the user wrote earlier. Every field is
     * optional; the row is created if today hasn't been logged yet.
     */
    public function updateToday(Request $request, EscStreakCalculator $streaks): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'learning_text' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'movement_text' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'movement_image' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'movement_image_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'meditation_image' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'meditation_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'learning_completed' => ['sometimes', 'boolean'],
            'movement_completed' => ['sometimes', 'boolean'],
            'meditation_completed' => ['sometimes', 'boolean'],
        ]);

        $record = DailyEscRecord::firstOrNew([
            'user_id' => $user->id,
            'record_date' => now()->toDateString(),
        ]);

        // Plain fields: only overwrite what was actually sent.
        foreach (['learning_text', 'movement_text', 'learning_completed', 'movement_completed', 'meditation_completed'] as $field) {
            if (array_key_exists($field, $validated)) {
                $record->{$field} = $validated[$field];
            }
        }

        if ($request->hasFile('movement_image')) {
            $record->movement_image_url = $this->replaceUpload(
                $record->movement_image_url,
                $request->file('movement_image'),
                "esc/movement/{$user->id}",
            );
        } elseif (array_key_exists('movement_image_url', $validated)) {
            $record->movement_image_url = $validated['movement_image_url'];
        }

        if ($request->hasFile('meditation_image')) {
            $record->meditation_url = $this->replaceUpload(
                $record->meditation_url,
                $request->file('meditation_image'),
                "esc/meditation/{$user->id}",
            );
        } elseif (array_key_exists('meditation_url', $validated)) {
            $record->meditation_url = $validated['meditation_url'];
        }

        // A pillar the client didn't explicitly flag still tracks whatever it
        // now holds, so ticking follows the content without contradicting an
        // explicit flag.
        if (! array_key_exists('learning_completed', $validated) && array_key_exists('learning_text', $validated)) {
            $record->learning_completed = filled($record->learning_text);
        }

        if (! array_key_exists('movement_completed', $validated)
            && (array_key_exists('movement_text', $validated) || $request->hasFile('movement_image') || array_key_exists('movement_image_url', $validated))) {
            $record->movement_completed = filled($record->movement_text) || filled($record->movement_image_url);
        }

        $created = ! $record->exists;
        $record->submitted_at = now();
        $record->save();

        $streaks->recalculateFor($user);

        return response()->json([
            'data' => $this->present($record),
            'created' => $created,
            'streak' => [
                'current' => $user->current_streak,
                'longest' => $user->longest_streak,
            ],
        ], $created ? 201 : 200);
    }

    /**
     * Shape a record for the week/range view: the date and the three pillar
     * ticks, nothing else.
     *
     * That view is a checklist grid — it only needs to know which pillars were
     * completed each day, so notes and proof URLs are deliberately left out to
     * keep the payload small. Fetch a single day via `/daily-records/today` (or
     * the save response) when you need the full content to edit.
     *
     * Every key is always present here — the booleans are cast, never null — so
     * the client can map days without null checks.
     *
     * @return array<string, mixed>
     */
    private function presentSummary(DailyEscRecord $record): array
    {
        return [
            'record_date' => $record->record_date instanceof Carbon
                ? $record->record_date->toDateString()
                : Carbon::parse($record->record_date)->toDateString(),
            'learning_completed' => (bool) $record->learning_completed,
            'movement_completed' => (bool) $record->movement_completed,
            'meditation_completed' => (bool) $record->meditation_completed,
        ];
    }

    /**
     * Shape a full record for the API.
     *
     * `record_date` goes out as a plain `YYYY-MM-DD` — the model casts it to a
     * date, and serialising that as an ISO datetime shifts it a day in a
     * non-UTC app timezone, which would put the client on the wrong day.
     *
     * Null fields are dropped: a checklist-only client gets a lean payload
     * instead of a wall of nulls, while a client that stores notes still gets
     * them back for editing.
     *
     * @return array<string, mixed>
     */
    private function present(DailyEscRecord $record): array
    {
        return array_filter([
            'id' => $record->id,
            // Always the token owner, but part of the documented contract.
            'user_id' => $record->user_id,
            'record_date' => $record->record_date instanceof Carbon
                ? $record->record_date->toDateString()
                : Carbon::parse($record->record_date)->toDateString(),
            'learning_completed' => (bool) $record->learning_completed,
            'movement_completed' => (bool) $record->movement_completed,
            'meditation_completed' => (bool) $record->meditation_completed,
            'learning_text' => $record->learning_text,
            'movement_text' => $record->movement_text,
            'movement_image_url' => $record->movement_image_url,
            'meditation_url' => $record->meditation_url,
            'submitted_at' => optional($record->submitted_at)?->toIso8601String(),
        ], fn ($value) => ! is_null($value));
    }

    /**
     * Store a new upload on the public disk, deleting the file it replaces when
     * that file was one of ours.
     */
    private function replaceUpload(?string $currentUrl, $file, string $directory): string
    {
        $previousPath = $this->uploadedPathFrom($currentUrl);

        if ($previousPath) {
            Storage::disk('public')->delete($previousPath);
        }

        return Storage::disk('public')->url($file->store($directory, 'public'));
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
