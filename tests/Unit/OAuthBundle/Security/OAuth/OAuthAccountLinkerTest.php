<?php

declare(strict_types=1);

namespace App\Tests\Unit\OAuthBundle\Security\OAuth;

use App\Entity\User;
use App\Account\Repository\UserRepository;
use App\OAuthBundle\Security\OAuth\Exception\OAuthIdentityConflictException;
use App\OAuthBundle\Security\OAuth\OAuthAccountLinker;
use App\OAuthBundle\Security\OAuth\OAuthIdentityAccessor;
use App\OAuthBundle\Security\OAuth\OAuthProvider;
use Doctrine\DBAL\Driver\Exception as DriverExceptionInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Group(name: 'unit')]
final class OAuthAccountLinkerTest extends TestCase
{
    #[DataProvider('providers')]
    #[TestDox('Связывает только запрошенный идентификатор одним сохранением')]
    public function testLinksOnlyRequestedIdentityWithOneFlush(OAuthProvider $provider, string $field): void
    {
        $user = $this->user();
        $before = $this->identities($user);
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with([$field => 'external-id'])->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        (new OAuthAccountLinker(new OAuthIdentityAccessor(), $repository, $entityManager))
            ->link($user, $provider, '  external-id  ');

        $before[$provider->identityFamily()] = 'external-id';
        self::assertSame($before, $this->identities($user));
    }

    #[DataProvider('invalidExternalIds')]
    #[TestDox('Пустой или неверный внешний идентификатор отклоняется')]
    public function testRejectsBlankOrInvalidExternalId(mixed $externalId): void
    {
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::never())->method('findOneBy');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $this->expectException(OAuthIdentityConflictException::class);
        (new OAuthAccountLinker(new OAuthIdentityAccessor(), $repository, $entityManager))
            ->link($this->user(), OAuthProvider::Google, $externalId);
    }

    #[TestDox('Уже связанный у текущего пользователя идентификатор отклоняется до поиска')]
    public function testRejectsIdentityAlreadyLinkedOnCurrentUserBeforeLookup(): void
    {
        $user = $this->user();
        $user->setYandexId('existing');
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::never())->method('findOneBy');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $this->expectException(OAuthIdentityConflictException::class);
        (new OAuthAccountLinker(new OAuthIdentityAccessor(), $repository, $entityManager))
            ->link($user, OAuthProvider::Yandex, 'new-id');
    }

    #[TestDox('Идентификатор другого пользователя отклоняется без изменений')]
    public function testRejectsIdentityOwnedByAnotherUserWithoutMutation(): void
    {
        $user = $this->user();
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with(['githubId' => 'owned'])->willReturn($this->user());
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        try {
            (new OAuthAccountLinker(new OAuthIdentityAccessor(), $repository, $entityManager))
                ->link($user, OAuthProvider::GithubRus, 'owned');
            self::fail('An owned identity must be rejected.');
        } catch (OAuthIdentityConflictException $exception) {
            self::assertSame('OAuth identity cannot be linked.', $exception->getMessage());
            self::assertNull($user->getGithubId());
            self::assertStringNotContainsString('owned', $exception->getMessage());
        }
    }

    #[TestDox('Гонка уникальности даёт нейтральный отказ и восстанавливает идентификатор в памяти')]
    public function testUniqueConstraintRaceIsGenericAndRestoresInMemoryIdentity(): void
    {
        $user = $this->user();
        $repository = $this->createStub(UserRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $driverException = new class('database detail containing secret-id') extends \RuntimeException implements DriverExceptionInterface {
            public function getSQLState(): ?string
            {
                return '23000';
            }
        };
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush')->willThrowException(
            new UniqueConstraintViolationException($driverException, null)
        );

        try {
            (new OAuthAccountLinker(new OAuthIdentityAccessor(), $repository, $entityManager))
                ->link($user, OAuthProvider::Vkontakte, 'secret-id');
            self::fail('A unique race must be translated.');
        } catch (OAuthIdentityConflictException $exception) {
            self::assertSame('OAuth identity cannot be linked.', $exception->getMessage());
            self::assertNull($user->getVkontakteId());
            self::assertStringNotContainsString('secret-id', $exception->getMessage());
        }
    }

    #[TestDox('Сбой сохранения пробрасывается без подмены и восстанавливает все OAuth-идентификаторы')]
    public function testFlushFailureRethrowsSameExceptionAndRestoresAllIdentities(): void
    {
        $user = $this->user();
        $user->setYandexId('existing-yandex-id');
        $before = $this->identities($user);
        $failure = new \RuntimeException('database failure');
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::once())->method('findOneBy')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush')->willThrowException($failure);

        try {
            (new OAuthAccountLinker(new OAuthIdentityAccessor(), $repository, $entityManager))
                ->link($user, OAuthProvider::Google, 'external-id');
            self::fail('A failed flush must not leave an in-memory identity.');
        } catch (\RuntimeException $exception) {
            self::assertSame($failure, $exception);
            self::assertSame($before, $this->identities($user));
        }
    }

    /** @return iterable<string, array{OAuthProvider, string}> */
    public static function providers(): iterable
    {
        yield 'Google' => [OAuthProvider::Google, 'googleId'];
        yield 'Yandex' => [OAuthProvider::Yandex, 'yandexId'];
        yield 'Vkontakte' => [OAuthProvider::Vkontakte, 'vkontakteId'];
        yield 'GitHub EN shared field' => [OAuthProvider::GithubEn, 'githubId'];
        yield 'GitHub RU shared field' => [OAuthProvider::GithubRus, 'githubId'];
        yield 'Facebook' => [OAuthProvider::Facebook, 'facebookId'];
        yield 'LinkedIn' => [OAuthProvider::Linkedin, 'linkedinId'];
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidExternalIds(): iterable
    {
        yield 'blank' => ['   '];
        yield 'null' => [null];
        yield 'array' => [['id']];
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

    private function user(): User
    {
        $user = new User();
        $user->setGoogleId(null);
        $user->setYandexId(null);
        $user->setVkontakteId(null);
        $user->setGithubId(null);
        $user->setFacebookId(null);
        $user->setLinkedinId(null);

        return $user;
    }
}
