<?php

declare(strict_types=1);

namespace App\Tests\Unit\OAuthBundle\Provider\Vk;

use App\OAuthBundle\Provider\Vk\Vk;
use App\OAuthBundle\Provider\Vk\VkUser;
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
final class VkTest extends TestCase
{
    #[TestDox('Ответ users.get и данные токена преобразуются во владельца VK без сети')]
    public function testCreatesVkResourceOwnerThroughPublicProviderFlow(): void
    {
        $provider = $this->providerWithResponse(new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'response' => [[
                'id' => 73,
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
            ]],
        ], JSON_THROW_ON_ERROR)));
        $user = $provider->getResourceOwner(new AccessToken([
            'access_token' => 'access-token',
            'user_id' => 73,
            'email' => 'ada@example.test',
        ]));

        self::assertInstanceOf(VkUser::class, $user);
        self::assertSame('73', $user->getId());
        self::assertSame('ada@example.test', $user->getEmail());
        self::assertSame('Ada Lovelace', $user->getFullName());
        self::assertSame([
            'id' => 73,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'user_id' => 73,
            'email' => 'ada@example.test',
        ], $user->toArray());
    }

    #[DataProvider('providerFailures')]
    #[TestDox('Ошибка VK очищается через публичный HTTP-поток без раскрытия ответа провайдера')]
    public function testSanitizesProviderFailureThroughPublicProviderFlow(
        Response $response,
        int $expectedCode,
        string $secretMarker,
        string $upstreamField,
    ): void
    {
        $provider = $this->providerWithResponse($response);

        try {
            $provider->getResourceOwner(new AccessToken(['access_token' => 'invalid-token']));
            self::fail('A failed VK response must throw an identity provider exception.');
        } catch (IdentityProviderException $exception) {
            self::assertSame('VK OAuth request failed.', $exception->getMessage());
            self::assertSame($expectedCode, $exception->getCode());
            self::assertSame(['status_code' => $response->getStatusCode()], $exception->getResponseBody());

            $safeResponse = serialize($exception->getResponseBody());
            self::assertStringNotContainsString($secretMarker, $exception->getMessage());
            self::assertStringNotContainsString($secretMarker, $safeResponse);
            self::assertStringNotContainsString($upstreamField, $safeResponse);
            self::assertStringNotContainsString('request_params', $safeResponse);
            self::assertStringNotContainsString('access_token', $safeResponse);
        }
    }

    /** @return iterable<string, array{Response, int, string, string}> */
    public static function providerFailures(): iterable
    {
        $logicalSecret = 'VK-LOGICAL-SECRET-7f3a';
        yield 'logical provider error with successful HTTP status' => [
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'error' => [
                    'error_code' => 5,
                    'error_msg' => 'User authorization failed: '.$logicalSecret,
                    'request_params' => [
                        ['key' => 'method', 'value' => 'users.get'],
                        ['key' => 'access_token', 'value' => 'access-'.$logicalSecret],
                    ],
                ],
                'access_token' => 'top-level-'.$logicalSecret,
            ], JSON_THROW_ON_ERROR)),
            0,
            $logicalSecret,
            'error_msg',
        ];

        $statusSecret = 'VK-STATUS-SECRET-19d4';
        yield 'HTTP error without provider error key' => [
            new Response(503, ['Content-Type' => 'application/json'], json_encode([
                'upstream_message' => 'Service unavailable: '.$statusSecret,
                'access_token' => 'access-'.$statusSecret,
            ], JSON_THROW_ON_ERROR)),
            503,
            $statusSecret,
            'upstream_message',
        ];
    }

    private function providerWithResponse(Response $response): Vk
    {
        return new Vk([], [
            'httpClient' => new Client(['handler' => new MockHandler([$response])]),
        ]);
    }
}
