<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceMetricSetting extends Model
{
    protected $fillable = ['workspace_id', 'allowed_metrics', 'default_metrics'];

    protected $casts = [
        'allowed_metrics' => 'array',
        'default_metrics' => 'array',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
