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
        if (!is_array($data) || empty($data['error'])) {
            return;
        }

        $error = $data['error'];
        $message = is_array($error) ? (string) ($error['message'] ?? 'Facebook OAuth request failed.') : (string) $error;
        $code = is_array($error) && is_numeric($error['code'] ?? null) ? (int) $error['code'] : 0;

        throw new IdentityProviderException($message, $code, $data);
    }

    protected function createResourceOwner(array $response, AccessToken $token): ResourceOwnerInterface
    {
        return new FacebookUser($response);
    }
}
