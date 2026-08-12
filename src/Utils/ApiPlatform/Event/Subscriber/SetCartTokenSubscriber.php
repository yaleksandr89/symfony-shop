<?php

declare(strict_types=1);

namespace App\Utils\ApiPlatform\Event\Subscriber;

use ApiPlatform\Symfony\EventListener\EventPriorities;
use App\Entity\Cart;
use App\Utils\Generator\TokenGenerator;
use Exception;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class SetCartTokenSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => [
                'setCartTokenToCart', EventPriorities::PRE_VALIDATE,
            ],
        ];
    }

    /**
     * @throws Exception
     */
    public function setCartTokenToCart(ViewEvent $event): void
    {
        $cart = $event->getControllerResult();
        $method = $event->getRequest()->getMethod();

        if (!$cart instanceof Cart || Request::METHOD_POST !== $method) {
            return;
        }

        $cartToken = $event->getRequest()->cookies->get('CART_TOKEN');

        if (!is_string($cartToken) || !preg_match('/\A[0-9a-f]{32}\z/', $cartToken)) {
            $cartToken = TokenGenerator::generateToken();
        }

        $cart->setToken($cartToken);
    }
}
