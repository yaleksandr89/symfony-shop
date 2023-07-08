<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\OrderCreatedFromCartEvent;
use App\Utils\Mailer\Sender\OrderCreatedFromCartEmailSender;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\Attribute\Required;

class OrderCreatedFromCartSendNotificationSubscriber implements EventSubscriberInterface
{
    private OrderCreatedFromCartEmailSender $orderCreatedFromCartEmailSender;

    #[Required]
    public function setOrderCreatedFromCartEmailSender(OrderCreatedFromCartEmailSender $orderCreatedFromCartEmailSender): OrderCreatedFromCartSendNotificationSubscriber
    {
        $this->orderCreatedFromCartEmailSender = $orderCreatedFromCartEmailSender;

        return $this;
    }

    public function onOrderCreatedFromCartEvent(OrderCreatedFromCartEvent $event): void
    {
        $order = $event->getOrder();

        $this->orderCreatedFromCartEmailSender->sendEmailToClient($order);
        $this->orderCreatedFromCartEmailSender->sendEmailToManager($order);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderCreatedFromCartEvent::class => 'onOrderCreatedFromCartEvent',
        ];
    }
}
