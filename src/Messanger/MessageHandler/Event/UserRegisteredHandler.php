<?php

declare(strict_types=1);

namespace App\Messanger\MessageHandler\Event;

use App\Messanger\Message\Event\EventUserRegisteredEvent;
use App\Security\Verifier\EmailVerifier;
use App\Utils\Manager\UserManager;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class UserRegisteredHandler implements MessageHandlerInterface
{
    /**
     * @var EmailVerifier
     */
    private EmailVerifier $emailVerifier;
    /**
     * @var UserManager
     */
    private UserManager $userManager;

    public function __construct(EmailVerifier $emailVerifier, UserManager $userManager)
    {
        $this->emailVerifier = $emailVerifier;
        $this->userManager = $userManager;
    }

    public function __invoke(EventUserRegisteredEvent $event)
    {
        dump($event->getUser());
    }
}