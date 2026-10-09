<?php

declare(strict_types=1);

namespace App\Tests\Unit\OAuthBundle\Security\OAuth;

use App\Account\Repository\UserRepository;
use App\Entity\User;
use App\OAuthBundle\Security\OAuth\Exception\OAuthIdentityConflictException;
use App\OAuthBundle\Security\OAuth\OAuthAccountLinker;
use App\OAuthBundle\Security\OAuth\OAuthIdentityAccessor;
use App\OAuthBundle\Security\OAuth\OAuthProvider;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Throwable;

#[Group('unit')]
final class OAuthAccountLinkerTest extends TestCase
{
    #[DataProvider('providers')]
    #[TestDox('Связывание меняет только выбранное поле после блокировки и refresh')]
    public function testLinksOnlyRequestedIdentityAfterLockedRefresh(OAuthProvider $provider, string $field): void
    {
        $user = $this->user();
        $user->setFullName('unchanged');
        [$linker, $manager, $repository] = $this->linker($user);
        $manager->expects(self::once())->method('find')->with(User::class, 13, LockMode::PESSIMISTIC_WRITE)->willReturn($user);
        $manager->expects(self::once())->method('refresh')->with($user, LockMode::PESSIMISTIC_WRITE);
        $repository->expects(self::once())->method('findOneBy')->with([$field => 'external-id'])->willReturn(null);

        $linker->link($user, $provider, '  external-id  ');

        foreach (OAuthProvider::cases() as $other) {
            if ($other->isImplemented()) {
                self::assertSame($other->identityFamily() === $provider->identityFamily() ? 'external-id' : null, (new OAuthIdentityAccessor())->getExternalId($user, $other));
            }
        }
        self::assertSame('unchanged', $user->getFullName());
    }

