<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Pancake\Models\User as PancakeUser;

class CsrSchedule extends Model
{
    protected $fillable = [
        'workspace_id',
        'pancake_user_id',
        'date',
        'shift_start',
        'shift_end',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'shift_start' => 'datetime:H:i',
            'shift_end' => 'datetime:H:i',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function pancakeUser(): BelongsTo
    {
        return $this->belongsTo(PancakeUser::class, 'pancake_user_id');
    }
}
