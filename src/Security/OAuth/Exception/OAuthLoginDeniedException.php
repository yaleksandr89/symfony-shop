<?php

declare(strict_types=1);

namespace App\Security\OAuth\Exception;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

final class OAuthLoginDeniedException extends CustomUserMessageAuthenticationException
{
    public function __construct()
    {
        parent::__construct('OAuth authentication could not be completed.');
    }
}
