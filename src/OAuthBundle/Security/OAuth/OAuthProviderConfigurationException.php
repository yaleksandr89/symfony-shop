<?php

declare(strict_types=1);

namespace App\OAuthBundle\Security\OAuth;

final class OAuthProviderConfigurationException extends \RuntimeException
{
    public function __construct(OAuthProvider $provider)
    {
        parent::__construct(sprintf('OAuth provider "%s" is enabled but not configured.', $provider->value));
    }
}
