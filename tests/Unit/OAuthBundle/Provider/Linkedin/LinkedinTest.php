<?php

declare(strict_types=1);

namespace App\Tests\Unit\OAuthBundle\Provider\Linkedin;

use App\OAuthBundle\Provider\Linkedin\Linkedin;
use App\OAuthBundle\Provider\Linkedin\LinkedinUser;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Group(name: 'unit')]
final class LinkedinTest extends TestCase
{
    #[TestDox('Использует актуальные конечные точки OIDC, области и Bearer-запрос данных пользователя')]
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

    #[TestDox('Владелец ресурса LinkedIn создаётся через публичный HTTP-поток без изменения регистра subject')]
    public function testCreatesLinkedinResourceOwnerThroughPublicProviderFlow(): void
    {
        $provider = $this->providerWithResponse(new Response(
            200,
            ['Content-Type' => 'application/json'],
            '{"sub":"LiNkEdIn-sub"}'
        ));
        $user = $provider->getResourceOwner(new AccessToken(['access_token' => 'token']));

        self::assertInstanceOf(LinkedinUser::class, $user);
        self::assertSame('LiNkEdIn-sub', $user->getId());
    }

    #[TestDox('Ошибка LinkedIn через публичный HTTP-поток не раскрывает данные провайдера')]
    public function testProviderErrorIsSanitized(): void
    {
        $secret = 'client-secret-not-for-output';
        $token = 'access-token-not-for-output';
        $provider = $this->providerWithResponse(new Response(
            401,
            ['Content-Type' => 'application/json'],
            json_encode([
                'error' => 'invalid_token',
                'error_description' => $secret.' '.$token,
            ], JSON_THROW_ON_ERROR)
        ));

        try {
            $provider->getResourceOwner(new AccessToken(['access_token' => 'token']));
            self::fail('A LinkedIn provider error must throw.');
        } catch (IdentityProviderException $exception) {
            self::assertSame('LinkedIn OAuth request failed.', $exception->getMessage());
            self::assertSame(401, $exception->getCode());
            self::assertSame(['status_code' => 401], $exception->getResponseBody());
            self::assertStringNotContainsString($secret, $exception->getMessage());
            self::assertStringNotContainsString($token, $exception->getMessage());
        }
    }

    private function providerWithResponse(Response $response): Linkedin
    {
        return new Linkedin([], [
            'httpClient' => new Client(['handler' => new MockHandler([$response])]),
        ]);
    }
}
