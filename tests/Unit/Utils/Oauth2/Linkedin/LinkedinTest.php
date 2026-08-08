<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utils\Oauth2\Linkedin;

use App\Utils\Oauth2\Linkedin\Linkedin;
use App\Utils\Oauth2\Linkedin\LinkedinUser;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

#[Group(name: 'unit')]
final class LinkedinTest extends TestCase
{
    public function testUsesCurrentOidcEndpointsScopesAndBearerUserInfoRequest(): void
    {
        $provider = new Linkedin([
            'clientId' => 'client-id',
            'clientSecret' => 'client-secret',
            'redirectUri' => 'https://app.example/check',
        ]);
        $authorizationUrl = $provider->getAuthorizationUrl(['state' => 'state']);
        parse_str((string) parse_url($authorizationUrl, PHP_URL_QUERY), $query);
        $token = new AccessToken(['access_token' => 'access-token']);
        $resourceUrl = $provider->getResourceOwnerDetailsUrl($token);
        $request = $provider->getAuthenticatedRequest('GET', $resourceUrl, $token);

        self::assertSame('https://www.linkedin.com/oauth/v2/authorization', $provider->getBaseAuthorizationUrl());
        self::assertSame('https://www.linkedin.com/oauth/v2/accessToken', $provider->getBaseAccessTokenUrl([]));
        self::assertSame('https://api.linkedin.com/v2/userinfo', $resourceUrl);
        self::assertSame('openid profile email', $query['scope']);
        self::assertSame('Bearer access-token', $request->getHeaderLine('Authorization'));
        self::assertStringNotContainsString('access-token', $resourceUrl);
        self::assertStringNotContainsString('/v2/me', $authorizationUrl.$resourceUrl);
        self::assertStringNotContainsString('r_liteprofile', $authorizationUrl);
        self::assertStringNotContainsString('r_emailaddress', $authorizationUrl);
    }

    public function testDefaultScopesAreExactlyOpenidProfileAndEmail(): void
    {
        $provider = new Linkedin();
        $method = new \ReflectionMethod($provider, 'getDefaultScopes');

        self::assertSame(['openid', 'profile', 'email'], $method->invoke($provider));
    }

    public function testCreatesLinkedinResourceOwnerFromSuccessfulResponse(): void
    {
        $provider = new Linkedin();
        $method = new \ReflectionMethod($provider, 'createResourceOwner');
        $user = $method->invoke($provider, ['sub' => 'LiNkEdIn-sub'], new AccessToken(['access_token' => 'token']));

        self::assertInstanceOf(LinkedinUser::class, $user);
        self::assertSame('LiNkEdIn-sub', $user->getId());
    }

    public function testSuccessfulResponsePassesWithoutException(): void
    {
        $provider = new Linkedin();
        $method = new \ReflectionMethod($provider, 'checkResponse');
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $method->invoke($provider, $response, ['sub' => 'subject']);

        self::assertTrue(true);
    }

    public function testProviderErrorIsSanitized(): void
    {
        $provider = new Linkedin();
        $method = new \ReflectionMethod($provider, 'checkResponse');
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(401);
        $secret = 'client-secret-not-for-output';
        $token = 'access-token-not-for-output';

        try {
            $method->invoke($provider, $response, [
                'error' => 'invalid_token',
                'error_description' => $secret.' '.$token,
            ]);
            self::fail('A LinkedIn provider error must throw.');
        } catch (IdentityProviderException $exception) {
            self::assertSame('LinkedIn OAuth request failed.', $exception->getMessage());
            self::assertSame(401, $exception->getCode());
            self::assertSame(['status_code' => 401], $exception->getResponseBody());
            self::assertStringNotContainsString($secret, $exception->getMessage());
            self::assertStringNotContainsString($token, $exception->getMessage());
        }
    }
}
