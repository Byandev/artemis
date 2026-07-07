<?php

namespace Modules\GencysERP\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GencysInternDailyRecord extends Model
{
    protected $table = 'gencys_intern_daily_records';

    protected $fillable = [
        'workspace_id',
        'gencys_intern_id',
        'record_date',
        'sales',
        'roas',
        'ad_spent',
        'rts_rate',
        'rts_amount',
    ];

    protected $casts = [
        'record_date' => 'date',
        'sales' => 'decimal:2',
        'roas' => 'decimal:2',
        'ad_spent' => 'decimal:2',
        'rts_rate' => 'decimal:2',
        'rts_amount' => 'decimal:2',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function intern(): BelongsTo
    {
        return $this->belongsTo(GencysIntern::class, 'gencys_intern_id');
    }
}
