<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utils\Oauth2\Facebook;

use App\Utils\Oauth2\Facebook\Facebook;
use App\Utils\Oauth2\Facebook\FacebookUser;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

#[Group(name: 'unit')]
final class FacebookTest extends TestCase
{
    public function testUsesOnlyVersionedMetaEndpointsAndEmailScope(): void
    {
        $provider = new Facebook(['clientId' => 'client-id', 'clientSecret' => 'client-secret', 'redirectUri' => 'https://app.example/check']);
        $authorizationUrl = $provider->getAuthorizationUrl();
        $resourceUrl = $provider->getResourceOwnerDetailsUrl(new AccessToken(['access_token' => 'token']));

        self::assertStringStartsWith('https://www.facebook.com/v26.0/dialog/oauth?', $authorizationUrl);
        self::assertStringContainsString('scope=email', $authorizationUrl);
        self::assertSame('https://graph.facebook.com/v26.0/oauth/access_token', $provider->getBaseAccessTokenUrl([]));
        self::assertSame('https://graph.facebook.com/v26.0/me?fields=id%2Cname%2Cemail', $resourceUrl);
        self::assertStringNotContainsString('access_token', $resourceUrl);
        self::assertStringNotContainsString('graph.facebook.com/me', $resourceUrl);
    }

    public function testCreatesFacebookResourceOwner(): void
    {
        $provider = new Facebook();
        $method = new \ReflectionMethod($provider, 'createResourceOwner');
        $user = $method->invoke($provider, ['id' => 'facebook-id'], new AccessToken(['access_token' => 'token']));

        self::assertInstanceOf(FacebookUser::class, $user);
    }

    public function testMetaErrorPayloadThrowsIdentityProviderException(): void
    {
        $provider = new Facebook();
        $method = new \ReflectionMethod($provider, 'checkResponse');

        $this->expectException(IdentityProviderException::class);
        $method->invoke($provider, $this->createStub(ResponseInterface::class), ['error' => ['message' => 'Request failed', 'code' => 190]]);
    }
}
