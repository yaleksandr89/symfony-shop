<?php

declare(strict_types=1);

namespace App\Tests\Integration\OAuthBundle\Security;

use App\Entity\User;
use App\OAuthBundle\Security\OAuth\Exception\OAuthIdentityConflictException;
use App\OAuthBundle\Security\OAuth\OAuthAccountLinker;
use App\OAuthBundle\Security\OAuth\OAuthProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group(name: 'integration')]
final class OAuthAccountLinkerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private OAuthAccountLinker $linker;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->linker = self::getContainer()->get(OAuthAccountLinker::class);
    }

    #[DataProvider('providers')]
    #[TestDox('Для текущего пользователя сохраняется только запрошенный идентификатор')]
    public function testLinksManagedCurrentUserAndPersistsOnlyRequestedIdentity(OAuthProvider $provider): void
    {
        $user = $this->persistUser('success-'.$provider->value);
        $externalId = 'integration-'.$provider->value.'-'.str_replace('.', '', uniqid('', true));

        $this->linker->link($user, $provider, $externalId);

        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(User::class, $user->getId());
        self::assertInstanceOf(User::class, $reloaded);
        self::assertSame($externalId, $this->identity($reloaded, $provider));
        foreach (OAuthProvider::cases() as $otherProvider) {
            if ($otherProvider->isImplemented() && $otherProvider->identityFamily() !== $provider->identityFamily()) {
                self::assertNull($this->identity($reloaded, $otherProvider));
            }
        }
    }

    #[TestDox('Клиенты GitHub разделяют одну границу владения')]
    public function testGithubClientsShareTheSameOwnershipBoundary(): void
    {
        $externalId = 'integration-github-shared-'.str_replace('.', '', uniqid('', true));
        $owner = $this->persistUser('github-owner');
        $this->linker->link($owner, OAuthProvider::GithubEn, $externalId);
        $currentUser = $this->persistUser('github-current');

        $this->expectException(OAuthIdentityConflictException::class);
        try {
            $this->linker->link($currentUser, OAuthProvider::GithubRus, $externalId);
        } finally {
            self::assertNull($currentUser->getGithubId());
        }
    }

    #[TestDox('Идентификатор другого пользователя отклоняется без изменения базы данных')]
    public function testIdentityOwnedByAnotherUserIsRejectedWithoutDatabaseMutation(): void
    {
        $externalId = 'integration-owned-'.str_replace('.', '', uniqid('', true));
        $owner = $this->persistUser('owner');
        $owner->setGoogleId($externalId);
        $this->entityManager->flush();
        $currentUser = $this->persistUser('current');

        try {
            $this->linker->link($currentUser, OAuthProvider::Google, $externalId);
            self::fail('An identity owned by another user must be rejected.');
        } catch (OAuthIdentityConflictException $exception) {
            self::assertSame('OAuth identity cannot be linked.', $exception->getMessage());
        }

        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(User::class, $currentUser->getId());
        self::assertInstanceOf(User::class, $reloaded);
        self::assertNull($reloaded->getGoogleId());
    }

    #[TestDox('Устаревший managed user обновляется до проверки; конфликт сохраняет manager')]
    public function testStaleManagedUserCannotOverwriteCommittedIdentity(): void
    {
        $user = $this->persistUser('stale');
        $this->entityManager->getConnection()->executeStatement('UPDATE "user" SET google_id = ? WHERE id = ?', ['winner', $user->getId()]);
        self::assertNull($user->getGoogleId());
        try {
            $this->linker->link($user, OAuthProvider::Google, 'loser');
            self::fail('Stale identity cannot overwrite the persisted winner.');
        } catch (OAuthIdentityConflictException) {
            self::assertTrue($this->entityManager->isOpen());
            self::assertSame('winner', $this->linker->recoverUser($user)->getGoogleId());
            self::assertNull($user->getGoogleId());
            self::assertTrue($this->entityManager->contains($this->linker->recoverUser($user)));
        }
    }

    #[TestDox('Запоздалая отвязка не удаляет новую привязку; последовательная смена остаётся доступной')]
    public function testDelayedUnlinkCannotEraseSequentialReplacement(): void
    {
        $user = $this->persistUser('replacement');
        $this->linker->link($user, OAuthProvider::GithubEn, 'old-identity');
        $this->linker->unlink($user, OAuthProvider::GithubRus, 'old-identity');
        $this->linker->link($user, OAuthProvider::GithubRus, 'new-identity');
        try {
            $this->linker->unlink($user, OAuthProvider::GithubEn, 'old-identity');
            self::fail('Delayed unlink cannot erase a replacement.');
        } catch (OAuthIdentityConflictException) {
            self::assertTrue($this->entityManager->isOpen());
            self::assertSame('new-identity', $this->linker->recoverUser($user)->getGithubId());
        }
        $this->linker->unlink($user, OAuthProvider::GithubEn, 'new-identity');
        $this->linker->unlink($user, OAuthProvider::GithubRus, 'new-identity');
        self::assertNull($user->getGithubId());
        self::assertTrue($this->entityManager->isOpen());
    }

    /** @return iterable<string, array{OAuthProvider}> */
    public static function providers(): iterable
    {
        yield 'Google' => [OAuthProvider::Google];
        yield 'Yandex' => [OAuthProvider::Yandex];
        yield 'Vkontakte' => [OAuthProvider::Vkontakte];
        yield 'GitHub EN' => [OAuthProvider::GithubEn];
        yield 'GitHub RU' => [OAuthProvider::GithubRus];
        yield 'Facebook' => [OAuthProvider::Facebook];
        yield 'LinkedIn' => [OAuthProvider::Linkedin];
    }

    private function persistUser(string $suffix): User
    {
        $user = (new User())
            ->setEmail('oauth-account-linker-'.$suffix.'-'.uniqid('', true).'@example.test')
            ->setPassword('not-used')
            ->setIsVerified(true);
        $user->setGoogleId(null);
        $user->setYandexId(null);
        $user->setVkontakteId(null);
        $user->setGithubId(null);
        $user->setFacebookId(null);
        $user->setLinkedinId(null);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function identity(User $user, OAuthProvider $provider): ?string
    {
        return match ($provider) {
            OAuthProvider::Google => $user->getGoogleId(),
            OAuthProvider::Yandex => $user->getYandexId(),
            OAuthProvider::Vkontakte => $user->getVkontakteId(),
            OAuthProvider::GithubEn, OAuthProvider::GithubRus => $user->getGithubId(),
            OAuthProvider::Facebook => $user->getFacebookId(),
            OAuthProvider::Linkedin => $user->getLinkedinId(),
            default => null,
        };
    }
}
