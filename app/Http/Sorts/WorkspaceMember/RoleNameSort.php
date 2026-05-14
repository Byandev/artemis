<?php

namespace App\Http\Sorts\WorkspaceMember;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class RoleNameSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): Builder
    {
        $direction = $descending ? 'DESC' : 'ASC';

        return $query
            ->orderByRaw("
                LOWER(COALESCE(
                    roles.name,
                    CASE workspace_user.role
                        WHEN 'owner' THEN 'Owner'
                        WHEN 'admin' THEN 'Admin'
                        WHEN 'member' THEN 'Member'
                        ELSE workspace_user.role
                    END,
                    ''
                )) {$direction}
            ")
            ->orderBy('users.name');
    }
}
