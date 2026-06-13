<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PageAccessGrant extends Model
{
    protected $fillable = [
        'workspace_id',
        'grantee_type',
        'grantee_id',
        'page_id',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * The User or Team this grant belongs to.
     */
    public function grantee(): MorphTo
    {
        return $this->morphTo();
    }
}
