<?php

namespace Modules\GencysERP\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\GencysERP\Database\Factories\GencysPageFactory;

class GencysPage extends Model
{
    use HasFactory;

    protected $table = 'gencys_pages';

    protected $fillable = [
        'workspace_id',
        'page_id',
        'date_created',
        'name',
        'owner',
        'intern_and_brand',
        'gencys_intern_id',
        'status',
        'platform',
    ];

    protected $casts = [
        'page_id' => 'integer',
        'date_created' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function intern(): BelongsTo
    {
        return $this->belongsTo(Intern::class, 'gencys_intern_id');
    }

    protected static function newFactory(): GencysPageFactory
    {
        return GencysPageFactory::new();
    }
}
