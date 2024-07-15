<?php

namespace App\Tests\Integration\Security\Verifier;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Verifier\EmailVerifier;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use SymfonyCasts\Bundle\VerifyEmail\Model\VerifyEmailSignatureComponents;

#[Group(name: 'integration')]
class EmailVerifierTest extends KernelTestCase
{
    private UserRepository $userRepository;

    private EmailVerifier $emailVerifier;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->emailVerifier = self::getContainer()->get(EmailVerifier::class);
        $this->userRepository = self::getContainer()->get(UserRepository::class);
    }

    public function testGenerateEmailSignature(): void
    {
        $user = $this->userRepository->findOneBy([
            'email' => UserFixtures::USER_1_EMAIL,
        ]);
        $user->setIsVerified(false);

        $currentDateTime = new DateTimeImmutable();
        $emailSignature = $this->emailVerifier->generateEmailSignature('main_verify_email', $user);

        $this->assertGreaterThan($currentDateTime, $emailSignature->getExpiresAt());
    }

    public function testHandleEmailConfirmation(): void
    {
        $user = $this->userRepository->findOneBy(['email' => UserFixtures::USER_1_EMAIL]);
        $user->setIsVerified(false);

        $emailSignature = $this->emailVerifier->generateEmailSignature('main_verify_email', $user);

        $this->emailVerifier->handleEmailConfirmation(
            self::getRequest($emailSignature->getSignedUrl()),
            $user
        );
        $this->assertTrue($user->isVerified());
    }

    public function testGenerateEmailSignatureAndHandleEmailConfirmation(): void
    {
        $user = $this->userRepository->findOneBy([
            'email' => UserFixtures::USER_1_EMAIL,
        ]);
        $user->setIsVerified(false);

        $emailSignature = $this->checkGenerateEmailSignature($user);

        $this->checkHandleEmailConfirmation($emailSignature, $user);
    }

    private function checkGenerateEmailSignature(User $user): VerifyEmailSignatureComponents
    {
        $currentDateTime = new DateTimeImmutable();
        $emailSignature = $this->emailVerifier->generateEmailSignature('main_verify_email', $user);

        $this->assertGreaterThan($currentDateTime, $emailSignature->getExpiresAt());

        return $emailSignature;
    }

    private function checkHandleEmailConfirmation(VerifyEmailSignatureComponents $signatureComponents, User $user): void
    {
        $this->assertFalse($user->isVerified());

        $this->emailVerifier->handleEmailConfirmation(
            self::getRequest($signatureComponents->getSignedUrl()),
            $user
        );

        $this->assertTrue($user->isVerified());
    }

    private static function getRequest(string $signedUrl): Request
    {
        return Request::create($signedUrl);
    }
}
