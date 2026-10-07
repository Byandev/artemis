<?php

namespace Modules\Creatives\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * When a Creatives Tracker user gets the daily "creatives waiting" push.
 * Users without a row get the defaults: on, at 9:00 AM Manila time.
 */
class ReminderSetting extends Model
{
    public const TIMEZONE = 'Asia/Manila';

    public const DEFAULT_TIME = '09:00';

    protected $table = 'creatives_tracker_reminder_settings';

    protected $guarded = [];

    protected $attributes = [
        'daily_reminder_enabled' => true,
        'daily_reminder_time' => self::DEFAULT_TIME.':00',
    ];

    protected $casts = [
        'daily_reminder_enabled' => 'boolean',
        'last_reminded_on' => 'date:Y-m-d',
    ];

    public static function for(User $user): self
    {
        return static::firstOrNew(['user_id' => $user->id]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "09:00:00" → "09:00" */
    public function time(): string
    {
        return substr((string) $this->daily_reminder_time, 0, 5);
    }

    public function toApi(): array
    {
        return [
            'enabled' => $this->daily_reminder_enabled,
            'time' => $this->time(),
            'timezone' => self::TIMEZONE,
        ];
    }
}
