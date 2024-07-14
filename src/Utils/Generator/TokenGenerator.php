<?php

declare(strict_types=1);

namespace App\Utils\Generator;

use Exception;

class TokenGenerator
{
    /**
     * @throws Exception
     */
    public static function generateToken(): string
    {
        $token = random_bytes(16);

        return bin2hex($token);
    }
}
