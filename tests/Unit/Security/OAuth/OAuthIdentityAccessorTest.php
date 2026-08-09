<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\OAuth;

use App\Entity\User;
use App\Security\OAuth\OAuthIdentityAccessor;
use App\Security\OAuth\OAuthProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group(name: 'unit')]
final class OAuthIdentityAccessorTest extends TestCase
{
    #[DataProvider('supportedProviders')]
    public function testGetsAndUnlinksOnlyTheRequestedIdentity(OAuthProvider $provider, string $expectedExternalId, string $expectedField): void
    {
        $user = $this->userWithIdentities();
        $accessor = new OAuthIdentityAccessor();

        self::assertSame($expectedExternalId, $accessor->getExternalId($user, $provider));
        self::assertSame($expectedField, $accessor->identityField($provider));

        $accessor->unlink($user, $provider);

        self::assertNull($accessor->getExternalId($user, $provider));
        self::assertSame($this->expectedIdentitiesAfterUnlink($provider), $this->identities($user));
    }

    #[DataProvider('supportedProviders')]
    public function testLinksOnlyTheRequestedIdentity(OAuthProvider $provider, string $externalId, string $identityField): void
    {
        $user = new User();
        $accessor = new OAuthIdentityAccessor();

        $accessor->link($user, $provider, $externalId);

        self::assertSame($externalId, $accessor->getExternalId($user, $provider));
        self::assertSame($identityField, $accessor->identityField($provider));
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

    /** @return iterable<string, array{OAuthProvider, string, string}> */
    public static function supportedProviders(): iterable
    {
        yield 'Google' => [OAuthProvider::Google, 'google-id', 'googleId'];
        yield 'Yandex' => [OAuthProvider::Yandex, 'yandex-id', 'yandexId'];
        yield 'Vkontakte' => [OAuthProvider::Vkontakte, 'vkontakte-id', 'vkontakteId'];
        yield 'Github EN' => [OAuthProvider::GithubEn, 'github-id', 'githubId'];
        yield 'Github RU' => [OAuthProvider::GithubRus, 'github-id', 'githubId'];
        yield 'Facebook' => [OAuthProvider::Facebook, 'facebook-id', 'facebookId'];
        yield 'LinkedIn' => [OAuthProvider::Linkedin, 'LiNkEdIn-sub', 'linkedinId'];
    }

    /** @return iterable<string, array{OAuthProvider}> */
    public static function unsupportedProviders(): iterable
    {
        yield 'Mailru' => [OAuthProvider::Mailru];
    }

    private function userWithIdentities(): User
    {
        $user = new User();
        $user->setGoogleId('google-id');
        $user->setYandexId('yandex-id');
        $user->setVkontakteId('vkontakte-id');
        $user->setGithubId('github-id');
        $user->setFacebookId('facebook-id');
        $user->setLinkedinId('LiNkEdIn-sub');

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
            'facebook' => $user->getFacebookId(),
            'linkedin' => $user->getLinkedinId(),
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
            'facebook' => 'facebook-id',
            'linkedin' => 'LiNkEdIn-sub',
        ];
        $identities[$provider->identityFamily()] = null;

        return $identities;
    }
}
