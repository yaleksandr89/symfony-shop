<?php

declare(strict_types=1);

namespace App\Tests\Unit\OAuthBundle\Provider\Facebook;

use App\OAuthBundle\Provider\Facebook\Facebook;
use App\OAuthBundle\Provider\Facebook\FacebookUser;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Group(name: 'unit')]
final class FacebookTest extends TestCase
{
    #[TestDox('Использует только версионированные конечные точки Meta и область email')]
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

    #[TestDox('Владелец ресурса Facebook создаётся через публичный HTTP-поток с нормализованным ID')]
    public function testCreatesFacebookResourceOwnerThroughPublicProviderFlow(): void
    {
        $provider = $this->providerWithResponse(new Response(200, ['Content-Type' => 'application/json'], '{"id":"  facebook-id  "}'));
        $user = $provider->getResourceOwner(new AccessToken(['access_token' => 'token']));

        self::assertInstanceOf(FacebookUser::class, $user);
        self::assertSame('facebook-id', $user->getId());
    }

    #[DataProvider('providerFailures')]
    #[TestDox('Ошибка Facebook очищается через публичный HTTP-поток без раскрытия ответа провайдера')]
    public function testSanitizesProviderFailureThroughPublicProviderFlow(
        Response $response,
        int $expectedCode,
        string $secretMarker,
    ): void
    {
        $provider = $this->providerWithResponse($response);

        try {
            $provider->getResourceOwner(new AccessToken(['access_token' => 'token']));
            self::fail('A failed Facebook response must throw an identity provider exception.');
        } catch (IdentityProviderException $exception) {
            self::assertSame('Facebook OAuth request failed.', $exception->getMessage());
            self::assertSame($expectedCode, $exception->getCode());
            self::assertSame(['status_code' => $response->getStatusCode()], $exception->getResponseBody());

            $safeResponse = serialize($exception->getResponseBody());
            self::assertStringNotContainsString($secretMarker, $exception->getMessage());
            self::assertStringNotContainsString($secretMarker, $safeResponse);
            self::assertStringNotContainsString('error_description', $safeResponse);
            self::assertStringNotContainsString('access_token', $safeResponse);
        }
    }

    /** @return iterable<string, array{Response, int, string}> */
    public static function providerFailures(): iterable
    {
        $payloadSecret = 'FACEBOOK-TOKEN-SECRET-7f3a';
        yield 'provider payload with HTTP error' => [
            new Response(400, ['Content-Type' => 'application/json'], json_encode([
                'error' => [
                    'message' => $payloadSecret,
                    'code' => 190,
                ],
                'error_description' => 'description-'.$payloadSecret,
                'access_token' => 'access-'.$payloadSecret,
            ], \JSON_THROW_ON_ERROR)),
            400,
            $payloadSecret,
        ];

        $logicalSecret = 'FACEBOOK-LOGICAL-SECRET-6b21';
        yield 'provider payload with successful HTTP status' => [
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'error' => ['message' => $logicalSecret],
            ], \JSON_THROW_ON_ERROR)),
            0,
            $logicalSecret,
        ];

        $statusSecret = 'FACEBOOK-STATUS-SECRET-19d4';
        yield 'HTTP error without provider error key' => [
            new Response(503, ['Content-Type' => 'application/json'], json_encode([
                'error_description' => $statusSecret,
                'access_token' => 'access-'.$statusSecret,
            ], \JSON_THROW_ON_ERROR)),
            503,
            $statusSecret,
        ];
    }

    private function providerWithResponse(Response $response): Facebook
    {
        return new Facebook([], [
            'httpClient' => new Client(['handler' => new MockHandler([$response])]),
        ]);
    }
}
