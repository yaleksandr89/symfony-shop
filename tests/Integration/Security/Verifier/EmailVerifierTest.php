<?php

namespace App\Tests\Integration\Security\Verifier;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Verifier\EmailVerifier;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\Model\VerifyEmailSignatureComponents;

/**
 * @group integration
 */
class EmailVerifierTest extends KernelTestCase
{
    /**
     * @var UserRepository
     */
    private $userRepository;
    /**
     * @var EmailVerifier
     */
    private $emailVerifier;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->emailVerifier = self::getContainer()->get(EmailVerifier::class);
        $this->userRepository = self::getContainer()->get(UserRepository::class);
    }

    public function testGenerateEmailSignature(): void
    {
        $user = $this->userRepository->findOneBy(['email' => UserFixtures::USER_1_EMAIL]);
        $user->setIsVerified(false);

        $currentDateTime = new DateTimeImmutable();
        $emailSignature = $this->emailVerifier->generateEmailSignature('main_verify_email', $user);

        $this->assertGreaterThan($currentDateTime, $emailSignature->getExpiresAt());
    }

    /**
     * @throws VerifyEmailExceptionInterface
     */
    public function testHandleEmailConfirmation(): void
    {
        $user = $this->userRepository->findOneBy(['email' => UserFixtures::USER_1_EMAIL]);
        $user->setIsVerified(false);

        $currentDateTime = new DateTimeImmutable();
        $emailSignature = $this->emailVerifier->generateEmailSignature('main_verify_email', $user);

        $this->emailVerifier->handleEmailConfirmation($emailSignature->getSignedUrl(), $user);
        $this->assertTrue($user->isVerified());
    }

    /**
     * @return void
     * @throws VerifyEmailExceptionInterface
     */
    public function testGenerateEmailSignatureAndHandleEmailConfirmation(): void
    {
        $user = $this->userRepository->findOneBy(['email' => UserFixtures::USER_1_EMAIL]);
        $user->setIsVerified(false);

        $emailSignature = $this->checkGenerateEmailSignature($user);

        $this->checkHandleEmailConfirmation($emailSignature, $user);
    }

    /**
     * @param User $user
     * @return VerifyEmailSignatureComponents
     */
    private function checkGenerateEmailSignature(User $user): VerifyEmailSignatureComponents
    {
        $currentDateTime = new DateTimeImmutable();
        $emailSignature = $this->emailVerifier->generateEmailSignature('main_verify_email', $user);

        $this->assertGreaterThan($currentDateTime, $emailSignature->getExpiresAt());

        return $emailSignature;
    }

    /**
     * @param VerifyEmailSignatureComponents $signatureComponents
     * @param User $user
     * @return void
     * @throws VerifyEmailExceptionInterface
     */
    private function checkHandleEmailConfirmation(VerifyEmailSignatureComponents $signatureComponents, User $user): void
    {
        $this->assertFalse($user->isVerified());

        $this->emailVerifier->handleEmailConfirmation($signatureComponents->getSignedUrl(), $user);

        $this->assertTrue($user->isVerified());
    }
}
