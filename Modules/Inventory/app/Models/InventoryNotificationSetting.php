<?php

namespace Modules\Inventory\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryNotificationSetting extends Model
{
    protected $table = 'inventory_notification_settings';

    protected $fillable = [
        'workspace_id',
        'discord_webhook_url',
        'deliveries_enabled',
        'deliveries_send_at',
        'awaiting_enabled',
        'awaiting_send_at',
    ];

    protected $casts = [
        'deliveries_enabled' => 'boolean',
        'awaiting_enabled' => 'boolean',
    ];

    /**
     * Defaults applied to a brand-new (unsaved) settings instance, so a workspace
     * that has never configured notifications still behaves sensibly.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'deliveries_enabled' => true,
        'deliveries_send_at' => '17:00',
        'awaiting_enabled' => true,
        'awaiting_send_at' => '10:00',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * The workspace's settings row, or a fresh unsaved instance carrying the
     * defaults above. Never returns null, so callers can read fields directly.
     */
    public static function forWorkspace(int $workspaceId): self
    {
        return static::firstOrNew(['workspace_id' => $workspaceId]);
    }
}
