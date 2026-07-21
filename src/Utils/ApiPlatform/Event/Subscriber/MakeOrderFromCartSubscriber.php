<?php

declare(strict_types=1);

namespace App\Utils\ApiPlatform\Event\Subscriber;

use ApiPlatform\Symfony\EventListener\EventPriorities;
use App\Entity\Cart;
use App\Entity\Order;
use App\Entity\StaticStorage\OrderStaticStorage;
use App\Entity\User;
use App\Event\OrderCreatedFromCartEvent;
use App\Utils\Manager\OrderManager;
use JsonException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
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
            throw new AccessDeniedHttpException();
        }

        $request = $this->getRequest($viewEvent);
        $cartId = $this->getCartId($request);

        /** @var Cart|null $cart */
        $cart = $this->orderManager->findCart($cartId);
        if (!$cart || $cart->getCartProducts()->isEmpty()) {
            throw new BadRequestHttpException('Checkout cart is unavailable.');
        }

        $cartToken = $request->cookies->get('CART_TOKEN');
        if (!is_string($cartToken) || '' === $cartToken || $cart->getToken() !== $cartToken) {
            throw new BadRequestHttpException('Checkout cart is unavailable.');
        }

        $order->setOwner($user);
        $this->orderManager->addOrdersProductsFromVerifiedCart($order, $cart);
        $this->orderManager->calculationOrderTotalPrice($order);

        $order->setStatus(OrderStaticStorage::ORDER_STATUS_CREATED);
    }

    private function getCartId(Request $request): int
    {
        try {
            $content = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new BadRequestHttpException('Invalid checkout request.');
        }

        if (!is_array($content) || !array_key_exists('cartId', $content) || !is_int($content['cartId']) || $content['cartId'] <= 0) {
            throw new BadRequestHttpException('Invalid checkout cart.');
        }

        return $content['cartId'];
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
