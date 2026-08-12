<?php

declare(strict_types=1);

namespace App\Security\Authenticator\Front;

use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;

abstract class AbstractOAuthAuthenticator extends OAuth2Authenticator
{
    private const EXTERNAL_FAILURE_MESSAGE = 'OAuth provider request failed.';

    protected function fetchAccessTokenFromProvider(OAuth2ClientInterface $client): AccessToken
    {
        try {
            return $this->fetchAccessToken($client);
        } catch (AuthenticationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new AuthenticationServiceException(self::EXTERNAL_FAILURE_MESSAGE, 0, $exception);
        }
    }

    protected function fetchResourceOwnerFromProvider(
        OAuth2ClientInterface $client,
        AccessToken $accessToken,
    ): ResourceOwnerInterface {
        try {
            return $client->fetchUserFromToken($accessToken);
        } catch (AuthenticationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new AuthenticationServiceException(self::EXTERNAL_FAILURE_MESSAGE, 0, $exception);
        }
    }
}
