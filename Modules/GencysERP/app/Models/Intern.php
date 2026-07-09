<?php

namespace Modules\GencysERP\Models;

use App\Models\User;
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
        'user_id',
        'intern_id',
        'full_name',
        'company_name',
        'username',
        'contact_number',
        'email',
        'is_active',
    ];

    protected $casts = [
        'intern_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory(): InternFactory
    {
        return InternFactory::new();
    }
}
