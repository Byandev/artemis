<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkspaceUser extends Pivot
{
    use SoftDeletes;

    protected $table = 'workspace_user';

    public $incrementing = true;
    protected $keyType = 'int';
}