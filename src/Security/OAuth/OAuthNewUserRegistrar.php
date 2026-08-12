<?php

declare(strict_types=1);

namespace App\Security\OAuth;

use App\Entity\User;
use App\Security\OAuth\Exception\OAuthLoginDeniedException;
use App\Utils\Mailer\Sender\UserRegisteredEmailSender;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class OAuthNewUserRegistrar
{
    public function __construct(
        private readonly OAuthIdentityAccessor $identityAccessor,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        private readonly UserRegisteredEmailSender $emailSender,
    ) {
    }

    public function register(User $user, OAuthProvider $provider): void
    {
        $email = trim($user->getEmail() ?? '');
        $externalId = $this->identityAccessor->getExternalId($user, $provider);
        if ('' === $email || null === $externalId || '' === trim($externalId)) {
            throw new OAuthLoginDeniedException();
        }

        $user->setIsVerified(false);
        $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
        $this->entityManager->persist($user);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new OAuthLoginDeniedException();
        }

        $userId = $user->getId();
        if (null === $userId) {
            throw new \LogicException('A persisted OAuth user must have an identifier.');
        }

        $signatureComponents = $this->verifyEmailHelper->generateSignature(
            'main_verify_email',
            (string) $userId,
            $email,
            ['id' => (string) $userId]
        );
        try {
            $this->emailSender->sendEmailToClient($user, $signatureComponents);
        } catch (TransportExceptionInterface) {
        }
    }
}
