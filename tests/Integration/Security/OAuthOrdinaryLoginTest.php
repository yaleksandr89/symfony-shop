<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\Entity\User;
use App\Security\OAuth\Exception\OAuthLoginDeniedException;
use App\Security\OAuth\OAuthNewUserRegistrar;
use App\Security\OAuth\OAuthProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group(name: 'integration')]
final class OAuthOrdinaryLoginTest extends KernelTestCase
{
    #[DataProvider('uniqueRaces')]
    public function testDatabaseUniqueRaceIsGenericAndSendsNoVerificationEmail(string $race): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $nonce = str_replace('.', '', uniqid('', true));
        $duplicateEmail = 'oauth-race-'.$nonce.'@example.test';
        $duplicateExternalId = 'oauth-race-'.$nonce;
        $owner = (new User())
            ->setEmail($duplicateEmail)
            ->setPassword('owner-password-hash')
            ->setIsVerified(true);
        $owner->setGoogleId('external-id' === $race ? $duplicateExternalId : null);
        $entityManager->persist($owner);
        $entityManager->flush();

        $candidate = (new User())
            ->setEmail('email' === $race ? $duplicateEmail : 'oauth-candidate-'.$nonce.'@example.test')
            ->setIsVerified(true);
        $candidate->setGoogleId('external-id' === $race ? $duplicateExternalId : 'oauth-candidate-'.$nonce);

        try {
            self::getContainer()->get(OAuthNewUserRegistrar::class)->register($candidate, OAuthProvider::Google);
            self::fail('A database unique race must deny OAuth authentication.');
        } catch (OAuthLoginDeniedException $exception) {
            self::assertSame('OAuth authentication could not be completed.', $exception->getMessageKey());
            self::assertStringNotContainsString($duplicateEmail, $exception->getMessage());
            self::assertStringNotContainsString($duplicateExternalId, $exception->getMessage());
        }

        self::assertEmailCount(0);
    }

    /** @return iterable<string, array{string}> */
    public static function uniqueRaces(): iterable
    {
        yield 'email' => ['email'];
        yield 'provider external ID' => ['external-id'];
    }
}
