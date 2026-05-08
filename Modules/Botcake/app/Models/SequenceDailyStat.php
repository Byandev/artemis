<?php

namespace Modules\Botcake\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SequenceDailyStat extends Model
{
    protected $table = 'botcake_sequence_daily_stats';

    protected $fillable = [
        'sequence_id',
        'date',
        'delivery',
        'seen',
        'sent',
        'total_phone_number',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class);
    }
}
