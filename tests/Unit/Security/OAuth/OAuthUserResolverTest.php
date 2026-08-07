<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\OAuth;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\OAuth\Exception\OAuthLoginDeniedException;
use App\Security\OAuth\OAuthIdentityAccessor;
use App\Security\OAuth\OAuthProvider;
use App\Security\OAuth\OAuthUserResolver;
use App\Security\UserChecker\DeletedUserChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

#[Group(name: 'unit')]
final class OAuthUserResolverTest extends TestCase
{
    #[DataProvider('providers')]
    public function testLinkedIdentityReturnsSameActiveUserWithoutEmailLookupOrMutation(
        OAuthProvider $provider,
        string $identityField,
        string $externalId,
    ): void {
        $user = $this->userWithIdentities();
        $this->setIdentity($user, $provider, $externalId);
        $before = $this->identities($user);
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with([$identityField => $externalId])->willReturn($user);
        $checker = $this->createMock(DeletedUserChecker::class);
        $checker->expects(self::once())->method('checkPreAuth')->with($user);
        $factoryCalls = 0;

        $resolution = $this->resolver($repository, $checker)->resolve(
            $provider,
            '  '.$externalId.'  ',
            'different-provider-email@example.test',
            static function () use (&$factoryCalls): User {
                ++$factoryCalls;

                return new User();
            }
        );

        self::assertSame($user, $resolution->user());
        self::assertFalse($resolution->isNewUser());
        self::assertSame(0, $factoryCalls);
        self::assertSame($before, $this->identities($user));
    }

    #[DataProvider('providers')]
    public function testDeletedLinkedIdentityIsRejectedWithoutMutation(
        OAuthProvider $provider,
        string $identityField,
        string $externalId,
    ): void {
        $user = $this->userWithIdentities();
        $user->setIsDeleted(true);
        $before = $this->identities($user);
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with([$identityField => $externalId])->willReturn($user);
        $checker = $this->createMock(DeletedUserChecker::class);
        $checker->expects(self::once())->method('checkPreAuth')->with($user)->willThrowException(
            new CustomUserMessageAccountStatusException('Invalid credentials.')
        );
        $factoryCalls = 0;

        try {
            $this->resolver($repository, $checker)->resolve($provider, $externalId, null, static function () use (&$factoryCalls): User {
                ++$factoryCalls;

                return new User();
            });
            self::fail('A deleted linked user must be rejected.');
        } catch (CustomUserMessageAccountStatusException $exception) {
            self::assertSame('Invalid credentials.', $exception->getMessageKey());
        }

        self::assertSame(0, $factoryCalls);
        self::assertSame($before, $this->identities($user));
    }

    #[DataProvider('providers')]
    public function testExistingEmailCollisionIsGenericAndNeverLinks(
        OAuthProvider $provider,
        string $identityField,
        string $externalId,
    ): void {
        $user = $this->userWithNullIdentities();
        $before = $this->identities($user);
        $lookups = [];
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::exactly(2))->method('findOneBy')->willReturnCallback(
            static function (array $criteria) use (&$lookups, $identityField, $externalId, $user): ?User {
                $lookups[] = $criteria;

                return [$identityField => $externalId] === $criteria ? null : $user;
            }
        );
        $checker = $this->createMock(DeletedUserChecker::class);
        $checker->expects(self::never())->method('checkPreAuth');
        $factoryCalls = 0;

        try {
            $this->resolver($repository, $checker)->resolve($provider, $externalId, ' existing@example.test ', static function () use (&$factoryCalls): User {
                ++$factoryCalls;

                return new User();
            });
            self::fail('An existing email must not be auto-linked.');
        } catch (OAuthLoginDeniedException $exception) {
            self::assertSame('OAuth authentication could not be completed.', $exception->getMessageKey());
            self::assertStringNotContainsString('existing@example.test', $exception->getMessage());
            self::assertStringNotContainsString($externalId, $exception->getMessage());
        }

