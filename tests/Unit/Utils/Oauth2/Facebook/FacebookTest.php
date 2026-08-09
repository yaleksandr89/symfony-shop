<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utils\Oauth2\Facebook;

use App\Utils\Oauth2\Facebook\Facebook;
use App\Utils\Oauth2\Facebook\FacebookUser;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

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

    #[TestDox('Facebook resource owner создаётся через публичный HTTP-поток с нормализованным ID')]
    public function testCreatesFacebookResourceOwnerThroughPublicProviderFlow(): void
    {
        $provider = $this->providerWithResponse(new Response(200, ['Content-Type' => 'application/json'], '{"id":"  facebook-id  "}'));
        $user = $provider->getResourceOwner(new AccessToken(['access_token' => 'token']));

        self::assertInstanceOf(FacebookUser::class, $user);
        self::assertSame('facebook-id', $user->getId());
    }

    public function testMetaErrorPayloadThrowsIdentityProviderException(): void
    {
        $provider = $this->providerWithResponse(new Response(
            400,
            ['Content-Type' => 'application/json'],
            '{"error":{"message":"Request failed","code":190}}'
        ));

        $this->expectException(IdentityProviderException::class);
        $provider->getResourceOwner(new AccessToken(['access_token' => 'token']));
    }

    private function providerWithResponse(Response $response): Facebook
    {
        return new Facebook([], [
            'httpClient' => new Client(['handler' => new MockHandler([$response])]),
        ]);
    }
}