    #[DataProvider('invalidExternalIds')]
    #[TestDox('Неверный внешний идентификатор отклоняется до транзакции')]
    public function testRejectsInvalidExternalIdBeforeTransaction(mixed $externalId): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('getManagerForClass');
        $this->expectException(OAuthIdentityConflictException::class);
        (new OAuthAccountLinker(new OAuthIdentityAccessor(), $registry))->link($this->user(), OAuthProvider::Google, $externalId);
    }

    #[TestDox('Проверка обновлённого поля отклоняет устаревший callback без исключения внутри транзакции')]
    public function testRefreshedIdentityRejectsStaleCallbackWithoutThrowingInsideTransaction(): void
    {
        $user = $this->user();
        [$linker, $manager, $repository] = $this->linker($user);
        $manager->method('find')->willReturn($user);
        $manager->method('refresh')->willReturnCallback(static fn () => $user->setGithubId('winner'));
        $repository->expects(self::never())->method('findOneBy');
        try {
            $linker->link($user, OAuthProvider::GithubRus, 'loser');
            self::fail('A stale callback cannot overwrite the winner.');
        } catch (OAuthIdentityConflictException $exception) {
            self::assertSame('OAuth identity cannot be linked.', $exception->getMessage());
            self::assertSame('winner', $user->getGithubId());
        }
    }

    #[TestDox('Чужой идентификатор отклоняется после refresh без изменения пользователя')]
    public function testRejectsOwnedIdentityWithoutMutation(): void
    {
        $user = $this->user();
        [$linker, $manager, $repository] = $this->linker($user);
        $manager->method('find')->willReturn($user);
        $repository->expects(self::once())->method('findOneBy')->willReturn($this->user());
        $this->expectException(OAuthIdentityConflictException::class);
        try {
            $linker->link($user, OAuthProvider::Google, 'owned');
        } finally {
            self::assertNull($user->getGoogleId());
        }
    }

    #[DataProvider('unlinkStates')]
    #[TestDox('Отвязка сравнивает наблюдённый POST идентификатор с обновлённым полем')]
    public function testUnlinkUsesExpectedPostIdentity(?string $current, bool $conflict): void
    {
        $user = $this->user();
        $user->setGoogleId('observed');
        [$linker, $manager, $repository] = $this->linker($user);
        $manager->method('find')->willReturn($user);
        $manager->method('refresh')->willReturnCallback(static fn () => $user->setGoogleId($current));
        $repository->expects(self::never())->method('findOneBy');
        if ($conflict) {
            $this->expectException(OAuthIdentityConflictException::class);
        }
        try {
            $linker->unlink($user, OAuthProvider::Google, 'observed');
        } finally {
            self::assertSame($conflict ? $current : null, $user->getGoogleId());
        }
    }

    #[TestDox('Неожиданный сбой сохраняет исходное исключение и не оставляет попытку привязки в снимке')]
    public function testUnexpectedFailureIsNotMasked(): void
    {
        $user = $this->user();
        $failure = new RuntimeException('synthetic failure');
        [$linker, $manager, $repository] = $this->linker($user, $failure);
        $manager->method('find')->willReturn($user);
        $repository->expects(self::once())->method('findOneBy')->willReturn(null);
        try {
            $linker->link($user, OAuthProvider::Google, 'candidate');
            self::fail('A failed transaction cannot report success.');
        } catch (RuntimeException $exception) {
            self::assertSame($failure, $exception);
            self::assertNull($user->getGoogleId());
        }
    }

    #[TestDox('Recovery получает manager заново после reset и возвращает managed пользователя')]
    public function testRecoveryDoesNotReuseClosedManager(): void
    {
        $user = $this->user();
        $persisted = clone $user;
        $closed = $this->createMock(EntityManagerInterface::class);
        $closed->method('isOpen')->willReturn(false);
        $closed->expects(self::never())->method('find');
        $fresh = $this->createMock(EntityManagerInterface::class);
        $fresh->expects(self::once())->method('contains')->with($user)->willReturn(false);
        $fresh->expects(self::once())->method('find')->with(User::class, 13)->willReturn($persisted);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturnOnConsecutiveCalls($closed, $fresh);
        $registry->expects(self::once())->method('resetManager');
        self::assertSame($persisted, (new OAuthAccountLinker(new OAuthIdentityAccessor(), $registry))->recoverUser($user));
    }

    /** @return iterable<string, array{OAuthProvider, string}> */
    public static function providers(): iterable
    {
        yield 'Google' => [OAuthProvider::Google, 'googleId'];
        yield 'Yandex' => [OAuthProvider::Yandex, 'yandexId'];
        yield 'Vkontakte' => [OAuthProvider::Vkontakte, 'vkontakteId'];
        yield 'GitHub EN' => [OAuthProvider::GithubEn, 'githubId'];
        yield 'GitHub RU' => [OAuthProvider::GithubRus, 'githubId'];
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

    /** @return iterable<string, array{?string, bool}> */
    public static function unlinkStates(): iterable
    {
        yield 'same identity' => ['observed', false];
        yield 'already unlinked' => [null, false];
        yield 'replacement identity' => ['replacement', true];
    }

    /** @return array{OAuthAccountLinker, EntityManagerInterface, UserRepository} */
    private function linker(User $user, ?Throwable $failure = null): array
    {
        $repository = $this->createMock(UserRepository::class);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->willReturn($repository);
        $manager->expects(self::never())->method('persist');
        $manager->expects(self::never())->method('close');
        $manager->expects(self::once())->method('wrapInTransaction')->willReturnCallback(function (callable $mutation) use ($manager, $failure): bool {
            $accepted = $mutation($manager);
            self::assertIsBool($accepted);
            if (null !== $failure) {
                throw $failure;
            }

            return $accepted;
        });
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        return [new OAuthAccountLinker(new OAuthIdentityAccessor(), $registry), $manager, $repository];
    }

    private function user(): User
    {
        $user = (new User())->setEmail('unit@example.test')->setPassword('synthetic-hash');
        (new ReflectionProperty(User::class, 'id'))->setValue($user, 13);
        $user->setGoogleId(null);
        $user->setYandexId(null);
        $user->setVkontakteId(null);
        $user->setGithubId(null);

        return $user;
    }
}
