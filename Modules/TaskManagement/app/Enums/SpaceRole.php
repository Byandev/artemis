<?php

namespace Modules\TaskManagement\Enums;

enum SpaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';

    /**
     * Determine whether the role includes every ability of the given role.
     */
    public function atLeast(self $role): bool
    {
        return $this->rank() >= $role->rank();
    }

    /**
     * Get the ordered weight of the role.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Owner => 40,
            self::Admin => 30,
            self::Member => 20,
            self::Viewer => 10,
        };
    }

    /**
     * Get every role value.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
