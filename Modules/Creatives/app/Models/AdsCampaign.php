<?php

namespace Modules\Creatives\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdsCampaign extends Model
{
    protected $table = 'creatives_ads_campaigns';

    protected $guarded = [];

    public function creative(): BelongsTo
    {
        return $this->belongsTo(Creative::class);
    }
}
