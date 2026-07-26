<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RmoSetting extends Model
{
    protected $fillable = ['workspace_id', 'enable_edit_previous_day'];

    protected $casts = [
        'enable_edit_previous_day' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
