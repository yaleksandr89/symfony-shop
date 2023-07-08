<?php

declare(strict_types=1);

namespace App\Messenger\MessageHandler\Event;

use App\Entity\User;
use App\Messenger\Message\Event\EventUserRegisteredEvent;
use App\Security\Verifier\EmailVerifier;
use App\Utils\Mailer\Sender\UserRegisteredEmailSender;
use App\Utils\Manager\UserManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(fromTransport: 'async')]
class UserRegisteredHandler
{
    private EmailVerifier $emailVerifier;

    private UserManager $userManager;

    private UserRegisteredEmailSender $emailSender;

    public function __construct(EmailVerifier $emailVerifier, UserManager $userManager, UserRegisteredEmailSender $emailSender)
    {
        $this->emailVerifier = $emailVerifier;
        $this->userManager = $userManager;
        $this->emailSender = $emailSender;
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
