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
    ];

    protected $casts = [
        'enable_edit_previous_day' => 'boolean',
        'enable_bulk_status_update' => 'boolean',
        'enable_auto_tag_status' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
