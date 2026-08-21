<?php

declare(strict_types=1);

namespace App\Account\User;

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
            self::USER_ROLE_USER => 'user.role.user',
            self::USER_ROLE_ADMIN => 'user.role.admin',
            self::USER_ROLE_SUPER_ADMIN => 'user.role.super_admin',
        ];
    }

    public static function getUserRoleHasAccessToAdminSection(): array
    {
        return [
            self::USER_ROLE_ADMIN,
            self::USER_ROLE_SUPER_ADMIN,
        ];
    }
}
