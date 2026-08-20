<?php

declare(strict_types=1);

namespace App\Tests\Unit\OAuthBundle\Factory;

use App\OAuthBundle\Factory\UserFactory;
use App\OAuthBundle\Provider\Facebook\FacebookUser;
use App\OAuthBundle\Provider\Linkedin\LinkedinUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Yaleksandr\OAuth2\Client\Provider\YandexResourceOwner;

#[Group(name: 'unit')]
final class UserFactoryTest extends TestCase
{
    #[DataProvider('names')]
    #[TestDox('Создание пользователя Яндекса использует проверенные email, ID и запасные имена')]
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

    #[TestDox('Создание пользователя Facebook использует проверенные email, имя и внешний ID')]
    public function testCreateUserFromFacebookUsesValidatedEmailNameAndExternalId(): void
    {
        $user = UserFactory::createUserFromFacebook(
            new FacebookUser(['id' => 'facebook-id', 'name' => 'Facebook User']),
            'user@example.test'
        );

        self::assertSame('user@example.test', $user->getEmail());
        self::assertSame('Facebook User', $user->getFullName());
        self::assertSame('facebook-id', $user->getFacebookId());
        self::assertFalse($user->isVerified());
    }

    #[TestDox('Создание пользователя LinkedIn оставляет локальную верификацию выключенной')]
    public function testCreateUserFromLinkedinKeepsLocalVerificationFalse(): void
    {
        $user = UserFactory::createUserFromLinkedin(
            new LinkedinUser([
                'sub' => 'LiNkEdIn-sub',
                'name' => 'LinkedIn User',
                'email_verified' => true,
            ]),
            'user@example.test',
        );

        self::assertSame('user@example.test', $user->getEmail());
        self::assertSame('LinkedIn User', $user->getFullName());
        self::assertSame('LiNkEdIn-sub', $user->getLinkedinId());
        self::assertFalse($user->isVerified());
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
