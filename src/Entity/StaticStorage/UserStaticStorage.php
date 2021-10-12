<?php

declare(strict_types=1);

namespace App\Entity\StaticStorage;

final class UserStaticStorage
{
    public const USER_ROLE_USER = 'ROLE_USER';
    public const USER_ROLE_ADMIN = 'ROLE_ADMIN';
    public const USER_ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    /**
     * @return string[]
     */
    public static function getUserRolesChoices(): array
    {
        return [
            self::USER_ROLE_USER => 'User',
            self::USER_ROLE_ADMIN => 'Admin',
            self::USER_ROLE_SUPER_ADMIN => 'Super Admin',
        ];
    }

    /**
     * @return array
     */
    public static function getUserRoleHasAccessToAdminSection(): array
    {
        return [
            self::USER_ROLE_ADMIN,
            self::USER_ROLE_SUPER_ADMIN,
        ];
    }
}