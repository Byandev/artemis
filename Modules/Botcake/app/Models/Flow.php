<?php

namespace Modules\Botcake\Models;

use App\Models\Page;
use Illuminate\Database\Eloquent\Model;

class Flow extends Model
{
    protected $guarded = [];

    protected $table = 'botcake_flows';

    public function page(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
