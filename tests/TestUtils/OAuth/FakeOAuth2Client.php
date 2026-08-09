<?php

declare(strict_types=1);

namespace App\Tests\TestUtils\OAuth;

use KnpU\OAuth2ClientBundle\Client\OAuth2Client;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

final class FakeOAuth2Client implements OAuth2ClientInterface
{
    public const TOKEN_FAILURE_MARKER = 'OAUTH-TOKEN-UPSTREAM-SECRET-7ac4';
    public const USER_INFO_FAILURE_MARKER = 'OAUTH-USERINFO-UPSTREAM-SECRET-91ef';

    public int $redirectCalls = 0;
    public int $registryAccesses = 0;
    public int $tokenRequests = 0;
    public int $userInfoRequests = 0;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ResourceOwnerInterface $resourceOwner,
        private readonly string $state = 'fake-oauth-state',
        private readonly bool $failTokenRequest = false,
        private readonly bool $failUserInfoRequest = false,
    ) {
    }

    public function setAsStateless(): void
    {
    }

    public function redirect(array $scopes = [], array $options = []): RedirectResponse
    {
        ++$this->redirectCalls;
        $this->requestStack->getCurrentRequest()?->getSession()->set(OAuth2Client::OAUTH2_SESSION_STATE_KEY, $this->state);

        return new RedirectResponse('https://provider.example/authorize?state='.$this->state);
    }

    public function getAccessToken(array $options = []): AccessToken
    {
        ++$this->tokenRequests;
        $request = $this->requestStack->getCurrentRequest();
        $expectedState = $request?->getSession()->get(OAuth2Client::OAUTH2_SESSION_STATE_KEY);
        $actualState = $request?->query->get('state');
        if ($expectedState !== $actualState) {
            throw new \RuntimeException('Invalid state.');
        }
        if ($this->failTokenRequest) {
            throw new \RuntimeException('Token exchange exposed '.self::TOKEN_FAILURE_MARKER);
        }

        return new AccessToken(['access_token' => 'fake-token']);
    }

    public function fetchUserFromToken(AccessToken $accessToken): ResourceOwnerInterface
    {
        ++$this->userInfoRequests;
        if ($this->failUserInfoRequest) {
            throw new \RuntimeException('Resource owner fetch exposed '.self::USER_INFO_FAILURE_MARKER);
        }

        return $this->resourceOwner;
    }

    public function fetchUser(): ResourceOwnerInterface
    {
        return $this->fetchUserFromToken($this->getAccessToken());
    }

    public function getOAuth2Provider(): AbstractProvider
    {
        throw new \LogicException('The fake has no provider.');
    }
}
