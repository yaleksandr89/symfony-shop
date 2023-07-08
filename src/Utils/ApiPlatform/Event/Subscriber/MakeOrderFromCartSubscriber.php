<?php

declare(strict_types=1);

namespace App\Utils\ApiPlatform\Event\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\Order;
use App\Entity\StaticStorage\OrderStaticStorage;
use App\Entity\User;
use App\Event\OrderCreatedFromCartEvent;
use App\Utils\Manager\OrderManager;
use JsonException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\Attribute\Required;

class MakeOrderFromCartSubscriber implements EventSubscriberInterface
{
    private Security $security;

    #[Required]
    public function setSecurity(Security $security): MakeOrderFromCartSubscriber
    {
        $this->security = $security;

        return $this;
    }

    private OrderManager $orderManager;

    #[Required]
    public function setOrderManager(OrderManager $orderManager): MakeOrderFromCartSubscriber
    {
        $this->orderManager = $orderManager;

        return $this;
    }

    private EventDispatcherInterface $eventDispatcher;

    #[Required]
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): MakeOrderFromCartSubscriber
    {
        $this->eventDispatcher = $eventDispatcher;

        return $this;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => [
                [
                    'makeOrder',
                    EventPriorities::PRE_WRITE,
                ],
                [
                    'sendNotificationsAboutNewOrder',
                    EventPriorities::POST_WRITE,
                ],
            ],
        ];
    }

    /**
     * @throws JsonException
     */
    public function makeOrder(ViewEvent $viewEvent): void
    {
        /** @var Order $order */
        $order = $viewEvent->getControllerResult();
        $method = $this->getRequest($viewEvent)->getMethod();

        if (!$order instanceof Order || Request::METHOD_POST !== $method) {
            return;
        }

        /** @var User|null $user */
        $user = $this->security->getUser();
        if (!$user) {
            return;
        }

        $order->setOwner($user);
        $contentJson = $this->getRequest($viewEvent)->getContent();
        if (!$contentJson) {
            return;
        }

        $content = json_decode($contentJson, true, 512, JSON_THROW_ON_ERROR);
        if (!array_key_exists('cartId', $content)) {
            return;
        }

        $cartId = (int) $content['cartId'];

        $this->orderManager->addOrdersProductsFromCart($order, $cartId);
        $this->orderManager->calculationOrderTotalPrice($order);

        $order->setStatus(OrderStaticStorage::ORDER_STATUS_CREATED);
    }

    public function sendNotificationsAboutNewOrder(ViewEvent $viewEvent): void
    {
        /** @var Order $order */
        $order = $viewEvent->getControllerResult();
        $method = $this->getRequest($viewEvent)->getMethod();

        if (!$order instanceof Order || Request::METHOD_POST !== $method) {
            return;
        }

        $event = new OrderCreatedFromCartEvent($order);
        $this->eventDispatcher->dispatch($event);
    }

    private function getRequest(ViewEvent $viewEvent): Request
    {
        return $viewEvent->getRequest();
    }
}
