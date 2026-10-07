<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RmoSetting extends Model
{
    protected $fillable = [
        'workspace_id',
        'enable_edit_previous_day',
        'enable_bulk_status_update',
        'enable_auto_tag_status',
        'enable_external_team_sync',
        'external_team_sheet_url',
        'external_team_status_map',
        'discord_daily_stats_enabled',
        'discord_webhook_url',
        'discord_send_at',
    ];

    protected $casts = [
        'enable_edit_previous_day' => 'boolean',
        'enable_bulk_status_update' => 'boolean',
        'enable_auto_tag_status' => 'boolean',
        'enable_external_team_sync' => 'boolean',
        'external_team_status_map' => 'array',
        'discord_daily_stats_enabled' => 'boolean',
    ];

    protected $attributes = [
        'discord_daily_stats_enabled' => false,
        'discord_send_at' => '18:00',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
