<?php

namespace App\Tests\Integration\Security\Verifier;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Verifier\EmailVerifier;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

#[Group(name: 'integration')]
class EmailVerifierTest extends KernelTestCase
{
    private UserRepository $userRepository;

    private EmailVerifier $emailVerifier;

    private EntityManagerInterface $entityManager;

    private UriSigner $verifyEmailUriSigner;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->emailVerifier = self::getContainer()->get(EmailVerifier::class);
        $this->userRepository = self::getContainer()->get(UserRepository::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->verifyEmailUriSigner = self::getContainer()->get('symfonycasts.verify_email.uri_signer');
    }

    #[TestDox('Подменённая ссылка подтверждения не верифицирует пользователя')]
    public function testTamperedConfirmationDoesNotVerifyPersistedUser(): void
    {
        $user = $this->findPersistedUnverifiedUser();
        $signedUrl = $this->emailVerifier->generateEmailSignature('main_verify_email', $user)->getSignedUrl();
        $request = self::createRequestWithQuery($signedUrl, ['signature' => 'tampered']);

        $this->assertRejectedConfirmationDoesNotVerifyPersistedUser($request, $user);
    }

    #[TestDox('Просроченная ссылка подтверждения не верифицирует пользователя')]
    public function testExpiredConfirmationDoesNotVerifyPersistedUser(): void
    {
        $user = $this->findPersistedUnverifiedUser();
        $signedUrl = $this->emailVerifier->generateEmailSignature('main_verify_email', $user)->getSignedUrl();
        $unsignedUrl = self::createUrlWithQuery($signedUrl, [
            'expires' => (string) (time() - 1),
            'signature' => null,
        ]);
        $request = Request::create($this->verifyEmailUriSigner->sign($unsignedUrl));

        self::assertTrue($this->verifyEmailUriSigner->checkRequest($request));

        $this->assertRejectedConfirmationDoesNotVerifyPersistedUser($request, $user);
    }

    private function findPersistedUnverifiedUser(): User
    {
        $user = $this->userRepository->findOneBy(['email' => UserFixtures::USER_1_EMAIL]);
        self::assertNotNull($user);

        $user->setIsVerified(false);
        $this->entityManager->flush();

        return $user;
    }

    private function assertRejectedConfirmationDoesNotVerifyPersistedUser(Request $request, User $user): void
    {
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
            self::fail('The confirmation request was not rejected.');
        } catch (VerifyEmailExceptionInterface) {
        }

        $this->entityManager->clear();
        $persistedUser = $this->userRepository->find($user->getId());

        self::assertNotNull($persistedUser);
        self::assertFalse($persistedUser->isVerified());
    }

    /**
     * @param array<string, string|null> $changes
     */
    private static function createRequestWithQuery(string $url, array $changes): Request
    {
        return Request::create(self::createUrlWithQuery($url, $changes));
    }

    /**
     * @param array<string, string|null> $changes
     */
    private static function createUrlWithQuery(string $url, array $changes): string
    {
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);

        foreach ($changes as $name => $value) {
            if (null === $value) {
                unset($query[$name]);

                continue;
            }

            $query[$name] = $value;
        }

        $authority = $parts['host'];
        if (isset($parts['port'])) {
            $authority .= ':'.$parts['port'];
        }

        return $parts['scheme'].'://'.$authority.($parts['path'] ?? '').'?'.http_build_query($query);
    }
}
