<?php

namespace Modules\Creatives\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Creatives\Models\ReminderSetting;

/**
 * Mobile app: the signed-in user's daily reminder (on/off and time of day,
 * Asia/Manila). Pushes for newly assigned creatives are not affected.
 */
class ReminderSettingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['daily_reminder' => ReminderSetting::for($request->user())->toApi()]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'time' => ['required', 'date_format:H:i'],
        ]);

        $setting = ReminderSetting::for($request->user());
        $setting->fill([
            'daily_reminder_enabled' => $validated['enabled'],
            'daily_reminder_time' => $validated['time'].':00',
        ])->save();

        return response()->json(['daily_reminder' => $setting->toApi()]);
    }
}
