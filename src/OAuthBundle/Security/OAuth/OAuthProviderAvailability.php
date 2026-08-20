<?php

declare(strict_types=1);

namespace App\OAuthBundle\Security\OAuth;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class OAuthProviderAvailability
{
    /**
     * @param array<string, bool>                                          $enabled
     * @param array<string, array{clientId: string, clientSecret: string}> $credentials
     */
    public function __construct(
        private readonly array $enabled,
        private readonly array $credentials,
    ) {
    }

    public function isEnabled(OAuthProvider $provider): bool
    {
        return true === ($this->enabled[$provider->value] ?? false);
    }

    public function assertEnabled(OAuthProvider $provider): void
    {
        if (!$this->isEnabled($provider)) {
            throw new NotFoundHttpException();
        }
    }

    public function assertOperational(OAuthProvider $provider): void
    {
        $this->assertEnabled($provider);

        if (!$provider->isImplemented()) {
            return;
        }

        $credentials = $this->credentials[$provider->value] ?? [];
        $clientId = trim((string) ($credentials['clientId'] ?? ''));
        $clientSecret = trim((string) ($credentials['clientSecret'] ?? ''));

        if ('' === $clientId || '' === $clientSecret) {
            throw new OAuthProviderConfigurationException($provider);
        }
    }

    /** @return list<OAuthProvider> */
    public function enabledProviders(): array
    {
        return array_values(array_filter(
            OAuthProvider::cases(),
            fn (OAuthProvider $provider): bool => $this->isEnabled($provider)
        ));
    }
}
