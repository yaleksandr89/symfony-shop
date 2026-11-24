<?php

declare(strict_types=1);

namespace App\OAuthBundle\Provider\Linkedin;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

final class Linkedin extends AbstractProvider
{
    use BearerAuthorizationTrait;

    public function getBaseAuthorizationUrl(): string
    {
        return 'https://www.linkedin.com/oauth/v2/authorization';
    }

    /** @param array<string, mixed> $params */
    public function getBaseAccessTokenUrl(array $params): string
    {
        return 'https://www.linkedin.com/oauth/v2/accessToken';
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token): string
    {
        return 'https://api.linkedin.com/v2/userinfo';
    }

    /** @return list<string> */
    protected function getDefaultScopes(): array
    {
        return ['openid', 'profile', 'email'];
    }

    protected function getScopeSeparator(): string
    {
        return ' ';
    }

    /** @param array<string, mixed>|string|null $data */
    protected function checkResponse(ResponseInterface $response, $data): void
    {
        $hasProviderError = is_array($data) && array_key_exists('error', $data);
        if (!$hasProviderError && $response->getStatusCode() < 400) {
            return;
        }

        $statusCode = $response->getStatusCode();
        throw new IdentityProviderException('LinkedIn OAuth request failed.', $statusCode >= 400 ? $statusCode : 0, ['status_code' => $statusCode]);
    }

    /** @param array<string, mixed> $response */
    protected function createResourceOwner(array $response, AccessToken $token): ResourceOwnerInterface
    {
        return new LinkedinUser($response);
    }
}
