<?php

declare(strict_types=1);

namespace App\Utils\Oauth2\Facebook;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

final class Facebook extends AbstractProvider
{
    use BearerAuthorizationTrait;

    private const GRAPH_API_VERSION = 'v26.0';

    public function getBaseAuthorizationUrl(): string
    {
        return 'https://www.facebook.com/'.self::GRAPH_API_VERSION.'/dialog/oauth';
    }

    public function getBaseAccessTokenUrl(array $params): string
    {
        return 'https://graph.facebook.com/'.self::GRAPH_API_VERSION.'/oauth/access_token';
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token): string
    {
        return 'https://graph.facebook.com/'.self::GRAPH_API_VERSION.'/me?'.$this->buildQueryString([
            'fields' => 'id,name,email',
        ]);
    }

    protected function getDefaultScopes(): array
    {
        return ['email'];
    }

    protected function checkResponse(ResponseInterface $response, $data): void
    {
        $hasProviderError = is_array($data) && array_key_exists('error', $data);
        if (!$hasProviderError && $response->getStatusCode() < 400) {
            return;
        }

        $statusCode = $response->getStatusCode();
        throw new IdentityProviderException('Facebook OAuth request failed.', $statusCode >= 400 ? $statusCode : 0, ['status_code' => $statusCode]);
    }

    protected function createResourceOwner(array $response, AccessToken $token): ResourceOwnerInterface
    {
        return new FacebookUser($response);
    }
}
