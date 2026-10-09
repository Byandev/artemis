<?php

namespace Modules\TaskManagement\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\TaskManagement\Enums\SpaceRole;

/**
 * A user seen through their membership of one space.
 *
 * @mixin User
 *
 * @property SpaceRole $space_role
 */
class SpaceMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->space_role->value,
        ];
    }
}
