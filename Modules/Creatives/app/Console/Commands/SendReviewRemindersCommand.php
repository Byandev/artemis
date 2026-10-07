<?php

namespace Modules\Creatives\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\Creatives\Models\Creative;
use Modules\Creatives\Models\PushToken;
use Modules\Creatives\Models\ReminderSetting;
use Modules\Creatives\Services\ExpoPush;

/**
 * Daily push to every Creatives Tracker user who still has creatives waiting
 * on their review, at the time they picked in the app (9:00 AM Manila by
 * default). Runs every minute; users with nothing pending get nothing.
 */
class SendReviewRemindersCommand extends Command
{
    /**
     * A reminder still goes out this many minutes after its time, so a late or
     * skipped scheduler run doesn't drop that day's reminder.
     */
    private const GRACE_MINUTES = 30;

    protected $signature = 'creatives:send-review-reminders';

    protected $description = 'Push a reminder to reviewers with creatives waiting on them';

    public function handle(ExpoPush $push): int
    {
        $now = Carbon::now(ReminderSetting::TIMEZONE);
        $today = $now->toDateString();
        $userIds = PushToken::distinct()->pluck('user_id');
        $settings = ReminderSetting::whereIn('user_id', $userIds)->get()->keyBy('user_id');
        $messages = [];

        User::whereIn('id', $userIds)->each(function (User $user) use ($settings, $now, $today, &$messages) {
            $setting = $settings->get($user->id) ?? new ReminderSetting(['user_id' => $user->id]);

            if (! $this->isDue($setting, $now, $today)) {
                return;
            }

            // Mark the day as handled even when nothing is pending, so a creative
            // assigned later in the grace window doesn't trigger a second reminder.
            $setting->last_reminded_on = $today;
            $setting->save();

            $count = Creative::awaitingReviewBy($user)->count();

            if ($count === 0) {
                return;
            }

            $noun = $count === 1 ? 'creative' : 'creatives';

            foreach (PushToken::where('user_id', $user->id)->pluck('token') as $token) {
                $messages[] = [
                    'to' => $token,
                    'title' => 'Creatives waiting for review',
                    'body' => "You have {$count} {$noun} waiting for your review.",
                    'sound' => 'default',
                    'channelId' => 'reviews',
                    'data' => ['type' => 'pending_reminder', 'count' => $count],
                ];
            }
        });

        $push->send($messages);

        $this->info('Sent '.count($messages).' reminder(s).');

        return self::SUCCESS;
    }

    private function isDue(ReminderSetting $setting, Carbon $now, string $today): bool
    {
        if (! $setting->daily_reminder_enabled || $setting->last_reminded_on?->toDateString() === $today) {
            return false;
        }

        [$hour, $minute] = array_map('intval', explode(':', $setting->time()));
        $minutesLate = ($now->hour * 60 + $now->minute) - ($hour * 60 + $minute);

        return $minutesLate >= 0 && $minutesLate < self::GRACE_MINUTES;
    }
}
