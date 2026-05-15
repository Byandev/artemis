<?php

namespace App\Models;

use App\Traits\LogsActivityForWorkspace;
use Illuminate\Database\Eloquent\Relations\Pivot;

class WorkspaceUser extends Pivot
{
    use LogsActivityForWorkspace;

    protected $table = 'workspace_user';

    public $incrementing = true;

    protected $keyType = 'int';
}
