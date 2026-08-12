<?php

declare(strict_types=1);

namespace App\Twig;

use App\Security\OAuth\OAuthProvider;
use App\Security\OAuth\OAuthProviderAvailability;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class OAuthProviderTwigExtension extends AbstractExtension
{
    public function __construct(private readonly OAuthProviderAvailability $availability)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('oauth_provider_enabled', $this->isProviderEnabled(...)),
        ];
    }

    public function isProviderEnabled(mixed $providerKey): bool
    {
        return is_string($providerKey)
            && null !== ($provider = OAuthProvider::tryFrom($providerKey))
            && $this->availability->isEnabled($provider);
    }
}
