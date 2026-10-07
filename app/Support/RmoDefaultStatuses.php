<?php

namespace App\Support;

use App\Models\Workspace;
use Modules\Pancake\Models\OrderForDeliveryCxStatus;
use Modules\Pancake\Models\OrderForDeliveryRiderStatus;

/**
 * The CX / rider statuses every workspace starts with: the old fixed RMO
 * statuses, split by who they're about. Workspaces rename or delete them freely
 * afterwards in Settings → RMO statuses.
 */
class RmoDefaultStatuses
{
    public const CX = [
        'PENDING',
        'CX CBR',
        'CX RINGING',
        'CX CALL ENDED',
        'AUTO DROP CX',
        'INCORRECT NUMBER',
        'RESCHEDULED',
        'CANCELLED',
    ];

    public const RIDER = [
        'PENDING',
        'RIDER OTW',
        'RIDER CBR',
        'RIDER RINGING',
        'RIDER CALL ENDED',
        'AUTO DROP RIDER',
        'WRONG SEGMENT CODE',
        'IN TRANSIT',
        'DELIVERED',
        'RETURNING',
    ];

    /**
     * The workspace's rider status called $name, by id — null once it has been
     * renamed or deleted, in which case callers tag nothing.
     */
    public static function riderStatusId(Workspace $workspace, string $name): ?int
    {
        return $workspace->rmoRiderStatuses()->where('name', $name)->value('id');
    }

    /**
     * Add whichever defaults the workspace is missing. Safe to re-run: a status
     * the workspace already has (or renamed back) is left alone.
     */
    public static function seed(Workspace $workspace): void
    {
        foreach ([OrderForDeliveryCxStatus::class => self::CX, OrderForDeliveryRiderStatus::class => self::RIDER] as $model => $names) {
            foreach ($names as $name) {
                $model::firstOrCreate(['workspace_id' => $workspace->id, 'name' => $name]);
            }
        }
    }
}
