<?php

declare(strict_types=1);

namespace App\Commerce\Cart;

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
