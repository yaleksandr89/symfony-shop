<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group(name: 'integration')]
class OAuthIdentityUniqueConstraintTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $registry = self::getContainer()->get(ManagerRegistry::class);
        $entityManager = $registry->getManager();
        if ($entityManager instanceof EntityManagerInterface && !$entityManager->isOpen()) {
            $entityManager = $registry->resetManager();
        }

        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }

    #[TestWith(['google'])]
    #[TestWith(['yandex'])]
    #[TestWith(['vkontakte'])]
    #[TestWith(['github'])]
    public function testProviderExternalIdMustBeUnique(string $provider): void
    {
        $externalId = 'external-id-'.$provider;
        $firstUser = $this->newUser($provider.'-first');
        $this->setExternalId($firstUser, $provider, $externalId);
        $this->entityManager->persist($firstUser);
        $this->entityManager->flush();

        $secondUser = $this->newUser($provider.'-second');
        $this->setExternalId($secondUser, $provider, $externalId);
        $this->entityManager->persist($secondUser);

        $this->expectException(UniqueConstraintViolationException::class);

        try {
            $this->entityManager->flush();
        } finally {
            self::assertFalse($this->entityManager->isOpen());
        }
    }

    public function testMultipleUsersCanHaveNullProviderExternalIds(): void
    {
        $firstUser = $this->newUser('null-first');
        $secondUser = $this->newUser('null-second');

        $this->entityManager->persist($firstUser);
        $this->entityManager->persist($secondUser);
        $this->entityManager->flush();

        self::assertNotNull($firstUser->getId());
        self::assertNotNull($secondUser->getId());
    }

    private function newUser(string $suffix): User
    {
        return (new User())
            ->setEmail(sprintf('oauth-identity-%s-%s@example.test', $suffix, uniqid('', true)))
            ->setPassword('not-used-by-this-test')
            ->setIsVerified(true);
    }

    private function setExternalId(User $user, string $provider, string $externalId): void
    {
        switch ($provider) {
            case 'google':
                $user->setGoogleId($externalId);
                break;
            case 'yandex':
                $user->setYandexId($externalId);
                break;
            case 'vkontakte':
                $user->setVkontakteId($externalId);
                break;
            case 'github':
                $user->setGithubId($externalId);
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Unsupported OAuth provider "%s".', $provider));
        }
    }
}
