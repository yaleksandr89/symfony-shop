<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\UserLoggedInViaSocialNetworkEvent;
use App\Utils\Mailer\Sender\UserLoggedInViaSocialNetworkEmailSender;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\Attribute\Required;

class UserLoggedInViaSocialNetworkSendNotificationSubscriber implements EventSubscriberInterface
{
    private UserLoggedInViaSocialNetworkEmailSender $mailerSender;

    #[Required]
    public function setOrderCreatedFromCartEmailSender(UserLoggedInViaSocialNetworkEmailSender $mailerSender): static
    {
        $this->mailerSender = $mailerSender;

        return $this;
    }

    public function onUserLoggedInViaSocialNetworkEvent(UserLoggedInViaSocialNetworkEvent $event): void
    {
        $user = $event->getUser();
        $plainPassword = $event->getPlainPassword();
        $verifyEmail = $event->getVerifyEmail();

        $this->mailerSender->sendEmailToClient($user, $plainPassword, $verifyEmail);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UserLoggedInViaSocialNetworkEvent::class => 'onUserLoggedInViaSocialNetworkEvent',
        ];
    }
}
