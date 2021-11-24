<?php

declare(strict_types=1);

namespace App\Messanger\MessageHandler\Event;

use App\Entity\User;
use App\Messanger\Message\Event\EventUserRegisteredEvent;
use App\Security\Verifier\EmailVerifier;
use App\Utils\Mailer\Sender\UserRegisteredEmailSender;
use App\Utils\Manager\UserManager;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class UserRegisteredHandler implements MessageHandlerInterface
{
    /** @var EmailVerifier  */
    private EmailVerifier $emailVerifier;

    /** @var UserManager  */
    private UserManager $userManager;

    /** @var UserRegisteredEmailSender  */
    private UserRegisteredEmailSender $emailSender;

    /**
     * @param EmailVerifier $emailVerifier
     * @param UserManager $userManager
     * @param UserRegisteredEmailSender $emailSender
     */
    public function __construct(EmailVerifier $emailVerifier, UserManager $userManager, UserRegisteredEmailSender $emailSender)
    {
        $this->emailVerifier = $emailVerifier;
        $this->userManager = $userManager;
        $this->emailSender = $emailSender;
    }

    /**
     * @param EventUserRegisteredEvent $event
     */
    public function __invoke(EventUserRegisteredEvent $event)
    {
        $userId = $event->getUserId();

        /** @var User $user */
        $user = $this->userManager->find($userId);

        if (!$user) {
            return;
        }

        $emailSignature = $this->emailVerifier
            ->generateEmailSignature('main_verify_email', $user);

        $this->emailSender->sendEmailToClient($user, $emailSignature);
    }
}