        self::assertSame([[$identityField => $externalId], ['email' => 'existing@example.test']], $lookups);
        self::assertSame(0, $factoryCalls);
        self::assertSame($before, $this->identities($user));
    }

    #[DataProvider('invalidEmails')]
    public function testMissingEmailIsRejectedBeforeEmailLookupOrFactory(?string $email): void
    {
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with(['yandexId' => 'external-id'])->willReturn(null);
        $checker = $this->createMock(DeletedUserChecker::class);
        $checker->expects(self::never())->method('checkPreAuth');
        $factoryCalls = 0;

        $this->expectException(OAuthLoginDeniedException::class);
        try {
            $this->resolver($repository, $checker)->resolve(OAuthProvider::Yandex, 'external-id', $email, static function () use (&$factoryCalls): User {
                ++$factoryCalls;

                return new User();
            });
        } finally {
            self::assertSame(0, $factoryCalls);
        }
    }

    #[DataProvider('providers')]
    public function testNewUserGetsAuthoritativeIdentityEmailAndUnverifiedState(
        OAuthProvider $provider,
        string $identityField,
        string $externalId,
    ): void {
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::exactly(2))->method('findOneBy')->willReturn(null);
        $checker = $this->createMock(DeletedUserChecker::class);
        $checker->expects(self::never())->method('checkPreAuth');
        $user = $this->userWithIdentities();
        $user->setEmail('factory@example.test')->setIsVerified(true);
        $factoryCalls = 0;

        $resolution = $this->resolver($repository, $checker)->resolve(
            $provider,
            $externalId,
            '  provider@example.test  ',
            static function () use (&$factoryCalls, $user): User {
                ++$factoryCalls;

                return $user;
            }
        );

        self::assertSame($user, $resolution->user());
        self::assertTrue($resolution->isNewUser());
        self::assertSame(1, $factoryCalls);
        self::assertSame('provider@example.test', $user->getEmail());
        self::assertSame($externalId, (new OAuthIdentityAccessor())->getExternalId($user, $provider));
        self::assertFalse($user->isVerified());
    }

    #[DataProvider('invalidExternalIds')]
    public function testInvalidExternalIdIsRejectedBeforeLookup(mixed $externalId): void
    {
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::never())->method('findOneBy');
        $checker = $this->createMock(DeletedUserChecker::class);
        $checker->expects(self::never())->method('checkPreAuth');

        $this->expectException(OAuthLoginDeniedException::class);
        $this->resolver($repository, $checker)->resolve(OAuthProvider::Google, $externalId, 'user@example.test', static fn (): User => new User());
    }

    #[DataProvider('futureProviders')]
    public function testUnimplementedProviderIsRejectedBeforeLookup(OAuthProvider $provider): void
    {
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::never())->method('findOneBy');
        $checker = $this->createMock(DeletedUserChecker::class);
        $checker->expects(self::never())->method('checkPreAuth');

        $this->expectException(OAuthLoginDeniedException::class);
        $this->resolver($repository, $checker)->resolve($provider, 'external-id', 'user@example.test', static fn (): User => new User());
    }

    /** @return iterable<string, array{OAuthProvider, string, string}> */
    public static function providers(): iterable
    {
        yield 'Google' => [OAuthProvider::Google, 'googleId', 'google-external-id'];
        yield 'Yandex' => [OAuthProvider::Yandex, 'yandexId', 'yandex-external-id'];
        yield 'Vkontakte' => [OAuthProvider::Vkontakte, 'vkontakteId', 'vkontakte-external-id'];
        yield 'GitHub EN' => [OAuthProvider::GithubEn, 'githubId', 'github-external-id'];
        yield 'GitHub RU' => [OAuthProvider::GithubRus, 'githubId', 'github-external-id'];
        yield 'Facebook' => [OAuthProvider::Facebook, 'facebookId', 'facebook-external-id'];
    }

    /** @return iterable<string, array{?string}> */
    public static function invalidEmails(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'whitespace' => ['   '];
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidExternalIds(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'whitespace' => ['   '];
        yield 'array' => [['external-id']];
    }

    /** @return iterable<string, array{OAuthProvider}> */
    public static function futureProviders(): iterable
    {
        yield 'LinkedIn' => [OAuthProvider::Linkedin];
        yield 'Mail.ru' => [OAuthProvider::Mailru];
    }

    private function resolver(UserRepository $repository, DeletedUserChecker $checker): OAuthUserResolver
    {
        return new OAuthUserResolver($repository, $checker, new OAuthIdentityAccessor());
    }

    private function userWithNullIdentities(): User
    {
        $user = new User();
        $user->setGoogleId(null);
        $user->setYandexId(null);
        $user->setVkontakteId(null);
        $user->setGithubId(null);
        $user->setFacebookId(null);

        return $user;
    }

    private function userWithIdentities(): User
    {
        $user = new User();
        $user->setGoogleId('google-original');
        $user->setYandexId('yandex-original');
        $user->setVkontakteId('vkontakte-original');
        $user->setGithubId('github-original');
        $user->setFacebookId('facebook-original');

        return $user;
    }

    private function setIdentity(User $user, OAuthProvider $provider, string $externalId): void
    {
        (new OAuthIdentityAccessor())->link($user, $provider, $externalId);
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
        ];
    }
}
