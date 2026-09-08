<?php

namespace Modules\MetaAds\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-workspace Discord settings for the MetaAds reports. Mirrors
 * InventoryNotificationSetting: each report owns an enabled flag, its own
 * webhook, and a whole-hour send time that the hourly command matches against.
 */
class NotificationSetting extends Model
{
    protected $table = 'meta_ads_notification_settings';

    protected $fillable = [
        'workspace_id',
        'inactive_accounts_enabled',
        'inactive_accounts_webhook_url',
        'inactive_accounts_send_at',
    ];

    protected $casts = [
        'inactive_accounts_enabled' => 'boolean',
    ];

    protected $attributes = [
        'inactive_accounts_enabled' => true,
        'inactive_accounts_send_at' => '09:00',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public static function forWorkspace(int $workspaceId): self
    {
        return static::firstOrNew(['workspace_id' => $workspaceId]);
    }
}
