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

class MakeOrderFromCartSubscriber implements EventSubscriberInterface
{
    // >>> Autowiring
    /**
     * @var Security
     */
    private $security;

    /**
     * @required
     * @param Security $security
     * @return MakeOrderFromCartSubscriber
     */
    public function setSecurity(Security $security): MakeOrderFromCartSubscriber
    {
        $this->security = $security;
        return $this;
    }

    /**
     * @var OrderManager
     */
    private OrderManager $orderManager;

    /**
     * @required
     * @param OrderManager $orderManager
     * @return MakeOrderFromCartSubscriber
     */
    public function setOrderManager(OrderManager $orderManager): MakeOrderFromCartSubscriber
    {
        $this->orderManager = $orderManager;
        return $this;
    }

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @required
     * @param EventDispatcherInterface $eventDispatcher
     * @return MakeOrderFromCartSubscriber
     */
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): MakeOrderFromCartSubscriber
    {
        $this->eventDispatcher = $eventDispatcher;
        return $this;
    }
    // Autowiring <<<

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => [
                [
                    'makeOrder',
                    EventPriorities::PRE_WRITE
                ],
                [
                    'sendNotificationsAboutNewOrder',
                    EventPriorities::POST_WRITE
                ],
            ],
        ];
    }

    /**
     * @param ViewEvent $viewEvent
     * @throws JsonException
     * @return void
     */
    public function makeOrder(ViewEvent $viewEvent): void
    {
        /** @var Order $order */
        $order = $viewEvent->getControllerResult();
        $method = $this->getRequest($viewEvent)->getMethod();

        if (!$order instanceof Order || Request::METHOD_POST !== $method) {
            return;
        }

        /** @var User $user */
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

        $cartId = (int)$content['cartId'];

        $this->orderManager->addOrdersProductsFromCart($order, $cartId);
        $this->orderManager->calculationOrderTotalPrice($order);

        $order->setStatus(OrderStaticStorage::ORDER_STATUS_CREATED);
    }

    /**
     * @param ViewEvent $viewEvent
     * @return void
     */
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

    /**
     * @param ViewEvent $viewEvent
     * @return Request
     */
    private function getRequest(ViewEvent $viewEvent): Request
    {
        return $viewEvent->getRequest();
    }
}