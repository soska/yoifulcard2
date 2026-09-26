<?php

namespace App\Enums;

enum MembershipRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Employee = 'employee';

    /**
     * Whether this role may edit organization and program settings.
     */
    public function canManageSettings(): bool
    {
        return $this === self::Owner || $this === self::Manager;
    }
}
