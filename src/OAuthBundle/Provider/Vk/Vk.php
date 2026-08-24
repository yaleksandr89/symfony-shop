<?php

declare(strict_types=1);

namespace App\OAuthBundle\Provider\Vk;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

class Vk extends AbstractProvider
{
    use BearerAuthorizationTrait;

    protected string $version = '5.131';

    protected ?string $lang = '1';

    protected array $fields = ['id'];

    /**
     * {@inheritdoc}
     */
    public function getBaseAuthorizationUrl(): string
    {
        return 'https://oauth.vk.com/authorize';
    }

    public function getBaseAccessTokenUrl(array $params): string
    {
        return 'https://oauth.vk.com/access_token';
    }

    /**
     * {@inheritdoc}
     */
    public function getResourceOwnerDetailsUrl(AccessToken $token): string
    {
        $query = $this->buildQueryString([
            'fields' => $this->fields,
            'access_token' => $token->getToken(),
            'v' => $this->version,
            'lang' => $this->lang,
        ]);

        return "https://api.vk.com/method/users.get?$query";
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultScopes(): array
    {
        return ['offline', 'email'];
    }

    /**
     * {@inheritdoc}
     */
    protected function checkResponse(ResponseInterface $response, $data): void
    {
        $hasProviderError = is_array($data) && array_key_exists('error', $data);
        if (!$hasProviderError && $response->getStatusCode() < 400) {
            return;
        }

        $statusCode = $response->getStatusCode();
        throw new IdentityProviderException('VK OAuth request failed.', $statusCode >= 400 ? $statusCode : 0, ['status_code' => $statusCode]);
    }

    /**
     * {@inheritdoc}
     */
    protected function createResourceOwner(array $response, AccessToken $token): ResourceOwnerInterface
    {
        $data = [];

        if (array_key_exists('response', $response)) {
            foreach ($response['response'] as $key => $value) {
                $data = $response['response'][$key];
            }
        }

        $data = array_merge($data, $token->getValues());

        return new VkUser($data);
    }
}
