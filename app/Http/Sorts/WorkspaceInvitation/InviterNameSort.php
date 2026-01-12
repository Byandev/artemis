<?php

namespace App\Http\Sorts\WorkspaceInvitation;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class InviterNameSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): Builder
    {
        $direction = $descending ? 'DESC' : 'ASC';

        return $query
            ->join('users as inviters', 'inviters.id', '=', 'workspace_invitations.invited_by')
            ->select('workspace_invitations.*')
            ->orderBy('inviters.name', $direction);
    }
}
