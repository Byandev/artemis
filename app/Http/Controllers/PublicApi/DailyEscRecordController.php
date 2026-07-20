<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Models\DailyEscRecord;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DailyEscRecordController extends Controller
{
    /**
     * Store (or update) an employee's daily Extreme Self-Care record.
     *
     * Auth is the workspace API key (api.key middleware), which identifies the
     * workspace. The specific employee is resolved from `email` and must belong
     * to that workspace. One record per employee per day, so a same-day
     * resubmit updates the existing row.
     *
     * The movement image may be sent as a multipart file upload
     * (`movement_image`, saved to the local disk) or as an already-hosted URL
     * (`movement_image_url`).
     */
    public function store(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'record_date' => ['nullable', 'date'],
            'learning_text' => ['nullable', 'string'],
            'movement_text' => ['nullable', 'string'],
            'movement_image' => ['nullable', 'image', 'max:10240'],
            'movement_image_url' => ['nullable', 'string', 'max:2048'],
            'meditation_completed' => ['required', 'boolean'],
        ]);

        // Resolve the employee and confirm they belong to this workspace.
        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! $workspace->users()->whereKey($user->id)->exists()) {
            return response()->json(['error' => 'Employee not found in this workspace.'], 404);
        }

        $recordDate = $validated['record_date'] ?? now()->toDateString();

        $existing = DailyEscRecord::where('user_id', $user->id)
            ->where('record_date', $recordDate)
            ->first();

        $movementImageUrl = $existing?->movement_image_url;

        if ($request->hasFile('movement_image')) {
            if ($movementImageUrl && ! str_starts_with($movementImageUrl, 'http')) {
                Storage::disk('local')->delete($movementImageUrl);
            }

            $movementImageUrl = $request->file('movement_image')
                ->store("esc/movement/{$user->id}", 'local');
        } elseif (filled($validated['movement_image_url'] ?? null)) {
            $movementImageUrl = $validated['movement_image_url'];
        }

        $meditationCompleted = (bool) $validated['meditation_completed'];

        $isComplete = filled($validated['learning_text'] ?? null)
            && filled($validated['movement_text'] ?? null)
            && $meditationCompleted;

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
                'is_complete' => $isComplete,
                'submitted_at' => now(),
            ],
        );

        return response()->json([
            'data' => $record,
            'created' => $record->wasRecentlyCreated,
        ], $record->wasRecentlyCreated ? 201 : 200);
    }
}
