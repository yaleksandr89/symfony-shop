<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utils\Factory;

use App\Utils\Factory\UserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yaleksandr\OAuth2\Client\Provider\YandexResourceOwner;

#[Group(name: 'unit')]
final class UserFactoryTest extends TestCase
{
    #[DataProvider('names')]
    public function testCreateUserFromYandexUsesValidatedEmailIdAndNameFallbacks(?string $realName, ?string $displayName, string $login, string $expectedName): void
    {
        $user = UserFactory::createUserFromYandex($this->yandexUser($realName, $displayName, $login), 'user@example.test');

        self::assertSame('user@example.test', $user->getEmail());
        self::assertSame('yandex-id', $user->getYandexId());
        self::assertSame($expectedName, $user->getFullName());
    }

    public static function names(): iterable
    {
        yield 'real name' => ['Real Name', 'Display Name', 'login', 'Real Name'];
        yield 'display name fallback' => [null, 'Display Name', 'login', 'Display Name'];
        yield 'login fallback' => [null, null, 'login', 'login'];
    }

    private function yandexUser(?string $realName, ?string $displayName, string $login): YandexResourceOwner
    {
        return new YandexResourceOwner(array_filter([
            'id' => 'yandex-id',
            'login' => $login,
            'client_id' => 'client-id',
            'psuid' => 'psuid',
            'real_name' => $realName,
            'display_name' => $displayName,
        ], static fn (?string $value): bool => null !== $value));
    }
}
