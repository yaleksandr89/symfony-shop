<?php

declare(strict_types=1);

namespace App\Tests\Unit\Commerce\EventSubscriber;

use App\Commerce\Event\OrderCreatedFromCartEvent;
use App\Commerce\EventSubscriber\OrderCreatedFromCartSendNotificationSubscriber;
use App\Commerce\Mailer\OrderCreatedFromCartEmailSender;
use App\Entity\Order;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;

#[Group(name: 'unit')]
final class OrderCreatedFromCartSendNotificationSubscriberTest extends TestCase
{
    #[TestDox('Сбой письма клиенту не мешает попытке уведомить менеджера')]
    public function testClientTransportFailureDoesNotPreventManagerAttempt(): void
    {
        $order = new Order();
        $sender = $this->createMock(OrderCreatedFromCartEmailSender::class);
        $sender->expects(self::once())->method('sendEmailToClient')->with($order)
            ->willThrowException(new TransportException('client delivery unavailable'));
        $sender->expects(self::once())->method('sendEmailToManager')->with($order);

        $this->subscriber($sender)->onOrderCreatedFromCartEvent(new OrderCreatedFromCartEvent($order));
    }

    #[TestDox('Сбой письма менеджеру остаётся best-effort после успешного письма клиенту')]
    public function testManagerTransportFailureRemainsBestEffort(): void
    {
        $order = new Order();
        $sender = $this->createMock(OrderCreatedFromCartEmailSender::class);
        $sender->expects(self::once())->method('sendEmailToClient')->with($order);
        $sender->expects(self::once())->method('sendEmailToManager')->with($order)
            ->willThrowException(new TransportException('manager delivery unavailable'));

        $this->subscriber($sender)->onOrderCreatedFromCartEvent(new OrderCreatedFromCartEvent($order));
    }

    private function subscriber(OrderCreatedFromCartEmailSender $sender): OrderCreatedFromCartSendNotificationSubscriber
    {
        $subscriber = new OrderCreatedFromCartSendNotificationSubscriber();
        $subscriber->setOrderCreatedFromCartEmailSender($sender);

        return $subscriber;
    }
}
