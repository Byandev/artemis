<?php

namespace Modules\Botcake\Models;

use Illuminate\Database\Eloquent\Model;

class SequenceMessage extends Model
{
    protected $guarded = [];

    protected $table = 'botcake_sequence_messages';

    public function sequence(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Sequence::class);
    }

    public function dailyStats(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SequenceMessageDailyStat::class);
    }
}
