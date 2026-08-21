<?php

declare(strict_types=1);

namespace App\Account\MessageHandler\Event;

use App\Account\Mailer\UserRegisteredEmailSender;
use App\Account\Manager\UserManager;
use App\Account\Message\Event\EventUserRegisteredEvent;
use App\Account\Security\Verifier\EmailVerifier;
use App\Entity\User;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(fromTransport: 'async')]
class UserRegisteredHandler
{
    public function __construct(
        private EmailVerifier $emailVerifier,
        private UserManager $userManager,
        private UserRegisteredEmailSender $emailSender,
    ) {
    }

    public function __invoke(EventUserRegisteredEvent $event): void
    {
        $userId = $event->getUserId();

        /** @var User|null $user */
        $user = $this->userManager->find($userId);

        if (!$user) {
            return;
        }

        $emailSignature = $this->emailVerifier
            ->generateEmailSignature('main_verify_email', $user);

        $this->emailSender->sendEmailToClient($user, $emailSignature);
    }
}
