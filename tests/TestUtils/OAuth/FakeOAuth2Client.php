<?php

declare(strict_types=1);

namespace App\Tests\TestUtils\OAuth;

use KnpU\OAuth2ClientBundle\Client\OAuth2Client;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

final class FakeOAuth2Client implements OAuth2ClientInterface
{
    public int $redirectCalls = 0;
    public int $registryAccesses = 0;
    public int $tokenRequests = 0;
    public int $userInfoRequests = 0;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly FakeOAuthResourceOwner $resourceOwner,
        private readonly string $state = 'fake-oauth-state',
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

        return new AccessToken(['access_token' => 'fake-token']);
    }

    public function fetchUserFromToken(AccessToken $accessToken): FakeOAuthResourceOwner
    {
        ++$this->userInfoRequests;

        return $this->resourceOwner;
    }

    public function fetchUser(): FakeOAuthResourceOwner
    {
        return $this->fetchUserFromToken($this->getAccessToken());
    }

    public function getOAuth2Provider(): AbstractProvider
    {
        throw new \LogicException('The fake has no provider.');
    }
}
