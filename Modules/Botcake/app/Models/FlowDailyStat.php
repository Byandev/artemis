<?php

namespace Modules\Botcake\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlowDailyStat extends Model
{
    protected $table = 'botcake_flow_daily_stats';

    protected $fillable = [
        'flow_id',
        'date',
        'delivery',
        'is_clicked',
        'seen',
        'sent',
        'total_phone_number',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class);
    }
}
