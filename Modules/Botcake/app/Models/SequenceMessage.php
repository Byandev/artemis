<?php

namespace Modules\Botcake\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SequenceMessage extends Model
{
    protected $guarded = [];

    protected $table = 'botcake_sequence_messages';

    public $incrementing = false;

    protected $keyType = 'int';

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class);
    }

    public function dailyStats(): HasMany
    {
        return $this->hasMany(SequenceMessageDailyStat::class);
    }
}
