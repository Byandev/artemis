<?php

namespace Modules\Botcake\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SequenceMessageDailyStat extends Model
{
    protected $table = 'botcake_sequence_message_daily_stats';

    protected $fillable = [
        'sequence_message_id',
        'date',
        'delivery',
        'seen',
        'sent',
        'total_phone_number',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function sequenceMessage(): BelongsTo
    {
        return $this->belongsTo(SequenceMessage::class);
    }
}
