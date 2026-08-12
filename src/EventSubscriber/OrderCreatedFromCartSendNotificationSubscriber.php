<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\OrderCreatedFromCartEvent;
use App\Utils\Mailer\Sender\OrderCreatedFromCartEmailSender;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
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

        // Email delivery is best-effort after the order has already been committed.
        try {
            $this->orderCreatedFromCartEmailSender->sendEmailToClient($order);
        } catch (TransportExceptionInterface) {
        }

        try {
            $this->orderCreatedFromCartEmailSender->sendEmailToManager($order);
        } catch (TransportExceptionInterface) {
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderCreatedFromCartEvent::class => 'onOrderCreatedFromCartEvent',
        ];
    }
}
