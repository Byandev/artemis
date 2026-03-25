<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'roles';

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'workspace_id',
        'name',
        'description',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }
}
