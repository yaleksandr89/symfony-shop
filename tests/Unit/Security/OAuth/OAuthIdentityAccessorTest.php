<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\OAuth;

use App\Entity\User;
use App\Security\OAuth\OAuthIdentityAccessor;
use App\Security\OAuth\OAuthProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OAuthIdentityAccessorTest extends TestCase
{
    #[DataProvider('supportedProviders')]
    public function testGetsAndUnlinksOnlyTheRequestedIdentity(OAuthProvider $provider, string $expectedExternalId): void
    {
        $user = $this->userWithIdentities();
        $accessor = new OAuthIdentityAccessor();

        self::assertSame($expectedExternalId, $accessor->getExternalId($user, $provider));

        $accessor->unlink($user, $provider);

        self::assertNull($accessor->getExternalId($user, $provider));
        self::assertSame($this->expectedIdentitiesAfterUnlink($provider), $this->identities($user));
    }

    public function testGithubProvidersUseTheSameIdentityField(): void
    {
        $user = $this->userWithIdentities();
        $accessor = new OAuthIdentityAccessor();

        self::assertSame('github-id', $accessor->getExternalId($user, OAuthProvider::GithubEn));
        self::assertSame('github-id', $accessor->getExternalId($user, OAuthProvider::GithubRus));

        $accessor->unlink($user, OAuthProvider::GithubRus);

        self::assertNull($user->getGithubId());
    }

    #[DataProvider('unsupportedProviders')]
    public function testRejectsUnsupportedProviders(OAuthProvider $provider): void
    {
        $accessor = new OAuthIdentityAccessor();
        $user = $this->userWithIdentities();

        $this->expectException(\LogicException::class);
        $accessor->getExternalId($user, $provider);
    }

    #[DataProvider('unsupportedProviders')]
    public function testUnlinkRejectsUnsupportedProviders(OAuthProvider $provider): void
    {
        $accessor = new OAuthIdentityAccessor();
        $user = $this->userWithIdentities();

        $this->expectException(\LogicException::class);
        $accessor->unlink($user, $provider);
    }

    /** @return iterable<string, array{OAuthProvider, string}> */
    public static function supportedProviders(): iterable
    {
        yield 'Google' => [OAuthProvider::Google, 'google-id'];
        yield 'Yandex' => [OAuthProvider::Yandex, 'yandex-id'];
        yield 'Vkontakte' => [OAuthProvider::Vkontakte, 'vkontakte-id'];
        yield 'Github EN' => [OAuthProvider::GithubEn, 'github-id'];
        yield 'Github RU' => [OAuthProvider::GithubRus, 'github-id'];
    }

    /** @return iterable<string, array{OAuthProvider}> */
    public static function unsupportedProviders(): iterable
    {
        yield 'Facebook' => [OAuthProvider::Facebook];
        yield 'Linkedin' => [OAuthProvider::Linkedin];
        yield 'Mailru' => [OAuthProvider::Mailru];
    }

    private function userWithIdentities(): User
    {
        $user = new User();
        $user->setGoogleId('google-id');
        $user->setYandexId('yandex-id');
        $user->setVkontakteId('vkontakte-id');
        $user->setGithubId('github-id');

        return $user;
    }

    /** @return array<string, ?string> */
    private function identities(User $user): array
    {
        return [
            'google' => $user->getGoogleId(),
            'yandex' => $user->getYandexId(),
            'vkontakte' => $user->getVkontakteId(),
            'github' => $user->getGithubId(),
        ];
    }

    /** @return array<string, ?string> */
    private function expectedIdentitiesAfterUnlink(OAuthProvider $provider): array
    {
        $identities = [
            'google' => 'google-id',
            'yandex' => 'yandex-id',
            'vkontakte' => 'vkontakte-id',
            'github' => 'github-id',
        ];
        $identities[$provider->identityFamily()] = null;

        return $identities;
    }
}
