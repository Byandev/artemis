<?php

namespace Modules\Creatives\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreativeReview extends Model
{
    protected $table = 'creatives_reviews';

    protected $guarded = [];

    public function creative(): BelongsTo
    {
        return $this->belongsTo(Creative::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
