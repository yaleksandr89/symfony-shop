<?php

declare(strict_types=1);

namespace App\Utils\Generator;

use Exception;

class TokenGenerator
{
    /**
     * @return string
     * @throws Exception
     */
    public static function generateToken(): string
    {
        // $token = openssl_random_pseudo_bytes(16);
        $token = random_bytes(16);
        return bin2hex($token);
    }
}