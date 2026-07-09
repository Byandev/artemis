<?php

namespace Modules\GencysERP\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\GencysERP\Database\Factories\InternFactory;

class Intern extends Model
{
    use HasFactory;

    protected $table = 'gencys_interns';

    protected $fillable = [
        'workspace_id',
        'intern_id',
        'full_name',
        'company_name',
        'username',
        'contact_number',
        'email',
    ];

    protected $casts = [
        'intern_id' => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    protected static function newFactory(): InternFactory
    {
        return InternFactory::new();
    }
}
