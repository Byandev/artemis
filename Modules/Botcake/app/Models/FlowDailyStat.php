<?php

namespace Modules\Botcake\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function flow(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Flow::class);
    }
}
